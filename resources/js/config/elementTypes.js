// resources/js/config/elementTypes.js
//
// Single source of truth for every element type.
// Fields:
//   id           – unique subtype key, stored in the DB
//   type         – draw type: 'group' | 'route' | 'marker' | 'zone' | 'infrastructure'
//   label        – human-readable name
//   category     – top-level group in the Add Objects panel
//   subcategory  – second-level group in the Add Objects panel
//   icon         – key from elementIcons.js  (markers only; routes/zones/infra use shape rendering)
//   defaultStyle – color/opacity/width used for map rendering
//   properties   – extra editable properties (infrastructure only)
//
// Adding a new type: add one entry here + one entry in config/map_elements.php.
// Category/subcategory labels live in CATEGORY_LABELS / SUBCATEGORY_LABELS below.

const INFRA_PROPS = [
    { key: 'width',    type: 'number', unit: 'm' },
    { key: 'length',   type: 'number', unit: 'm' },
    { key: 'rotation', type: 'number', unit: '°' },
]

export const elementTypes = [
    // ── Groups (sidebar-only, no geometry) ───────────────────────────────────
    { id: 'group', type: 'group', label: 'Group', defaultStyle: { color: '#6366f1', opacity: 0.12 } },

    // ── Routes ───────────────────────────────────────────────────────────────
    { id: 'course',           type: 'route', label: 'Course',           icon: 'route_line',   category: 'areas_routes',  subcategory: 'routes',     defaultStyle: { color: '#e74c3c', width: 4 } },
    { id: 'pedestrian_route', type: 'route', label: 'Pedestrian Route', icon: 'pedestrian',   category: 'areas_routes',  subcategory: 'routes',     defaultStyle: { color: '#3498db', width: 2 } },
    { id: 'vehicle_route',    type: 'route', label: 'Vehicle Route',    icon: 'vehicle',      category: 'areas_routes',  subcategory: 'routes',     defaultStyle: { color: '#f39c12', width: 2 } },
    { id: 'barrier',          type: 'route', label: 'Barrier',          icon: 'barrier_line', category: 'areas_routes',  subcategory: 'access',     defaultStyle: { color: '#e67e22', width: 3 } },
    { id: 'fence',            type: 'route', label: 'Fence',            icon: 'fence',        category: 'areas_routes',  subcategory: 'access',     defaultStyle: { color: '#95a5a6', width: 2 } },

    // ── Markers ──────────────────────────────────────────────────────────────
    { id: 'buoy',               type: 'marker', label: 'Buoy',               icon: 'buoy',               category: 'areas_routes',   subcategory: 'course_markers', defaultStyle: { color: '#2980b9' } },
    { id: 'start',              type: 'marker', label: 'Start',              icon: 'flag_start',         category: 'areas_routes',   subcategory: 'course_markers', defaultStyle: { color: '#27ae60' } },
    { id: 'finish',             type: 'marker', label: 'Finish',             icon: 'flag_finish',        category: 'areas_routes',   subcategory: 'course_markers', defaultStyle: { color: '#c0392b' } },
    { id: 'checkpoint',         type: 'marker', label: 'Checkpoint',         icon: 'checkpoint',         category: 'areas_routes',   subcategory: 'course_markers', defaultStyle: { color: '#8e44ad' } },
    { id: 'transition_marker',  type: 'marker', label: 'Transition',         icon: 'transition_arrows',  category: 'areas_routes',   subcategory: 'course_markers', defaultStyle: { color: '#1abc9c' } },
    { id: 'timing_mat',         type: 'marker', label: 'Timing Mat',         icon: 'clock',              category: 'areas_routes',   subcategory: 'course_markers', defaultStyle: { color: '#34495e' } },
    { id: 'aid_station',        type: 'marker', label: 'Aid Station',        icon: 'aid',                category: 'infrastructure', subcategory: 'medical',        defaultStyle: { color: '#e67e22' } },
    { id: 'medical',            type: 'marker', label: 'Medical',            icon: 'medical_cross',      category: 'infrastructure', subcategory: 'medical',        defaultStyle: { color: '#e74c3c' } },
    { id: 'hazard',             type: 'marker', label: 'Hazard',             icon: 'warning',            category: 'infrastructure', subcategory: 'safety',         defaultStyle: { color: '#f39c12' } },
    { id: 'electricity',        type: 'marker', label: 'Electricity',        icon: 'lightning',          category: 'infrastructure', subcategory: 'utilities',      defaultStyle: { color: '#f1c40f' } },
    { id: 'spectator_area',     type: 'marker', label: 'Spectator Area',     icon: 'people',             category: 'areas_routes',   subcategory: 'spectator',      defaultStyle: { color: '#16a085' } },
    { id: 'bag_drop',           type: 'marker', label: 'Bag Drop',           icon: 'bag',                category: 'infrastructure', subcategory: 'athlete_services', defaultStyle: { color: '#7f8c8d' } },
    { id: 'feed_zone',          type: 'marker', label: 'Feed Zone',          icon: 'feed',               category: 'infrastructure', subcategory: 'athlete_services', defaultStyle: { color: '#d35400' } },

    // ── Zones ────────────────────────────────────────────────────────────────
    { id: 'restricted_area', type: 'zone', label: 'Restricted Area',  icon: 'zone_restricted', category: 'areas_routes',   subcategory: 'areas',   defaultStyle: { color: '#e74c3c', opacity: 0.2 } },
    { id: 'parking_zone',    type: 'zone', label: 'Parking Zone',     icon: 'parking',         category: 'areas_routes',   subcategory: 'areas',   defaultStyle: { color: '#95a5a6', opacity: 0.3 } },
    { id: 'transition_zone', type: 'zone', label: 'Transition Zone',  icon: 'transition_arrows', category: 'areas_routes', subcategory: 'areas',   defaultStyle: { color: '#1abc9c', opacity: 0.3 } },
    { id: 'start_zone',      type: 'zone', label: 'Start Zone',       icon: 'flag_start',      category: 'areas_routes',   subcategory: 'areas',   defaultStyle: { color: '#27ae60', opacity: 0.3 } },
    { id: 'finish_area',     type: 'zone', label: 'Finish Area',      icon: 'flag_finish',     category: 'areas_routes',   subcategory: 'areas',   defaultStyle: { color: '#c0392b', opacity: 0.3 } },
    { id: 'spectator_zone',  type: 'zone', label: 'Spectator Zone',   icon: 'spectator_zone',  category: 'areas_routes',   subcategory: 'spectator', defaultStyle: { color: '#3498db', opacity: 0.25 } },
    { id: 'media_zone',      type: 'zone', label: 'Media Zone',       icon: 'camera',          category: 'areas_routes',   subcategory: 'areas',   defaultStyle: { color: '#9b59b6', opacity: 0.25 } },
    { id: 'staging_area',    type: 'zone', label: 'Staging Area',     icon: 'staging',         category: 'infrastructure', subcategory: 'event',   defaultStyle: { color: '#e67e22', opacity: 0.25 } },
    { id: 'race_village',    type: 'zone', label: 'Race Village',     icon: 'village',         category: 'infrastructure', subcategory: 'event',   defaultStyle: { color: '#f39c12', opacity: 0.2 } },
    { id: 'exclusion_zone',  type: 'zone', label: 'Exclusion Zone',   icon: 'exclusion',       category: 'areas_routes',   subcategory: 'areas',   defaultStyle: { color: '#e74c3c', opacity: 0.35 } },

    // ── Infrastructure ───────────────────────────────────────────────────────
    { id: 'tent',          type: 'infrastructure', label: 'Tent',          icon: 'tent',            category: 'infrastructure', subcategory: 'structures',      defaultStyle: { color: '#ecf0f1', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'generator',     type: 'infrastructure', label: 'Generator',     icon: 'generator',       category: 'infrastructure', subcategory: 'utilities',       defaultStyle: { color: '#f39c12', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'toilet_block',  type: 'infrastructure', label: 'Toilet Block',  icon: 'toilet',          category: 'infrastructure', subcategory: 'structures',      defaultStyle: { color: '#bdc3c7', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'stage',         type: 'infrastructure', label: 'Stage',         icon: 'stage_platform',  category: 'infrastructure', subcategory: 'event',           defaultStyle: { color: '#2c3e50', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'podium',        type: 'infrastructure', label: 'Podium',        icon: 'podium',          category: 'infrastructure', subcategory: 'event',           defaultStyle: { color: '#f1c40f', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'timing_gantry', type: 'infrastructure', label: 'Timing Gantry', icon: 'gantry',          category: 'areas_routes',   subcategory: 'course_markers',  defaultStyle: { color: '#e74c3c', opacity: 0.7 }, properties: INFRA_PROPS },

    // Food & Beverage
    { id: 'food_stall',   type: 'infrastructure', label: 'Food Stall',    icon: 'food_stall',  category: 'infrastructure', subcategory: 'food_beverage',   defaultStyle: { color: '#e67e22', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'bar_drinks',   type: 'infrastructure', label: 'Bar / Drinks',  icon: 'bar_tent',    category: 'infrastructure', subcategory: 'food_beverage',   defaultStyle: { color: '#8e44ad', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'water_point',  type: 'infrastructure', label: 'Water Point',   icon: 'water_drop',  category: 'infrastructure', subcategory: 'food_beverage',   defaultStyle: { color: '#3498db', opacity: 0.7 }, properties: INFRA_PROPS },

    // Branding & Signage
    { id: 'banner_arch',  type: 'infrastructure', label: 'Banner Arch',   icon: 'gantry',      category: 'infrastructure', subcategory: 'branding',        defaultStyle: { color: '#e74c3c', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'info_board',   type: 'infrastructure', label: 'Info Board',    icon: 'banner_sign', category: 'infrastructure', subcategory: 'branding',        defaultStyle: { color: '#2c3e50', opacity: 0.7 }, properties: INFRA_PROPS },

    // Transport & Parking
    { id: 'bike_parking', type: 'infrastructure', label: 'Bike Parking',  icon: 'bike_rack',   category: 'infrastructure', subcategory: 'transport_infra', defaultStyle: { color: '#27ae60', opacity: 0.7 }, properties: INFRA_PROPS },
    { id: 'shuttle_stop', type: 'infrastructure', label: 'Shuttle Stop',  icon: 'vehicle',     category: 'infrastructure', subcategory: 'transport_infra', defaultStyle: { color: '#3498db', opacity: 0.7 }, properties: INFRA_PROPS },

    // ── Annotations ──────────────────────────────────────────────────────────
    { id: 'text_label',       type: 'marker',        label: 'Text Label',  icon: 'text_label', category: 'annotations', subcategory: 'annotations', defaultStyle: { color: '#1a1a1a' } },
    { id: 'annotation_line',  type: 'route',         label: 'Line',        icon: 'route_line', category: 'annotations', subcategory: 'annotations', defaultStyle: { color: '#6b7280', width: 2 } },
    { id: 'annotation_rect',  type: 'infrastructure',label: 'Rectangle',   icon: 'square',     category: 'annotations', subcategory: 'annotations', defaultStyle: { color: '#6b7280', opacity: 0.15 }, properties: INFRA_PROPS },
    { id: 'annotation_circle',type: 'zone',          label: 'Circle',      icon: 'ring',       category: 'annotations', subcategory: 'annotations', defaultStyle: { color: '#6b7280', opacity: 0.15 }, drawMode: 'draw_circle' },

    // ── Entry & Access ────────────────────────────────────────────────────────
    { id: 'entry_gate',            type: 'marker', label: 'Entry Gate',            icon: 'gate_entry',     category: 'areas_routes', subcategory: 'entry_access', defaultStyle: { color: '#27ae60' } },
    { id: 'exit_gate',             type: 'marker', label: 'Exit Gate',             icon: 'gate_exit',      category: 'areas_routes', subcategory: 'entry_access', defaultStyle: { color: '#e74c3c' } },
    { id: 'ticket_check',          type: 'marker', label: 'Ticket Check',          icon: 'ticket_check',   category: 'areas_routes', subcategory: 'entry_access', defaultStyle: { color: '#8e44ad' } },
    { id: 'wristband_collection',  type: 'marker', label: 'Wristband Collection',  icon: 'wristband',      category: 'areas_routes', subcategory: 'entry_access', defaultStyle: { color: '#2980b9' } },
    { id: 'accreditation',         type: 'marker', label: 'Accreditation',         icon: 'accreditation',  category: 'areas_routes', subcategory: 'entry_access', defaultStyle: { color: '#16a085' } },

    // ── Workforce ─────────────────────────────────────────────────────────────
    { id: 'steward',    type: 'marker', label: 'Steward',    icon: 'person_vest', category: 'workforce', subcategory: 'personnel', defaultStyle: { color: '#f39c12' } },
    { id: 'security',   type: 'marker', label: 'Security',   icon: 'shield',      category: 'workforce', subcategory: 'personnel', defaultStyle: { color: '#2c3e50' } },
    { id: 'police',     type: 'marker', label: 'Police',     icon: 'badge_star',  category: 'workforce', subcategory: 'personnel', defaultStyle: { color: '#2980b9' } },
    { id: 'fire',       type: 'marker', label: 'Fire',       icon: 'flame',       category: 'workforce', subcategory: 'personnel', defaultStyle: { color: '#e74c3c' } },
    { id: 'volunteer',  type: 'marker', label: 'Volunteer',  icon: 'person_heart',category: 'workforce', subcategory: 'personnel', defaultStyle: { color: '#27ae60' } },
    { id: 'supervisor', type: 'marker', label: 'Supervisor', icon: 'person_tie',  category: 'workforce', subcategory: 'personnel', defaultStyle: { color: '#8e44ad' } },
]

// ── Category / subcategory labels ────────────────────────────────────────────
// To add/rename a category: change here only — everything else derives from it.

export const CATEGORY_LABELS = {
    areas_routes:   'Areas, Routes & Access',
    infrastructure: 'Infrastructure',
    workforce:      'Workforce',
    annotations:    'Annotations',
}

export const SUBCATEGORY_LABELS = {
    routes:           'Routes',
    access:           'Barriers & Access',
    entry_access:     'Entry & Access',
    course_markers:   'Course Markers',
    spectator:        'Spectator',
    areas:            'Areas',
    medical:          'Medical',
    safety:           'Safety',
    utilities:        'Utilities',
    athlete_services: 'Athlete Services',
    structures:       'Structures',
    event:            'Event Facilities',
    personnel:        'Personnel',
    food_beverage:    'Food & Beverage',
    branding:         'Branding & Signage',
    transport_infra:  'Transport & Parking',
    annotations:      'Shapes & Labels',
}

// ── Derived lookups (read-only — do not edit manually) ───────────────────────

export const elementTypesBySubtype = Object.fromEntries(elementTypes.map(t => [t.id, t]))

export const elementTypesByDrawType = {
    group:          elementTypes.filter(t => t.type === 'group'),
    route:          elementTypes.filter(t => t.type === 'route'),
    marker:         elementTypes.filter(t => t.type === 'marker'),
    zone:           elementTypes.filter(t => t.type === 'zone'),
    infrastructure: elementTypes.filter(t => t.type === 'infrastructure'),
}

// Hierarchical structure for the Add Objects panel — derived, never edited manually.
export const elementCategories = (() => {
    const catMap   = {}
    const catOrder = []
    for (const t of elementTypes) {
        if (!t.category || t.type === 'group') continue
        if (!catMap[t.category]) {
            catMap[t.category] = { id: t.category, label: CATEGORY_LABELS[t.category] || t.category, subcats: {}, subcatOrder: [] }
            catOrder.push(t.category)
        }
        const cat = catMap[t.category]
        if (!cat.subcats[t.subcategory]) {
            cat.subcats[t.subcategory] = { id: t.subcategory, label: SUBCATEGORY_LABELS[t.subcategory] || t.subcategory, types: [] }
            cat.subcatOrder.push(t.subcategory)
        }
        cat.subcats[t.subcategory].types.push(t)
    }
    return catOrder.map(cid => ({
        id:            catMap[cid].id,
        label:         catMap[cid].label,
        subcategories: catMap[cid].subcatOrder.map(sid => catMap[cid].subcats[sid]),
    }))
})()
