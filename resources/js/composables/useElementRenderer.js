import { elementTypesBySubtype } from '@/config/elementTypes.js'

const CLICKABLE_LAYERS = [
    'elements-symbol',
    'elements-text-label',
    'elements-fill',
    'elements-line',
    'elements-line-dashed',
    'elements-line-dotted',
]

export function useElementRenderer(getMap) {
    function initLayers(onClickElement, onClickEmpty) {
        const map = getMap()

        map.addSource('elements', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: [] },
        })

        map.addLayer({
            id: 'elements-fill',
            type: 'fill',
            source: 'elements',
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'fill-color': ['get', 'fillColor'],
                'fill-opacity': ['get', 'opacity'],
            },
        })

        map.addLayer({
            id: 'elements-line',
            type: 'line',
            source: 'elements',
            filter: ['all',
                ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
                ['!', ['in', ['get', 'strokeType'], ['literal', ['dashed', 'dotted']]]],
            ],
            paint: {
                'line-color': ['get', 'strokeColor'],
                'line-width': ['get', 'lineWidth'],
            },
        })

        map.addLayer({
            id: 'elements-line-dashed',
            type: 'line',
            source: 'elements',
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

        map.addLayer({
            id: 'elements-line-dotted',
            type: 'line',
            source: 'elements',
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

        map.addLayer({
            id: 'elements-symbol',
            type: 'symbol',
            source: 'elements',
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

        map.addLayer({
            id: 'elements-text-label',
            type: 'symbol',
            source: 'elements',
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

        let _elementClickedThisFrame = false
        for (const layerId of CLICKABLE_LAYERS) {
            map.on('click', layerId, (e) => {
                        _elementClickedThisFrame = true
                onClickElement(e.features[0].properties._elId)
            })
        }
        map.on('click', () => {
            if (_elementClickedThisFrame) { _elementClickedThisFrame = false; return }
            onClickEmpty()
        })
    }

    function render(elements, drawEditingId) {
        const map = getMap()
        if (!map?.getSource('elements')) return

        const groupsById = Object.fromEntries(
            elements.filter(el => el.type === 'group').map(el => [el.id, el])
        )

        const visibleEls = elements.filter(el => {
            if (el.type === 'group') return false
            if (el.id === drawEditingId) return false
            if (el.is_hidden) return false
            if (el.parent_id != null) {
                const parent = groupsById[el.parent_id]
                return parent && !parent.is_hidden
            }
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

        map.getSource('elements').setData(geojson)
    }

    return { initLayers, render }
}
