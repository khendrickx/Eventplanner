// Two-click circle drawing mode for MapboxDraw.
// Click 1: set centre. Move: live preview. Click 2: confirm radius → Polygon.
// Uses @turf/turf to approximate the circle as a 64-point polygon.

import * as turf from '@turf/turf'

const DrawCircleMode = {
    onSetup() {
        const circle = this.newFeature({
            type: 'Feature',
            properties: {},
            geometry: { type: 'Polygon', coordinates: [[]] },
        })
        this.addFeature(circle)
        this.clearSelectedFeatures()
        this.setActionableState({ trash: true })
        return { circle, step: 0, center: null }
    },

    onClick(state, e) {
        if (state.step === 0) {
            state.center = [e.lngLat.lng, e.lngLat.lat]
            state.step = 1
        } else {
            const feat = state.circle.toGeoJSON()
            // Only fire create if we have a real polygon (more than just the closing point)
            if (feat.geometry.coordinates[0].length > 3) {
                this.map.fire('draw.create', { features: [feat] })
            }
            this.changeMode('simple_select')
        }
    },

    onMouseMove(state, e) {
        if (state.step !== 1) return
        const dist = turf.distance(
            turf.point(state.center),
            turf.point([e.lngLat.lng, e.lngLat.lat]),
            { units: 'kilometers' },
        )
        if (dist > 0) {
            const c = turf.circle(state.center, dist, { steps: 64, units: 'kilometers' })
            state.circle.incomingCoords(c.geometry.coordinates)
        }
    },

    onTrash(state) {
        this.deleteFeature([state.circle.id])
        this.changeMode('simple_select')
    },

    onKeyUp(state, e) {
        if (e.keyCode === 27) this.changeMode('simple_select')
    },

    toDisplayFeatures(state, geojson, display) {
        display(geojson)
    },
}

export default DrawCircleMode
