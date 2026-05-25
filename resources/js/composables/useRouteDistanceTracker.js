import { ref } from 'vue'
import { length as turfLength } from '@turf/turf'

export function useRouteDistanceTracker(getMap, getDraw) {
    const routeDistanceLabel = ref(null)
    const routeLabelPos = ref({ x: 0, y: 0 })
    let routeMouseMoveListener = null
    let routeEditMoveListener = null

    function startRouteEditDistanceTracking(drawFeatureId) {
        const map = getMap()
        if (!map) return
        if (routeEditMoveListener) map.off('mousemove', routeEditMoveListener)
        routeEditMoveListener = (e) => {
            const draw = getDraw()
            if (!drawFeatureId || !draw) return
            const feat = draw.get(drawFeatureId)
            if (!feat || feat.geometry.type !== 'LineString') { routeDistanceLabel.value = null; return }
            const coords = feat.geometry.coordinates
            if (coords.length < 2) { routeDistanceLabel.value = null; return }
            const km = turfLength({ type: 'Feature', geometry: feat.geometry }, { units: 'kilometers' })
            routeDistanceLabel.value = km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(2)} km`
            routeLabelPos.value = { x: e.point.x, y: e.point.y }
        }
        map.on('mousemove', routeEditMoveListener)
        routeEditMoveListener()
    }

    function stopRouteEditDistanceTracking() {
        if (routeEditMoveListener) {
            getMap()?.off('mousemove', routeEditMoveListener)
            routeEditMoveListener = null
            routeDistanceLabel.value = null
        }
    }

    function startRouteDistanceTracking() {
        stopRouteDistanceTracking()
        const map = getMap()
        if (!map) return
        routeMouseMoveListener = (e) => {
            const draw = getDraw()
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
            getMap()?.off('mousemove', routeMouseMoveListener)
            routeMouseMoveListener = null
        }
        routeDistanceLabel.value = null
    }

    function cleanup() {
        stopRouteDistanceTracking()
        stopRouteEditDistanceTracking()
    }

    return {
        routeDistanceLabel,
        routeLabelPos,
        startRouteDistanceTracking,
        stopRouteDistanceTracking,
        startRouteEditDistanceTracking,
        stopRouteEditDistanceTracking,
        cleanup,
    }
}
