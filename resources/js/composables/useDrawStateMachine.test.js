import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useDrawStateMachine } from './useDrawStateMachine.js'

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeDraw(overrides = {}) {
    return {
        add: vi.fn(() => ['draw-id-1']),
        delete: vi.fn(),
        deleteAll: vi.fn(),
        changeMode: vi.fn(),
        getMode: vi.fn(() => 'simple_select'),
        get: vi.fn(() => null),
        getAll: vi.fn(() => ({ features: [] })),
        ...overrides,
    }
}

function makeRouteTracker() {
    return {
        startRouteDistanceTracking: vi.fn(),
        stopRouteDistanceTracking: vi.fn(),
        startRouteEditDistanceTracking: vi.fn(),
        stopRouteEditDistanceTracking: vi.fn(),
    }
}

function makeCtx(overrides = {}) {
    const draw = makeDraw()
    const elements = ref([])
    const expandedGroupIds = ref([])
    const selectedElementId = ref(null)
    const renderElements = vi.fn()
    const create = vi.fn()
    const update = vi.fn()
    const remove = vi.fn()
    const pushUndo = vi.fn()
    const routeTracker = makeRouteTracker()

    const machine = useDrawStateMachine({
        getDraw: () => draw,
        elements,
        expandedGroupIds,
        selectedElementId,
        renderElements,
        create, update, remove,
        pushUndo,
        routeTracker,
        ...overrides,
    })

    return { draw, elements, expandedGroupIds, selectedElementId, renderElements, create, update, remove, pushUndo, routeTracker, machine }
}

// ── enterDrawEdit ─────────────────────────────────────────────────────────────

describe('enterDrawEdit', () => {
    it('sets drawEditingId and calls draw.add + renderElements', () => {
        const { draw, elements, machine, renderElements } = makeCtx()
        const el = { id: 1, geometry: { type: 'Point', coordinates: [4, 51] } }
        elements.value = [el]

        machine.enterDrawEdit(el)

        expect(draw.add).toHaveBeenCalledWith(expect.objectContaining({
            type: 'Feature',
            properties: { _elId: 1 },
            geometry: el.geometry,
        }))
        expect(machine.drawEditingId.value).toBe(1)
        expect(renderElements).toHaveBeenCalledOnce()
    })

    it('defers changeMode to simple_select for Points', async () => {
        vi.useFakeTimers()
        const { draw, machine } = makeCtx()
        const el = { id: 2, geometry: { type: 'Point', coordinates: [4, 51] } }

        machine.enterDrawEdit(el)
        vi.runAllTimers()

        expect(draw.changeMode).toHaveBeenCalledWith('simple_select', { featureIds: ['draw-id-1'] })
        vi.useRealTimers()
    })

    it('defers changeMode to direct_select for LineStrings and starts edit tracking', async () => {
        vi.useFakeTimers()
        const { draw, machine, routeTracker } = makeCtx()
        const el = { id: 3, geometry: { type: 'LineString', coordinates: [[0, 0], [1, 1]] } }

        machine.enterDrawEdit(el)
        vi.runAllTimers()

        expect(draw.changeMode).toHaveBeenCalledWith('direct_select', { featureId: 'draw-id-1' })
        expect(routeTracker.startRouteEditDistanceTracking).toHaveBeenCalledWith('draw-id-1')
        vi.useRealTimers()
    })

    it('defers changeMode to direct_select for Polygons', async () => {
        vi.useFakeTimers()
        const { draw, machine } = makeCtx()
        const el = { id: 4, geometry: { type: 'Polygon', coordinates: [[[0,0],[1,0],[1,1],[0,0]]] } }

        machine.enterDrawEdit(el)
        vi.runAllTimers()

        expect(draw.changeMode).toHaveBeenCalledWith('direct_select', { featureId: 'draw-id-1' })
        vi.useRealTimers()
    })

    it('aborts deferred mode change if exitDrawEdit was called first', () => {
        vi.useFakeTimers()
        const { draw, machine } = makeCtx()
        const el = { id: 5, geometry: { type: 'Point', coordinates: [4, 51] } }
        draw.get.mockReturnValue(null)

        machine.enterDrawEdit(el)
        machine.exitDrawEdit()        // clears drawEditingId before timer fires
        vi.runAllTimers()

        expect(draw.changeMode).not.toHaveBeenCalled()
        vi.useRealTimers()
    })
})

// ── exitDrawEdit ──────────────────────────────────────────────────────────────

describe('exitDrawEdit', () => {
    it('is a no-op when not editing', () => {
        const { draw, machine, renderElements, routeTracker } = makeCtx()
        machine.exitDrawEdit()
        expect(draw.deleteAll).not.toHaveBeenCalled()
        expect(renderElements).not.toHaveBeenCalled()
        expect(routeTracker.stopRouteEditDistanceTracking).not.toHaveBeenCalled()
    })

    it('clears drawEditingId, calls deleteAll and renderElements', () => {
        const { draw, elements, machine, renderElements, routeTracker } = makeCtx()
        const el = { id: 1, geometry: { type: 'Point', coordinates: [4, 51] } }
        elements.value = [el]
        draw.get.mockReturnValue(null)

        machine.enterDrawEdit(el)
        renderElements.mockClear()
        machine.exitDrawEdit()

        expect(machine.drawEditingId.value).toBeNull()
        expect(draw.deleteAll).toHaveBeenCalledOnce()
        expect(renderElements).toHaveBeenCalledOnce()
        expect(routeTracker.stopRouteEditDistanceTracking).toHaveBeenCalledOnce()
    })

    it('syncs geometry back when the draw feature has changed', () => {
        const { draw, elements, machine, update } = makeCtx()
        const original = { type: 'Point', coordinates: [4, 51] }
        const moved = { type: 'Point', coordinates: [5, 52] }
        const el = { id: 1, geometry: original }
        elements.value = [el]

        // Simulate: enterDrawEdit stores drawEditingDrawId, then draw.get returns changed geometry
        draw.get.mockReturnValue({ geometry: moved })
        machine.enterDrawEdit(el)
        machine.exitDrawEdit()

        expect(elements.value[0].geometry).toEqual(moved)
        expect(update).toHaveBeenCalledWith(1, { geometry: moved })
    })

    it('does not call update when geometry is unchanged', () => {
        const { draw, elements, machine, update } = makeCtx()
        const geo = { type: 'Point', coordinates: [4, 51] }
        const el = { id: 1, geometry: geo }
        elements.value = [el]

        draw.get.mockReturnValue({ geometry: geo })
        machine.enterDrawEdit(el)
        machine.exitDrawEdit()

        expect(update).not.toHaveBeenCalled()
    })
})

// ── onDrawUpdate ──────────────────────────────────────────────────────────────

describe('onDrawUpdate', () => {
    it('does optimistic update on elements and calls renderElements', async () => {
        const { elements, machine, renderElements, update } = makeCtx()
        const originalGeo = { type: 'Point', coordinates: [4, 51] }
        const newGeo = { type: 'Point', coordinates: [5, 52] }
        elements.value = [{ id: 7, geometry: originalGeo }]
        update.mockResolvedValue({})

        await machine.onDrawUpdate({
            features: [{ properties: { _elId: 7 }, geometry: newGeo }],
        })

        expect(elements.value[0].geometry).toEqual(newGeo)
        expect(renderElements).toHaveBeenCalled()
        expect(update).toHaveBeenCalledWith(7, { geometry: newGeo })
    })

    it('is a no-op when element id is not found', async () => {
        const { elements, machine, update, renderElements } = makeCtx()
        elements.value = []

        await machine.onDrawUpdate({
            features: [{ properties: { _elId: 99 }, geometry: { type: 'Point', coordinates: [0, 0] } }],
        })

        expect(update).not.toHaveBeenCalled()
        expect(renderElements).not.toHaveBeenCalled()
    })

    it('coerces string _elId to number', async () => {
        const { elements, machine, update } = makeCtx()
        elements.value = [{ id: 3, geometry: { type: 'Point', coordinates: [0, 0] } }]
        update.mockResolvedValue({})

        await machine.onDrawUpdate({
            features: [{ properties: { _elId: '3' }, geometry: { type: 'Point', coordinates: [1, 1] } }],
        })

        expect(update).toHaveBeenCalledWith(3, expect.anything())
    })

    it('pushes an undo operation', async () => {
        const { elements, machine, pushUndo, update } = makeCtx()
        elements.value = [{ id: 1, geometry: { type: 'Point', coordinates: [0, 0] } }]
        update.mockResolvedValue({})

        await machine.onDrawUpdate({
            features: [{ properties: { _elId: 1 }, geometry: { type: 'Point', coordinates: [1, 1] } }],
        })

        expect(pushUndo).toHaveBeenCalledOnce()
    })
})

// ── onDrawSelection ───────────────────────────────────────────────────────────

describe('onDrawSelection', () => {
    it('sets selectedElementId when features are present', () => {
        const { machine, selectedElementId } = makeCtx()
        machine.onDrawSelection({ features: [{ properties: { _elId: 42 } }] })
        expect(selectedElementId.value).toBe(42)
    })

    it('calls exitDrawEdit and clears selection when selection is empty and not in direct_select', () => {
        const { draw, machine, selectedElementId, renderElements } = makeCtx()
        draw.getMode.mockReturnValue('simple_select')

        machine.onDrawSelection({ features: [{ properties: { _elId: 1 } }] })
        machine.onDrawSelection({ features: [] })

        expect(selectedElementId.value).toBeNull()
    })

    it('does NOT call exitDrawEdit when in direct_select mode', () => {
        const { draw, elements, machine, renderElements } = makeCtx()
        const el = { id: 1, geometry: { type: 'Polygon', coordinates: [[[0,0],[1,0],[1,1],[0,0]]] } }
        elements.value = [el]
        draw.get.mockReturnValue(null)
        draw.getMode.mockReturnValue('direct_select')

        machine.enterDrawEdit(el)
        renderElements.mockClear()
        machine.onDrawSelection({ features: [] })

        // drawEditingId should remain set — not exited
        expect(machine.drawEditingId.value).toBe(1)
        expect(renderElements).not.toHaveBeenCalled()
    })
})

// ── onModeChange ──────────────────────────────────────────────────────────────

describe('onModeChange', () => {
    it('calls exitDrawEdit when transitioning from direct_select to simple_select while editing', () => {
        const { draw, elements, machine, renderElements } = makeCtx()
        const el = { id: 1, geometry: { type: 'Polygon', coordinates: [[[0,0],[1,0],[1,1],[0,0]]] } }
        elements.value = [el]
        draw.get.mockReturnValue(null)

        machine.enterDrawEdit(el)
        renderElements.mockClear()

        machine.onModeChange({ mode: 'direct_select' })   // enters direct_select
        machine.onModeChange({ mode: 'simple_select' })   // exits → should trigger exitDrawEdit

        expect(machine.drawEditingId.value).toBeNull()
        expect(renderElements).toHaveBeenCalled()
    })

    it('does not exit when transitioning to direct_select', () => {
        const { draw, elements, machine } = makeCtx()
        const el = { id: 1, geometry: { type: 'Polygon', coordinates: [[[0,0],[1,0],[1,1],[0,0]]] } }
        elements.value = [el]

        machine.enterDrawEdit(el)
        machine.onModeChange({ mode: 'direct_select' })

        expect(machine.drawEditingId.value).toBe(1)
    })
})

// ── onDrawCreate ──────────────────────────────────────────────────────────────

describe('onDrawCreate', () => {
    it('creates an element, deletes the draw feature, renders, and pushes undo', async () => {
        const { draw, machine, create, pushUndo, renderElements, selectedElementId } = makeCtx()
        const newEl = { id: 10, type: 'marker' }
        create.mockResolvedValue(newEl)

        await machine.onDrawCreate({
            features: [{ id: 'draw-temp', geometry: { type: 'Point', coordinates: [4, 51] }, properties: {} }],
        })

        expect(create).toHaveBeenCalledOnce()
        expect(draw.delete).toHaveBeenCalledWith('draw-temp')
        expect(machine.drawCreatePending.value).toBe(false)
        expect(renderElements).toHaveBeenCalled()
        expect(selectedElementId.value).toBe(10)
        expect(pushUndo).toHaveBeenCalledOnce()
    })

    it('assigns parentId when a group element is selected', async () => {
        const { machine, create, selectedElementId, elements } = makeCtx()
        const group = { id: 5, type: 'group' }
        elements.value = [group]
        selectedElementId.value = 5
        create.mockResolvedValue({ id: 11, type: 'marker' })

        await machine.onDrawCreate({
            features: [{ id: 'tmp', geometry: { type: 'Point', coordinates: [0, 0] }, properties: {} }],
        })

        expect(create).toHaveBeenCalledWith(expect.objectContaining({ parent_id: 5 }))
    })

    it('expands parent group if not already expanded', async () => {
        const { machine, create, selectedElementId, elements, expandedGroupIds } = makeCtx()
        const group = { id: 5, type: 'group' }
        elements.value = [group]
        selectedElementId.value = 5
        create.mockResolvedValue({ id: 11 })

        await machine.onDrawCreate({
            features: [{ id: 'tmp', geometry: { type: 'Point', coordinates: [0, 0] }, properties: {} }],
        })

        expect(expandedGroupIds.value).toContain(5)
    })

    it('infers element type from geometry when pendingType is not set', async () => {
        const { machine, create } = makeCtx()
        create.mockResolvedValue({ id: 20 })

        await machine.onDrawCreate({
            features: [{ id: 'tmp', geometry: { type: 'LineString', coordinates: [[0,0],[1,1]] }, properties: {} }],
        })

        expect(create).toHaveBeenCalledWith(expect.objectContaining({ type: 'route' }))
    })
})
