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
import { useRouteDistanceTracker } from '@/composables/useRouteDistanceTracker.js'
import { useElementRenderer } from '@/composables/useElementRenderer.js'
import { useDrawStateMachine } from '@/composables/useDrawStateMachine.js'
import { computeBoundsFromElements } from '@/utils/mapBounds.js'
import { loadElementIcons } from '@/utils/mapIcons.js'

const props = defineProps({
    event: { type: Object, required: true },
    initialPlans: { type: Array, required: true },
})

const mapContainer = ref(null)
const gpxFileInput = ref(null)
let map = null
let draw = null

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

const tracker = useRouteDistanceTracker(() => map, () => draw)
const { routeDistanceLabel, routeLabelPos } = tracker

const renderer = useElementRenderer(() => map)

const drawMachine = useDrawStateMachine({
    getDraw: () => draw,
    elements,
    expandedGroupIds,
    selectedElementId,
    renderElements,
    create, update, remove,
    pushUndo,
    routeTracker: tracker,
})
const {
    drawEditingId,
    drawCreatePending,
    startDraw,
    enterDrawEdit,
    exitDrawEdit,
    onDrawCreate,
    onDrawUpdate,
    onDrawDelete,
    onDrawSelection,
    onModeChange,
} = drawMachine

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
        map.on('draw.modechange', onModeChange)
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
        renderer.initLayers(onClickElement, onClickEmpty)
        renderElements()
        fitToElements()
    }

    if (map.isStyleLoaded()) {
        onMapLoad()
    } else {
        map.on('load', onMapLoad)
    }
})

onUnmounted(() => { tracker.cleanup(); map?.remove(); map = null })

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

// ── Rendering ────────────────────────────────────────────────────────────────

function onClickElement(elId) {
    if (drawCreatePending.value) return
    const mode = draw?.getMode()
    if (mode && !['simple_select', 'direct_select'].includes(mode)) return
    selectedElementId.value = elId
    if (canEdit) {
        const el = elements.value.find(el => el.id === elId)
        if (el && !el.is_locked) {
            if (drawEditingId.value !== null && drawEditingId.value !== elId) exitDrawEdit()
            if (drawEditingId.value === null) enterDrawEdit(el)
        }
    }
}

function onClickEmpty() {
    selectedElementId.value = null
    exitDrawEdit()
}

function renderElements() {
    renderer.render(elements.value, drawEditingId.value, expandedGroupIds.value)
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
