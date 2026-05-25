<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import maplibregl from 'maplibre-gl'
import MapboxDraw from '@mapbox/mapbox-gl-draw'
import patchedDrawTheme from './draw-modes/drawTheme.js'
import 'maplibre-gl/dist/maplibre-gl.css'
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css'
import { mapLayers } from '@/config/mapLayers.js'
import { elementTypesBySubtype } from '@/config/elementTypes.js'
import { useUndoStack } from '@/composables/useUndoStack.js'
import { useMapElements } from '@/composables/useMapElements.js'
import DrawRectangleMode from './draw-modes/DrawRectangleMode.js'
import DrawCircleMode from './draw-modes/DrawCircleMode.js'
import { LayerControl } from 'maplibre-gl-layer-control'
import PlanSwitcher from './PlanSwitcher.vue'
import ElementSidebar from './ElementSidebar.vue'
import PropertiesPanel from './PropertiesPanel.vue'
import PlanPropertiesPanel from './PlanPropertiesPanel.vue'
import axios from 'axios'
import { useMapExport } from '@/composables/useMapExport.js'
import { computeBoundsFromElements } from '@/utils/mapBounds.js'
import { length as turfLength } from '@turf/turf'
import { loadElementIcons } from '@/utils/mapIcons.js'

const props = defineProps({
    event: { type: Object, required: true },
    initialPlans: { type: Array, required: true },
})

const mapContainer = ref(null)
const gpxFileInput = ref(null)
let map = null
let draw = null
let drawEditingId = null
let drawEditingDrawId = null
let prevDrawMode = null       // track draw mode transitions
let _drawCreatePending = false // suppress element clicks while create is in flight

// ── Route distance tracking ───────────────────────────────────────────────────
const routeDistanceLabel = ref(null)
const routeLabelPos = ref({ x: 0, y: 0 })
let routeMouseMoveListener = null
let routeEditMoveListener = null

function startRouteEditDistanceTracking() {
    if (routeEditMoveListener) map.off('mousemove', routeEditMoveListener)
    routeEditMoveListener = (e) => {
        if (!drawEditingDrawId || !draw) return
        const feat = draw.get(drawEditingDrawId)
        if (!feat || feat.geometry.type !== 'LineString') { routeDistanceLabel.value = null; return }
        const coords = feat.geometry.coordinates
        if (coords.length < 2) { routeDistanceLabel.value = null; return }
        const km = turfLength({ type: 'Feature', geometry: feat.geometry }, { units: 'kilometers' })
        routeDistanceLabel.value = km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(2)} km`
        routeLabelPos.value = { x: e.point.x, y: e.point.y }
    }
    map.on('mousemove', routeEditMoveListener)
    routeEditMoveListener()   // show immediately
}

function stopRouteEditDistanceTracking() {
    if (routeEditMoveListener) {
        map?.off('mousemove', routeEditMoveListener)
        routeEditMoveListener = null
        routeDistanceLabel.value = null
    }
}

function startRouteDistanceTracking() {
    stopRouteDistanceTracking()
    routeMouseMoveListener = (e) => {
        if (!draw || draw.getMode() !== 'draw_line_string') {
            stopRouteDistanceTracking()
            return
        }
        const features = draw.getAll().features
        const line = features.find(f => f.geometry.type === 'LineString')
        if (!line || line.geometry.coordinates.length < 1) {
            routeDistanceLabel.value = null
            return
        }
        const coords = [...line.geometry.coordinates, [e.lngLat.lng, e.lngLat.lat]]
        if (coords.length < 2) return
        const km = turfLength({ type: 'Feature', geometry: { type: 'LineString', coordinates: coords } }, { units: 'kilometers' })
        routeDistanceLabel.value = km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(2)} km`
        const px = map.project(e.lngLat)
        routeLabelPos.value = { x: px.x, y: px.y }
    }
    map.on('mousemove', routeMouseMoveListener)
}

function stopRouteDistanceTracking() {
    if (routeMouseMoveListener) {
        map?.off('mousemove', routeMouseMoveListener)
        routeMouseMoveListener = null
    }
    routeDistanceLabel.value = null
}

const plans = ref([...props.initialPlans])
const activePlanId = ref(null)   // null = shared mode (create elements without a plan)
const selectedElementId = ref(null)
const canEdit = props.event.role !== 'viewer'
const publicToken = props.event.public_token || null

const expandedGroupIds = ref([])
const activePlan = computed(() => plans.value.find(p => p.id === activePlanId.value) || null)

function toggleGroupExpansion(groupId) {
    if (expandedGroupIds.value.includes(groupId)) {
        expandedGroupIds.value = expandedGroupIds.value.filter(id => id !== groupId)
    } else {
        expandedGroupIds.value = [...expandedGroupIds.value, groupId]
    }
    renderElements()
}

const { exportPng } = useMapExport(() => map)

const { push: pushUndo, undo, redo, canUndo, canRedo } = useUndoStack()
const { elements, saving, load, create, update, remove } = useMapElements(props.event.id, activePlanId, publicToken)

const selectedElement = () => elements.value.find(e => e.id === selectedElementId.value) ?? null

// ── Map initialization ──────────────────────────────────────────────────────

onMounted(async () => {
    const baseLayer = mapLayers.find(l => l.type === 'vector-style')

    map = new maplibregl.Map({
        container: mapContainer.value,
        style: baseLayer.url,
        center: [4.35, 50.85],
        zoom: 13,
    })

    map.addControl(new maplibregl.NavigationControl(), 'bottom-right')

    if (canEdit) {
        draw = new MapboxDraw({
            displayControlsDefault: false,
            controls: {},
            styles: patchedDrawTheme,
            modes: {
                ...MapboxDraw.modes,
                draw_rectangle: DrawRectangleMode,
                draw_circle: DrawCircleMode,
            },
        })
        map.addControl(draw)

        map.on('draw.create', onDrawCreate)
        map.on('draw.update', onDrawUpdate)
        map.on('draw.delete', onDrawDelete)
        map.on('draw.selectionchange', onDrawSelection)
        // Exit draw-edit when user clicks outside a polygon (direct_select → simple_select)
        map.on('draw.modechange', (e) => {
            if (prevDrawMode === 'direct_select' && e.mode === 'simple_select' && drawEditingId !== null) {
                exitDrawEdit()
            }
            prevDrawMode = e.mode
        })
    }

    const onMapLoad = async () => {
        map.resize() // Sync canvas to actual container dimensions (flex layout may finish after init)

        // Add all WMTS/XYZ overlay layers from the registry (hidden by default)
        // The LayerControl plugin lets users toggle them and adjust opacity.
        const overlayDefs = mapLayers.filter(l => l.type === 'wmts' || l.type === 'xyz')
        const layerStates = {}
        for (const ol of overlayDefs) {
            map.addSource(`overlay-${ol.id}`, {
                type: 'raster',
                tiles: [ol.tileUrlTemplate],
                tileSize: 256,
                attribution: ol.attribution,
            })
            map.addLayer({
                id: ol.id,
                type: 'raster',
                source: `overlay-${ol.id}`,
                layout: { visibility: 'none' },
            })
            layerStates[ol.id] = { visible: false, opacity: 1, name: ol.label }
        }
        if (overlayDefs.length > 0) {
            map.addControl(new LayerControl({
                layers: overlayDefs.map(ol => ol.id),
                layerStates,
                showOpacitySlider: true,
                showStyleEditor: false,
                showLayerSymbol: false,
                collapsed: true,
            }), 'bottom-right')
        }

        await Promise.all([load(), loadElementIcons(map)])
        renderElements()
        fitToElements()
    }

    if (map.isStyleLoaded()) {
        onMapLoad()
    } else {
        map.on('load', onMapLoad)
    }
})

onUnmounted(() => { stopRouteDistanceTracking(); stopRouteEditDistanceTracking(); map?.remove(); map = null })

// ── GPX import ───────────────────────────────────────────────────────────────

function parseGpx(xmlText) {
    const parser = new DOMParser()
    const doc = parser.parseFromString(xmlText, 'application/xml')
    if (doc.querySelector('parsererror')) throw new Error('Invalid GPX file')

    const routes = []
    const trksegs = Array.from(doc.getElementsByTagName('trkseg'))
    for (const seg of trksegs) {
        const coords = Array.from(seg.getElementsByTagName('trkpt')).map(pt => [
            parseFloat(pt.getAttribute('lon')),
            parseFloat(pt.getAttribute('lat')),
        ]).filter(([lng, lat]) => Number.isFinite(lng) && Number.isFinite(lat))
        if (coords.length >= 2) routes.push(coords)
    }
    return routes
}

async function handleGpxUpload(event) {
    const file = event.target.files?.[0]
    if (!file) return
    try {
        const text = await file.text()
        const coordSets = parseGpx(text)
        if (!coordSets.length) { alert('No track segments found in GPX file.'); return }

        const defaultStyle = elementTypesBySubtype['course']?.defaultStyle || {}
        for (const coords of coordSets) {
            const newEl = await create({
                type: 'route',
                subtype: 'course',
                geometry: { type: 'LineString', coordinates: coords },
                properties: { styling: defaultStyle },
                parent_id: null,
            })
            pushUndo(async () => { await remove(newEl.id); renderElements() })
        }
        renderElements()
    } catch (err) {
        alert('Failed to import GPX: ' + err.message)
    } finally {
        event.target.value = ''
    }
}

// ── Plan switching ───────────────────────────────────────────────────────────

async function switchPlan(planId) {   // planId may be null (deselect → shared mode)
    exitDrawEdit()
    activePlanId.value = planId
    selectedElementId.value = null
    await load()
    renderElements()
}

function fitToElements() {
    const bounds = computeBoundsFromElements(elements.value)
    if (!bounds) return
    const [[minLng, minLat], [maxLng, maxLat]] = bounds
    if (minLng === maxLng && minLat === maxLat) {
        map.jumpTo({ center: [minLng, minLat], zoom: 14 })
    } else {
        // animate: false avoids the animation being cancelled by concurrent layer additions (e.g. MapboxDraw)
        map.fitBounds(bounds, { padding: 80, maxZoom: 17, animate: false })
    }
}

// ── Plan event handlers ──────────────────────────────────────────────────────

function handlePlanCreated(plan) {
    plans.value.push(plan)
    switchPlan(plan.id)
}

function handlePlanRenamed(plan) {
    const i = plans.value.findIndex(x => x.id === plan.id)
    if (i !== -1) plans.value[i] = plan
}

function handlePlanDeleted(id) {
    plans.value = plans.value.filter(p => p.id !== id)
    if (activePlanId.value === id && plans.value.length > 0) switchPlan(plans.value[0].id)
}

// ── Drawing ──────────────────────────────────────────────────────────────────

let pendingSubtype = null
let pendingType = null

function startDraw({ mode, subtype }) {
    exitDrawEdit()
    pendingSubtype = subtype
    pendingType = mode

    const typeDef = elementTypesBySubtype[subtype]
    const drawMode = typeDef?.drawMode
        || (mode === 'marker'
            ? 'draw_point'
            : mode === 'route'
                ? 'draw_line_string'
                : mode === 'infrastructure'
                    ? 'draw_rectangle'
                    : 'draw_polygon')

    draw.changeMode(drawMode)
    if (mode === 'route') startRouteDistanceTracking()
}

async function onDrawCreate(e) {
    _drawCreatePending = true
    stopRouteDistanceTracking()
    const feature    = e.features[0]
    const subtypeKey = pendingSubtype || (pendingType === 'group' ? 'group' : null)
    const typeDef    = subtypeKey ? elementTypesBySubtype[subtypeKey] : null
    const defaultStyle = typeDef?.defaultStyle || {}

    const activeEl = selectedElement()
    const parentId = (activeEl?.type === 'group') ? activeEl.id : null

    const newEl = await create({
        type:       pendingType || featureType(feature),
        subtype:    pendingSubtype || null,
        geometry:   feature.geometry,
        properties: { styling: defaultStyle },
        parent_id:  parentId,
    })

    draw.delete(feature.id)
    _drawCreatePending = false
    renderElements()
    selectedElementId.value = newEl.id

    if (parentId && !expandedGroupIds.value.includes(parentId)) {
        toggleGroupExpansion(parentId)
    }

    pushUndo(async () => {
        await remove(newEl.id)
        renderElements()
        selectedElementId.value = null
    })
}

async function onDrawUpdate(e) {
    const feature = e.features[0]
    // _elId may come back as a number or (rarely) string — normalise to number.
    const elId = Number(feature.properties._elId)
    const idx = elements.value.findIndex(el => el.id === elId)
    if (idx === -1) return

    const prev = { ...elements.value[idx] }

    // Optimistic local update so exitDrawEdit's renderElements sees the new geometry
    // even if the server response hasn't arrived yet.
    elements.value[idx] = { ...elements.value[idx], geometry: feature.geometry }
    renderElements()

    const newGeometry = feature.geometry
    pushUndo(
        async () => { exitDrawEdit(); await update(prev.id, { geometry: prev.geometry }); renderElements() },
        async () => { await update(prev.id, { geometry: newGeometry }); renderElements() },
    )

    await update(prev.id, { geometry: feature.geometry })
}

async function onDrawDelete(e) {
    stopRouteDistanceTracking()
    const feature = e.features[0]
    const el = elements.value.find(el => el.id === feature.properties._elId)
    if (!el) return

    drawEditingId = null
    drawEditingDrawId = null
    const snapshot = { ...el }
    await remove(el.id)
    renderElements()

    pushUndo(async () => {
        const restored = await create({ ...snapshot, id: undefined })
        renderElements()
        selectedElementId.value = restored.id
    })
}

function onDrawSelection(e) {
    if (e.features.length > 0) {
        const elId = Number(e.features[0].properties._elId) || null
        selectedElementId.value = elId
    } else {
        // direct_select mode doesn't report a selection — guard against spurious exits
        if (draw?.getMode() !== 'direct_select') {
            selectedElementId.value = null
            exitDrawEdit()
        }
    }
}

function featureType(feature) {
    const typeMap = { Point: 'marker', LineString: 'route', Polygon: 'zone' }
    return typeMap[feature.geometry.type] || 'marker'
}

// ── Draw editing ──────────────────────────────────────────────────────────────

function enterDrawEdit(el) {
    const feature = {
        type: 'Feature',
        properties: { _elId: el.id },
        geometry: el.geometry,
    }
    const [drawFeatureId] = draw.add(feature)
    drawEditingId = el.id
    drawEditingDrawId = drawFeatureId
    renderElements()
    // Defer the mode change so it runs after the current click event finishes
    // processing inside MapboxDraw. Calling draw.changeMode() synchronously
    // inside a MapLibre layer-click handler causes MapboxDraw to re-process the
    // same click in the new mode, which throws an internal error and leaves the
    // element invisible. Wrapping in try/catch suppresses the benign error.
    setTimeout(() => {
        if (drawEditingId !== el.id) return // guard: exited before timeout fired
        try {
            if (el.geometry.type === 'Point') {
                draw.changeMode('simple_select', { featureIds: [drawFeatureId] })
            } else if (el.geometry.type === 'LineString') {
                draw.changeMode('direct_select', { featureId: drawFeatureId })
                startRouteEditDistanceTracking()
            } else {
                draw.changeMode('direct_select', { featureId: drawFeatureId })
            }
        } catch {
            // MapboxDraw may throw an internal error when changeMode is called
            // shortly after a user click. The feature is still added to the draw
            // layer via draw.add(), so editing still works. exitDrawEdit() handles
            // cleanup when the user clicks away.
        }
    }, 0)
}

function exitDrawEdit() {
    if (drawEditingId === null) return
    stopRouteEditDistanceTracking()
    const exitingId = drawEditingId

    // Sync the latest draw-layer geometry back into elements.value before clearing.
    // This is the safety net for cases where onDrawUpdate missed the move
    // (e.g. type coercion edge-cases or rapid click-away sequences).
    if (drawEditingDrawId !== null) {
        const drawFeature = draw.get(drawEditingDrawId)
        if (drawFeature) {
            const idx = elements.value.findIndex(e => e.id === exitingId)
            if (idx !== -1) {
                const cur = JSON.stringify(elements.value[idx].geometry)
                const nxt = JSON.stringify(drawFeature.geometry)
                if (cur !== nxt) {
                    elements.value[idx] = { ...elements.value[idx], geometry: drawFeature.geometry }
                    update(exitingId, { geometry: drawFeature.geometry })
                }
            }
        }
        drawEditingDrawId = null
    }

    drawEditingId = null
    draw.deleteAll()
    renderElements()
}

// ── Rendering ────────────────────────────────────────────────────────────────

function renderElements() {
    if (!map || !map.isStyleLoaded()) return

    const sourceId = 'elements'
    const visibleEls = elements.value.filter(el => {
        if (el.type === 'group') return false        // folders are sidebar-only, no map geometry
        if (el.id === drawEditingId) return false
        if (el.is_hidden) return false
        if (el.parent_id != null) return expandedGroupIds.value.includes(el.parent_id)
        return true
    })

    const geojson = {
        type: 'FeatureCollection',
        features: visibleEls.map(el => ({
            type: 'Feature',
            id: el.id,
            properties: {
                _elId: el.id,
                type: el.type,
                subtype: el.subtype,
                elementName: el.name || '',
                fillColor: el.properties?.styling?.fill_color || elementTypesBySubtype[el.subtype]?.defaultStyle?.color || '#3b82f6',
                strokeColor: el.properties?.styling?.stroke_color || '#1d4ed8',
                opacity: el.properties?.styling?.opacity ?? 0.4,
                lineWidth: elementTypesBySubtype[el.subtype]?.defaultStyle?.width || 2,
                strokeType: el.properties?.styling?.stroke_type || 'solid',
            },
            geometry: el.geometry,
        })),
    }

    if (map.getSource(sourceId)) {
        map.getSource(sourceId).setData(geojson)
    } else {
        map.addSource(sourceId, { type: 'geojson', data: geojson })

        map.addLayer({
            id: 'elements-fill',
            type: 'fill',
            source: sourceId,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'fill-color': ['get', 'fillColor'],
                'fill-opacity': ['get', 'opacity'],
            },
        })
            // Solid lines (default)
            map.addLayer({
                id: 'elements-line',
                type: 'line',
                source: sourceId,
                filter: ['all',
                    ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
                    ['!', ['in', ['get', 'strokeType'], ['literal', ['dashed', 'dotted']]]],
                ],
                paint: {
                    'line-color': ['get', 'strokeColor'],
                    'line-width': ['get', 'lineWidth'],
                },
            })
            // Dashed lines
            map.addLayer({
                id: 'elements-line-dashed',
                type: 'line',
                source: sourceId,
                filter: ['all',
                    ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
                    ['==', ['get', 'strokeType'], 'dashed'],
                ],
                paint: {
                    'line-color': ['get', 'strokeColor'],
                    'line-width': ['get', 'lineWidth'],
                    'line-dasharray': [6, 4],
                },
            })
            // Dotted lines
            map.addLayer({
                id: 'elements-line-dotted',
                type: 'line',
                source: sourceId,
                filter: ['all',
                    ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
                    ['==', ['get', 'strokeType'], 'dotted'],
                ],
                paint: {
                    'line-color': ['get', 'strokeColor'],
                    'line-width': ['get', 'lineWidth'],
                    'line-dasharray': [1, 5],
                },
            })
            // Symbol icons for all markers except text labels
            map.addLayer({
                id: 'elements-symbol',
                type: 'symbol',
                source: sourceId,
                filter: ['all',
                    ['==', ['geometry-type'], 'Point'],
                    ['!=', ['get', 'subtype'], 'text_label'],
                ],
                layout: {
                    'icon-image': ['get', 'subtype'],
                    'icon-size': 1,
                    'icon-allow-overlap': true,
                    'icon-ignore-placement': true,
                },
            })
            // Text labels
            map.addLayer({
                id: 'elements-text-label',
                type: 'symbol',
                source: sourceId,
                filter: ['all',
                    ['==', ['geometry-type'], 'Point'],
                    ['==', ['get', 'subtype'], 'text_label'],
                ],
                layout: {
                    'text-field': ['case',
                        ['!=', ['get', 'elementName'], ''], ['get', 'elementName'],
                        'Text',
                    ],
                    'text-size': 14,
                    'text-anchor': 'center',
                    'text-allow-overlap': true,
                    'text-ignore-placement': true,
                },
                paint: {
                    'text-color': ['get', 'fillColor'],
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                },
            })

            const onElementClick = (e) => {
                // Ignore clicks while a draw-create is resolving (prevents group intercept)
                if (_drawCreatePending) return
                // Ignore clicks while actively drawing a new element
                const mode = draw?.getMode()
                if (mode && !['simple_select', 'direct_select'].includes(mode)) return
                const elId = e.features[0].properties._elId
                selectedElementId.value = elId
            if (canEdit) {
                const el = elements.value.find(el => el.id === elId)
                if (el && !el.is_locked) {
                    if (drawEditingId !== null && drawEditingId !== elId) exitDrawEdit()
                    if (drawEditingId === null) enterDrawEdit(el)
                }
            }
        }
        map.on('click', 'elements-symbol', onElementClick)
        map.on('click', 'elements-text-label', onElementClick)
        map.on('click', 'elements-fill', onElementClick)
        map.on('click', 'elements-line', onElementClick)
        map.on('click', 'elements-line-dashed', onElementClick)
        map.on('click', 'elements-line-dotted', onElementClick)

        // Deselect when clicking empty map area. The layer-specific handlers
        // above set _elementClickedThisFrame = true; if the general handler
        // sees it was not set, the click landed on empty map.
        let _elementClickedThisFrame = false
        const _markElementClick = (e) => { _elementClickedThisFrame = true }
        map.on('click', 'elements-symbol', _markElementClick)
        map.on('click', 'elements-text-label', _markElementClick)
        map.on('click', 'elements-fill', _markElementClick)
        map.on('click', 'elements-line', _markElementClick)
        map.on('click', 'elements-line-dashed', _markElementClick)
        map.on('click', 'elements-line-dotted', _markElementClick)
        map.on('click', () => {
            if (_elementClickedThisFrame) {
                _elementClickedThisFrame = false
                return
            }
            selectedElementId.value = null
            exitDrawEdit()
        })
    }
}

// ── Element updates from PropertiesPanel ─────────────────────────────────────

async function handleMoveToGroup(elementId, groupId) {
    const el = elements.value.find(e => e.id === elementId)
    if (!el || el.type === 'group') return
    await update(elementId, { parent_id: groupId })
    renderElements()
    if (!expandedGroupIds.value.includes(groupId)) toggleGroupExpansion(groupId)
}

async function handleRemoveFromGroup(elementId) {
    await update(elementId, { parent_id: null })
    renderElements()
}

async function handleDelete(id) {
    const snapshot = elements.value.find(e => e.id === id)
    if (!snapshot) return
    await remove(id)
    selectedElementId.value = null
    renderElements()

    pushUndo(async () => {
        const restored = await create({ ...snapshot, id: undefined })
        renderElements()
        selectedElementId.value = restored.id
    })
}

function handleLiveName(name) {
    if (selectedElementId.value === null) return
    const idx = elements.value.findIndex(e => e.id === selectedElementId.value)
    if (idx !== -1) {
        elements.value[idx] = { ...elements.value[idx], name }
        renderElements()
    }
}

async function handleUpdate(payload) {
    const { id, ...fields } = payload
    const prev = elements.value.find(e => e.id === id)
    const prevSnapshot = { ...prev }

    await update(id, fields)
    renderElements()

    pushUndo(
        async () => { await update(prevSnapshot.id, Object.fromEntries(Object.keys(fields).map(k => [k, prevSnapshot[k]]))); renderElements() },
        async () => { await update(prevSnapshot.id, fields); renderElements() },
    )
}

async function handlePlanUpdate(properties) {
    const { data } = await axios.patch(`/api/plans/${activePlanId.value}`, { properties })
    const i = plans.value.findIndex(p => p.id === activePlanId.value)
    if (i !== -1) plans.value[i] = data
}

async function handleToggleLock(el) {
    const newLocked = !el.is_locked
    await handleUpdate({ id: el.id, is_locked: newLocked })
    if (el.type === 'group') {
        const children = elements.value.filter(c => c.parent_id === el.id)
        await Promise.all(children.map(c => update(c.id, { is_locked: newLocked })))
        renderElements()
    }
}

async function handleToggleHide(el) {
    const newHidden = !el.is_hidden
    await handleUpdate({ id: el.id, is_hidden: newHidden })
    if (el.type === 'group') {
        const children = elements.value.filter(c => c.parent_id === el.id)
        await Promise.all(children.map(c => update(c.id, { is_hidden: newHidden })))
    }
    renderElements()
}

async function handleCreateGroup(name) {
    const newGroup = await create(
        { type: 'group', subtype: null, name, geometry: null, properties: {} },
        { forPlan: false },   // groups are always shared (not plan-scoped)
    )
    if (!expandedGroupIds.value.includes(newGroup.id)) {
        expandedGroupIds.value = [...expandedGroupIds.value, newGroup.id]
    }
    selectedElementId.value = newGroup.id
}

async function handleUndo() {
    await undo()
}

async function handleRedo() {
    await redo()
}

// ── Keyboard shortcut ────────────────────────────────────────────────────────

function resetNorth() {
    map?.resetNorth()
}

function onKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey && canEdit) {
        e.preventDefault()
        handleUndo()
    }
    if ((e.ctrlKey || e.metaKey) && canEdit && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
        e.preventDefault()
        handleRedo()
    }
}
document.addEventListener('keydown', onKeydown)
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
    <div class="h-screen flex flex-col">
        <!-- Toolbar -->
        <div class="flex items-center gap-3 px-4 py-2 border-b bg-white shrink-0 overflow-x-auto">
            <PlanSwitcher
                :event-id="event.id"
                :plans="plans"
                :active-plan-id="activePlanId"
                :can-edit="canEdit"
                @switch="switchPlan"
                @created="handlePlanCreated"
                @renamed="handlePlanRenamed"
                @deleted="handlePlanDeleted"
            />
            <div class="w-px h-5 bg-gray-200 shrink-0"></div>
            <template v-if="canEdit">
                <button @click="gpxFileInput.click()" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50">Import GPX</button>
                <input ref="gpxFileInput" type="file" accept=".gpx" class="hidden" @change="handleGpxUpload" />
            </template>
            <div class="w-px h-5 bg-gray-200 shrink-0"></div>
            <button
                v-if="canEdit"
                :disabled="!canUndo()"
                @click="handleUndo"
                class="text-xs border rounded px-2 py-1.5 disabled:opacity-40 hover:bg-gray-50"
                title="Undo (Ctrl+Z)"
            >&#x21A9; Undo</button>
            <button
                v-if="canEdit"
                :disabled="!canRedo()"
                @click="handleRedo"
                class="text-xs border rounded px-2 py-1.5 disabled:opacity-40 hover:bg-gray-50"
                title="Redo (Ctrl+Shift+Z / Ctrl+Y)"
            >&#x21AA; Redo</button>
            <div class="w-px h-5 bg-gray-200 shrink-0"></div>
            <div class="flex gap-1 shrink-0">
                <button @click="exportPng()" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50">Export PNG</button>
                <button @click="exportPng({ print: true })" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50">Print PNG</button>
                <a v-if="activePlanId !== null" :href="`/api/plans/${activePlanId}/export/csv`" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50" download>Export CSV</a>
            </div>
            <button
                class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50 ml-auto shrink-0"
                title="Reset to north"
                @click="resetNorth"
            >&#x2B06; N</button>
            <span v-if="saving" class="text-xs text-gray-400 shrink-0">Saving&#x2026;</span>
        </div>

        <!-- Body -->
        <div class="flex flex-1 min-h-0">
            <ElementSidebar
                :elements="elements"
                :selected-id="selectedElementId"
                :can-edit="canEdit"
                :expanded-group-ids="expandedGroupIds"
                :plans="plans"
                :active-plan-id="activePlanId"
                @select="id => selectedElementId = id"
                @toggle-lock="handleToggleLock"
                @toggle-hide="handleToggleHide"
                @toggle-group="toggleGroupExpansion"
                @move-to-group="handleMoveToGroup"
                @remove-from-group="handleRemoveFromGroup"
                @create-group="handleCreateGroup"
                @draw="startDraw"
            />
            <div class="flex-1 relative min-w-0">
                <div ref="mapContainer" class="w-full h-full" />
                <div
                    v-if="routeDistanceLabel"
                    class="pointer-events-none absolute z-10 bg-white/90 border border-gray-200 text-xs px-2 py-1 rounded shadow-sm text-gray-700"
                    :style="{ left: (routeLabelPos.x + 14) + 'px', top: routeLabelPos.y + 'px', transform: 'translateY(-50%)' }"
                >{{ routeDistanceLabel }}</div>
            </div>
            <PropertiesPanel
                v-if="selectedElement()"
                :element="selectedElement()"
                :plans="plans"
                :can-edit="canEdit"
                @update="handleUpdate"
                @delete="handleDelete"
                @live-name="handleLiveName"
            />
            <PlanPropertiesPanel
                v-else-if="activePlan"
                :plan="activePlan"
                :can-edit="canEdit"
                @update="handlePlanUpdate"
            />
        </div>
    </div>
</template>
