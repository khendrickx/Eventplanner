/**
 * Central map layer registry.
 *
 * To add a new layer:
 *   1. Append an entry to this array.
 *   2. Set `type` to one of:
 *        'vector-style'  – a MapLibre GL style JSON URL (supports OSM vector tiles, etc.)
 *        'wmts'          – a WMTS raster tile service; fill in `tileUrlTemplate` with {z}/{y}/{x}
 *        'xyz'           – plain XYZ raster tiles; fill in `tileUrlTemplate` with {z}/{y}/{x}
 *   3. Set `crs` to the coordinate reference system (informational, no runtime effect for EPSG:3857).
 *      Use 'EPSG:3857' for standard Web Mercator (most web tile services).
 *   4. Provide a `thumbnail` URL (a representative 256×256 tile) for the layer picker.
 *
 * No other files need to change — the LayerSwitcher and MapEditor pick up new entries automatically.
 */

// Tile coords for a representative Brussels area preview (z=12, x=2098, y=1372)
const _BXL = { z: 12, x: 2098, y: 1372 }

export const mapLayers = [
    {
        id: 'osm',
        label: 'Streets (OSM)',
        type: 'vector-style',
        url: 'https://tiles.openfreemap.org/styles/liberty',
        attribution: '© OpenStreetMap contributors',
        crs: 'EPSG:3857',
        thumbnail: `https://tile.openstreetmap.org/${_BXL.z}/${_BXL.x}/${_BXL.y}.png`,
    },
    {
        id: 'vlaanderen-satellite',
        label: 'Satellite (Flanders 2025)',
        type: 'wmts',
        layer: 'omwrgb25vl',
        tilematrixset: 'GoogleMapsVL',
        format: 'image/png',
        attribution: '© Agentschap Informatie Vlaanderen',
        crs: 'EPSG:3857',
        // {z}/{y}/{x} are MapLibre tile-URL placeholders, substituted at tile-fetch time
        tileUrlTemplate:
            'https://geo.api.vlaanderen.be/OMW/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0' +
            '&LAYER=omwrgb25vl&STYLE=&TILEMATRIXSET=GoogleMapsVL&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}&FORMAT=image%2Fpng',
        thumbnail:
            `https://geo.api.vlaanderen.be/OMW/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0` +
            `&LAYER=omwrgb25vl&STYLE=&TILEMATRIXSET=GoogleMapsVL&TILEMATRIX=${_BXL.z}&TILEROW=${_BXL.y}&TILECOL=${_BXL.x}&FORMAT=image%2Fpng`,
    },
]
