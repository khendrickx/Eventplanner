/**
 * Compute the geographic bounding box from an array of map elements.
 * Returns null if elements is empty or contains no usable coordinates.
 * Returns [[minLng, minLat], [maxLng, maxLat]] suitable for maplibre fitBounds.
 */
export function computeBoundsFromElements(elements) {
    let minLng = Infinity, minLat = Infinity
    let maxLng = -Infinity, maxLat = -Infinity
    let hasCoords = false

    for (const el of elements) {
        if (!el.geometry) continue
        for (const [lng, lat] of extractCoordinates(el.geometry)) {
            if (lng < minLng) minLng = lng
            if (lat < minLat) minLat = lat
            if (lng > maxLng) maxLng = lng
            if (lat > maxLat) maxLat = lat
            hasCoords = true
        }
    }

    if (!hasCoords) return null
    return [[minLng, minLat], [maxLng, maxLat]]
}

function extractCoordinates(geometry) {
    switch (geometry.type) {
        case 'Point':
            return [geometry.coordinates]
        case 'LineString':
            return geometry.coordinates
        case 'Polygon':
        case 'MultiLineString':
            return geometry.coordinates.flat()
        case 'MultiPolygon':
            return geometry.coordinates.flat(2)
        case 'MultiPoint':
            return geometry.coordinates
        default:
            return []
    }
}
