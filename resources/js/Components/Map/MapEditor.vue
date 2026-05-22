<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import maplibregl from 'maplibre-gl'
import MapboxDraw from '@mapbox/mapbox-gl-draw'
import 'maplibre-gl/dist/maplibre-gl.css'
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css'
import { mapLayers } from '@/config/mapLayers.js'
import { elementTypesBySubtype } from '@/config/elementTypes.js'
import { useUndoStack } from '@/composables/useUndoStack.js'
import { useMapElements } from '@/composables/useMapElements.js'
import DrawRectangleMode from './draw-modes/DrawRectangleMode.js'
import LayerSwitcher from './LayerSwitcher.vue'
import DrawToolbar from './DrawToolbar.vue'
import PlanSwitcher from './PlanSwitcher.vue'
import ElementSidebar from './ElementSidebar.vue'
import PropertiesPanel from './PropertiesPanel.vue'

const props = defineProps({
    event: { type: Object, required: true },
    initialPlans: { type: Array, required: true },
})

const mapContainer = ref(null)
let map = null
let draw = null

const plans = ref([...props.initialPlans])
const activePlanId = ref(props.initialPlans[0]?.id)
const activeLayerId = ref(mapLayers[0].id)
const selectedElementId = ref(null)
const canEdit = props.event.role !== 'viewer'

const { push: pushUndo, undo, canUndo } = useUndoStack()
const { elements, saving, load, create, update, remove } = useMapElements(props.event.id, activePlanId)

const selectedElement = () => elements.value.find(e => e.id === selectedElementId.value) ?? null

// ── Map initialization ──────────────────────────────────────────────────────

onMounted(async () => {
    const layer = mapLayers.find(l => l.id === activeLayerId.value)

    map = new maplibregl.Map({
        container: mapContainer.value,
        style: layer.type === 'vector-style' ? layer.url : buildRasterStyle(layer),
        center: [4.35, 50.85],
        zoom: 13,
    })

    map.addControl(new maplibregl.NavigationControl(), 'bottom-right')

    if (canEdit) {
        draw = new MapboxDraw({
            displayControlsDefault: false,
            controls: {},
            modes: {
                ...MapboxDraw.modes,
                draw_rectangle: DrawRectangleMode,
            },
        })
        map.addControl(draw)

        map.on('draw.create', onDrawCreate)
        map.on('draw.update', onDrawUpdate)
        map.on('draw.delete', onDrawDelete)
        map.on('draw.selectionchange', onDrawSelection)
    }

    map.on('load', async () => {
        await load()
        renderElements()
    })
})

onUnmounted(() => map?.remove())

// ── Layer switching ──────────────────────────────────────────────────────────

function buildRasterStyle(layer) {
    return {
        version: 8,
        sources: {
            raster: {
                type: 'raster',
                tiles: [layer.tileUrlTemplate],
                tileSize: 256,
                attribution: layer.attribution,
            },
        },
        layers: [{ id: 'raster-layer', type: 'raster', source: 'raster' }],
    }
}

async function switchLayer(layerId) {
    activeLayerId.value = layerId
    const layer = mapLayers.find(l => l.id === layerId)
    const style = layer.type === 'vector-style' ? layer.url : buildRasterStyle(layer)
    map.setStyle(style)
    map.once('styledata', () => renderElements())
}

// ── Plan switching ───────────────────────────────────────────────────────────

async function switchPlan(planId) {
    activePlanId.value = planId
    selectedElementId.value = null
    await load()
    renderElements()
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
    if (activePlanId.value === id) switchPlan(plans.value[0].id)
}

// ── Drawing ──────────────────────────────────────────────────────────────────

let pendingSubtype = null
let pendingType = null

function startDraw({ mode, subtype }) {
    pendingSubtype = subtype
    pendingType = mode

    const drawMode = mode === 'marker'
        ? 'draw_point'
        : mode === 'route'
            ? 'draw_line_string'
            : mode === 'infrastructure'
                ? 'draw_rectangle'
                : 'draw_polygon'

    draw.changeMode(drawMode)
}

async function onDrawCreate(e) {
    const feature = e.features[0]
    const typeDef = pendingSubtype ? elementTypesBySubtype[pendingSubtype] : null
    const defaultStyle = typeDef?.defaultStyle || {}

    const newEl = await create({
        type: pendingType || featureType(feature),
        subtype: pendingSubtype,
        geometry: feature.geometry,
        properties: { styling: defaultStyle },
    })

    draw.delete(feature.id)
    renderElements()
    selectedElementId.value = newEl.id

    pushUndo(async () => {
        await remove(newEl.id)
        renderElements()
        selectedElementId.value = null
    })
}

async function onDrawUpdate(e) {
    const feature = e.features[0]
    const el = elements.value.find(el => el.id === feature.properties._elId)
    if (!el) return

    const prev = { ...el }
    await update(el.id, { geometry: feature.geometry })
    renderElements()

    pushUndo(async () => {
        await update(prev.id, { geometry: prev.geometry })
        renderElements()
    })
}

async function onDrawDelete(e) {
    const feature = e.features[0]
    const el = elements.value.find(el => el.id === feature.properties._elId)
    if (!el) return

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
        const elId = e.features[0].properties._elId
        selectedElementId.value = elId ?? null
    } else {
        selectedElementId.value = null
    }
}

function featureType(feature) {
    const typeMap = { Point: 'marker', LineString: 'route', Polygon: 'zone' }
    return typeMap[feature.geometry.type] || 'marker'
}

// ── Rendering ────────────────────────────────────────────────────────────────

function renderElements() {
    if (!map.isStyleLoaded()) return

    const sourceId = 'elements'
    const visibleEls = elements.value.filter(el => !el.is_hidden)

    const geojson = {
        type: 'FeatureCollection',
        features: visibleEls.map(el => ({
            type: 'Feature',
            id: el.id,
            properties: {
                _elId: el.id,
                type: el.type,
                subtype: el.subtype,
                fillColor: el.properties?.styling?.fill_color || elementTypesBySubtype[el.subtype]?.defaultStyle?.color || '#3b82f6',
                strokeColor: el.properties?.styling?.stroke_color || '#1d4ed8',
                opacity: el.properties?.styling?.opacity ?? 0.4,
                lineWidth: elementTypesBySubtype[el.subtype]?.defaultStyle?.width || 2,
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
        map.addLayer({
            id: 'elements-line',
            type: 'line',
            source: sourceId,
            filter: ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
            paint: {
                'line-color': ['get', 'strokeColor'],
                'line-width': ['get', 'lineWidth'],
            },
        })
        map.addLayer({
            id: 'elements-circle',
            type: 'circle',
            source: sourceId,
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-radius': 8,
                'circle-color': ['get', 'fillColor'],
                'circle-stroke-width': 2,
                'circle-stroke-color': ['get', 'strokeColor'],
            },
        })

        map.on('click', 'elements-circle', e => {
            selectedElementId.value = e.features[0].properties._elId
        })
        map.on('click', 'elements-fill', e => {
            selectedElementId.value = e.features[0].properties._elId
        })
    }
}

// ── Element updates from PropertiesPanel ─────────────────────────────────────

async function handleUpdate(payload) {
    const { id, ...fields } = payload
    const prev = elements.value.find(e => e.id === id)
    const prevSnapshot = { ...prev }

    await update(id, fields)
    renderElements()

    pushUndo(async () => {
        await update(prevSnapshot.id, Object.fromEntries(
            Object.keys(fields).map(k => [k, prevSnapshot[k]])
        ))
        renderElements()
    })
}

async function handleToggleLock(el) {
    await handleUpdate({ id: el.id, is_locked: !el.is_locked })
}

async function handleToggleHide(el) {
    await handleUpdate({ id: el.id, is_hidden: !el.is_hidden })
    renderElements()
}

async function handleUndo() {
    await undo()
}

// ── Keyboard shortcut ────────────────────────────────────────────────────────

function resetNorth() {
    map?.resetNorth()
}

function onKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && canEdit) {
        e.preventDefault()
        handleUndo()
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
            <LayerSwitcher :active-layer-id="activeLayerId" @change="switchLayer" />
            <div class="w-px h-5 bg-gray-200 shrink-0"></div>
            <DrawToolbar v-if="canEdit" @draw="startDraw" />
            <div class="w-px h-5 bg-gray-200 shrink-0"></div>
            <button
                v-if="canEdit"
                :disabled="!canUndo()"
                @click="handleUndo"
                class="text-xs border rounded px-2 py-1.5 disabled:opacity-40 hover:bg-gray-50"
                title="Undo (Ctrl+Z)"
            >&#x21A9; Undo</button>
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
                @select="id => selectedElementId = id"
                @toggle-lock="handleToggleLock"
                @toggle-hide="handleToggleHide"
            />
            <div ref="mapContainer" class="flex-1" />
            <PropertiesPanel
                :element="selectedElement()"
                :plans="plans"
                :can-edit="canEdit"
                @update="handleUpdate"
            />
        </div>
    </div>
</template>
