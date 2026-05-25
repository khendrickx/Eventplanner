import { ref } from 'vue'
import { elementTypesBySubtype } from '@/config/elementTypes.js'

/**
 * Owns all mutable draw state and the event handlers wired to MapboxDraw.
 *
 * Caller is responsible for registering the returned event handlers:
 *   map.on('draw.create',        onDrawCreate)
 *   map.on('draw.update',        onDrawUpdate)
 *   map.on('draw.delete',        onDrawDelete)
 *   map.on('draw.selectionchange', onDrawSelection)
 *   map.on('draw.modechange',    onModeChange)
 */
export function useDrawStateMachine({
    getDraw,
    elements,
    expandedGroupIds,
    selectedElementId,
    renderElements,
    create,
    update,
    remove,
    pushUndo,
    routeTracker,
}) {
    const drawEditingId = ref(null)
    let drawEditingDrawId = null
    let prevDrawMode = null
    let pendingSubtype = null
    let pendingType = null
    const drawCreatePending = ref(false)

    function featureTypeFromGeometry(geometry) {
        const map = { Point: 'marker', LineString: 'route', Polygon: 'zone' }
        return map[geometry.type] || 'marker'
    }

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

        getDraw().changeMode(drawMode)
        if (mode === 'route') routeTracker.startRouteDistanceTracking()
    }

    async function onDrawCreate(e) {
        drawCreatePending.value = true
        routeTracker.stopRouteDistanceTracking()

        const feature = e.features[0]
        const subtypeKey = pendingSubtype || (pendingType === 'group' ? 'group' : null)
        const typeDef = subtypeKey ? elementTypesBySubtype[subtypeKey] : null
        const defaultStyle = typeDef?.defaultStyle || {}

        const activeEl = selectedElementId.value !== null
            ? (elements.value.find(el => el.id === selectedElementId.value) ?? null)
            : null
        const parentId = (activeEl?.type === 'group') ? activeEl.id : null

        const newEl = await create({
            type: pendingType || featureTypeFromGeometry(feature.geometry),
            subtype: pendingSubtype || null,
            geometry: feature.geometry,
            properties: { styling: defaultStyle },
            parent_id: parentId,
        })

        getDraw().delete(feature.id)
        drawCreatePending.value = false

        if (parentId && !expandedGroupIds.value.includes(parentId)) {
            expandedGroupIds.value = [...expandedGroupIds.value, parentId]
        }

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
        const elId = Number(feature.properties._elId)
        const idx = elements.value.findIndex(el => el.id === elId)
        if (idx === -1) return

        const prev = { ...elements.value[idx] }
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
        routeTracker.stopRouteDistanceTracking()
        const feature = e.features[0]
        const el = elements.value.find(el => el.id === feature.properties._elId)
        if (!el) return

        drawEditingId.value = null
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
            selectedElementId.value = Number(e.features[0].properties._elId) || null
        } else {
            if (getDraw()?.getMode() !== 'direct_select') {
                selectedElementId.value = null
                exitDrawEdit()
            }
        }
    }

    function onModeChange(e) {
        if (prevDrawMode === 'direct_select' && e.mode === 'simple_select' && drawEditingId.value !== null) {
            exitDrawEdit()
        }
        prevDrawMode = e.mode
    }

    function enterDrawEdit(el) {
        const [drawFeatureId] = getDraw().add({
            type: 'Feature',
            properties: { _elId: el.id },
            geometry: el.geometry,
        })
        drawEditingId.value = el.id
        drawEditingDrawId = drawFeatureId
        renderElements()
        // Defer mode change to avoid MapboxDraw re-processing the originating click
        // in the new mode, which throws an internal error.
        setTimeout(() => {
            if (drawEditingId.value !== el.id) return
            try {
                if (el.geometry.type === 'Point') {
                    getDraw().changeMode('simple_select', { featureIds: [drawFeatureId] })
                } else if (el.geometry.type === 'LineString') {
                    getDraw().changeMode('direct_select', { featureId: drawFeatureId })
                    routeTracker.startRouteEditDistanceTracking(drawFeatureId)
                } else {
                    getDraw().changeMode('direct_select', { featureId: drawFeatureId })
                }
            } catch { /* benign — see comment above */ }
        }, 0)
    }

    function exitDrawEdit() {
        if (drawEditingId.value === null) return
        routeTracker.stopRouteEditDistanceTracking()
        const exitingId = drawEditingId.value

        // Safety net: sync any geometry change that onDrawUpdate may have missed.
        if (drawEditingDrawId !== null) {
            const drawFeature = getDraw().get(drawEditingDrawId)
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

        drawEditingId.value = null
        getDraw().deleteAll()
        renderElements()
    }

    return {
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
    }
}
