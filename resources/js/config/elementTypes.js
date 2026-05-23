// resources/js/config/elementTypes.js

export const elementTypes = [
    // Groups
    { id: 'group',           type: 'group',          label: 'Group',            defaultStyle: { color: '#6366f1', opacity: 0.12 } },

    // Routes
    { id: 'course',           type: 'route',          label: 'Course',           defaultStyle: { color: '#e74c3c', width: 4 } },
    { id: 'pedestrian_route', type: 'route',          label: 'Pedestrian Route', defaultStyle: { color: '#3498db', width: 2 } },
    { id: 'vehicle_route',    type: 'route',          label: 'Vehicle Route',    defaultStyle: { color: '#f39c12', width: 2 } },
    { id: 'barrier',          type: 'route',          label: 'Barrier',          defaultStyle: { color: '#e67e22', width: 3 } },
    { id: 'fence',            type: 'route',          label: 'Fence',            defaultStyle: { color: '#95a5a6', width: 2 } },

    // Markers
    { id: 'buoy',             type: 'marker',         label: 'Buoy',             icon: '🔵', defaultStyle: { color: '#2980b9' } },
    { id: 'start',            type: 'marker',         label: 'Start',            icon: '🟢', defaultStyle: { color: '#27ae60' } },
    { id: 'finish',           type: 'marker',         label: 'Finish',           icon: '🏁', defaultStyle: { color: '#c0392b' } },
    { id: 'checkpoint',       type: 'marker',         label: 'Checkpoint',       icon: '🔷', defaultStyle: { color: '#8e44ad' } },
    { id: 'aid_station',      type: 'marker',         label: 'Aid Station',      icon: '🍊', defaultStyle: { color: '#e67e22' } },
    { id: 'medical',          type: 'marker',         label: 'Medical',          icon: '🏥', defaultStyle: { color: '#e74c3c' } },
    { id: 'hazard',           type: 'marker',         label: 'Hazard',           icon: '⚠️', defaultStyle: { color: '#f39c12' } },
    { id: 'electricity',      type: 'marker',         label: 'Electricity',      icon: '⚡', defaultStyle: { color: '#f1c40f' } },
    { id: 'transition_zone',  type: 'marker',         label: 'Transition',       icon: '🔄', defaultStyle: { color: '#1abc9c' } },
    { id: 'timing_mat',       type: 'marker',         label: 'Timing Mat',       icon: '⏱', defaultStyle: { color: '#34495e' } },
    { id: 'spectator_area',   type: 'marker',         label: 'Spectator Area',   icon: '👥', defaultStyle: { color: '#16a085' } },
    { id: 'bag_drop',         type: 'marker',         label: 'Bag Drop',         icon: '🎒', defaultStyle: { color: '#7f8c8d' } },
    { id: 'feed_zone',        type: 'marker',         label: 'Feed Zone',        icon: '🍌', defaultStyle: { color: '#d35400' } },

    // Zones
    { id: 'restricted_area',  type: 'zone',           label: 'Restricted Area',  defaultStyle: { color: '#e74c3c', opacity: 0.2 } },
    { id: 'parking_zone',     type: 'zone',           label: 'Parking Zone',     defaultStyle: { color: '#95a5a6', opacity: 0.3 } },
    { id: 'transition_zone',  type: 'zone',           label: 'Transition Zone',  defaultStyle: { color: '#1abc9c', opacity: 0.3 } },
    { id: 'start_zone',       type: 'zone',           label: 'Start Zone',       defaultStyle: { color: '#27ae60', opacity: 0.3 } },
    { id: 'finish_area',      type: 'zone',           label: 'Finish Area',      defaultStyle: { color: '#c0392b', opacity: 0.3 } },
    { id: 'spectator_zone',   type: 'zone',           label: 'Spectator Zone',   defaultStyle: { color: '#3498db', opacity: 0.25 } },
    { id: 'media_zone',       type: 'zone',           label: 'Media Zone',       defaultStyle: { color: '#9b59b6', opacity: 0.25 } },
    { id: 'staging_area',     type: 'zone',           label: 'Staging Area',     defaultStyle: { color: '#e67e22', opacity: 0.25 } },
    { id: 'race_village',     type: 'zone',           label: 'Race Village',     defaultStyle: { color: '#f39c12', opacity: 0.2 } },
    { id: 'exclusion_zone',   type: 'zone',           label: 'Exclusion Zone',   defaultStyle: { color: '#e74c3c', opacity: 0.35 } },

    // Infrastructure
    { id: 'tent',             type: 'infrastructure', label: 'Tent',             defaultStyle: { color: '#ecf0f1', opacity: 0.7 }, properties: [{ key: 'width', type: 'number', unit: 'm' }, { key: 'length', type: 'number', unit: 'm' }, { key: 'rotation', type: 'number', unit: '°' }] },
    { id: 'generator',        type: 'infrastructure', label: 'Generator',        defaultStyle: { color: '#f39c12', opacity: 0.7 }, properties: [{ key: 'width', type: 'number', unit: 'm' }, { key: 'length', type: 'number', unit: 'm' }, { key: 'rotation', type: 'number', unit: '°' }] },
    { id: 'toilet_block',     type: 'infrastructure', label: 'Toilet Block',     defaultStyle: { color: '#bdc3c7', opacity: 0.7 }, properties: [{ key: 'width', type: 'number', unit: 'm' }, { key: 'length', type: 'number', unit: 'm' }, { key: 'rotation', type: 'number', unit: '°' }] },
    { id: 'stage',            type: 'infrastructure', label: 'Stage',            defaultStyle: { color: '#2c3e50', opacity: 0.7 }, properties: [{ key: 'width', type: 'number', unit: 'm' }, { key: 'length', type: 'number', unit: 'm' }, { key: 'rotation', type: 'number', unit: '°' }] },
    { id: 'podium',           type: 'infrastructure', label: 'Podium',           defaultStyle: { color: '#f1c40f', opacity: 0.7 }, properties: [{ key: 'width', type: 'number', unit: 'm' }, { key: 'length', type: 'number', unit: 'm' }, { key: 'rotation', type: 'number', unit: '°' }] },
    { id: 'timing_gantry',    type: 'infrastructure', label: 'Timing Gantry',    defaultStyle: { color: '#e74c3c', opacity: 0.7 }, properties: [{ key: 'width', type: 'number', unit: 'm' }, { key: 'length', type: 'number', unit: 'm' }, { key: 'rotation', type: 'number', unit: '°' }] },
]

export const elementTypesBySubtype = Object.fromEntries(elementTypes.map(t => [t.id, t]))

export const elementTypesByDrawType = {
    group:          elementTypes.filter(t => t.type === 'group'),
    route:          elementTypes.filter(t => t.type === 'route'),
    marker:         elementTypes.filter(t => t.type === 'marker'),
    zone:           elementTypes.filter(t => t.type === 'zone'),
    infrastructure: elementTypes.filter(t => t.type === 'infrastructure'),
}
