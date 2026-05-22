const DrawRectangleMode = {
    onSetup() {
        const rect = this.newFeature({
            type: 'Feature',
            geometry: { type: 'Polygon', coordinates: [[]] },
        })
        this.addFeature(rect)
        this.clearSelectedFeatures()
        this.setActionableState({ trash: true })
        return { rect, startPoint: null }
    },

    onClick(state, e) {
        if (!state.startPoint) {
            state.startPoint = [e.lngLat.lng, e.lngLat.lat]
            return
        }
        const [x1, y1] = state.startPoint
        const [x2, y2] = [e.lngLat.lng, e.lngLat.lat]
        if (x1 === x2 || y1 === y2) return  // degenerate rectangle — ignore
        state.rect.updateCoordinate('0.0', x1, y1)
        state.rect.updateCoordinate('0.1', x2, y1)
        state.rect.updateCoordinate('0.2', x2, y2)
        state.rect.updateCoordinate('0.3', x1, y2)
        state.rect.updateCoordinate('0.4', x1, y1)
        // MapboxDraw doesn't fire draw.create for custom modes, so we fire it manually.
        // changeMode('simple_select') does NOT re-emit draw.create, so there's no duplicate.
        this.map.fire('draw.create', { features: [state.rect.toGeoJSON()] })
        this.changeMode('simple_select', { featureIds: [state.rect.id] })
    },

    onMouseMove(state, e) {
        if (!state.startPoint) return
        const [x1, y1] = state.startPoint
        const [x2, y2] = [e.lngLat.lng, e.lngLat.lat]
        state.rect.updateCoordinate('0.0', x1, y1)
        state.rect.updateCoordinate('0.1', x2, y1)
        state.rect.updateCoordinate('0.2', x2, y2)
        state.rect.updateCoordinate('0.3', x1, y2)
        state.rect.updateCoordinate('0.4', x1, y1)
    },

    onTrash(state) {
        this.deleteFeature([state.rect.id])
        this.changeMode('simple_select')
    },

    toDisplayFeatures(state, geojson, display) {
        display(geojson)
    },
}

export default DrawRectangleMode
