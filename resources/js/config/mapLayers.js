// resources/js/config/mapLayers.js

export const mapLayers = [
    {
        id: 'osm',
        label: 'OpenStreetMap',
        type: 'vector-style',
        url: 'https://tiles.openfreemap.org/styles/liberty',
        attribution: '© OpenStreetMap contributors',
    },
    {
        id: 'vlaanderen-satellite',
        label: 'Vlaanderen Satellite',
        type: 'wmts',
        url: 'https://geo.api.vlaanderen.be/OMW/wmts',
        layer: 'omwrgb25vl',
        tilematrixset: 'BPL2008VL',
        format: 'image/png',
        attribution: '© Agentschap Informatie Vlaanderen',
        tileUrlTemplate:
            'https://geo.api.vlaanderen.be/OMW/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0' +
            '&LAYER=omwrgb25vl&STYLE=&TILEMATRIXSET=BPL2008VL&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}&FORMAT=image%2Fpng',
    },
]
