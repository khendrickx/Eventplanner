# Race Planner — Design Spec
_Date: 2026-05-22_

## Overview

A Laravel application that gives race event organizers a graphical way to plan their event on an interactive map. Inspired by oneplan.io. Organizers can draw routes, place typed markers, define zones, and position scaled infrastructure objects (tents, stages, etc.) on a map with multiple layer options.

---

## 1. Architecture

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend bridge | Inertia.js (Vue adapter) |
| UI framework | Vue 3 + Tailwind CSS 4 |
| Auth | Laravel Breeze (Inertia/Vue preset) |
| Map | MapLibre GL JS + `@mapbox/mapbox-gl-draw` |
| Geometry calculations | Turf.js (client-side) |
| Build | Vite |
| DB (test) | SQLite |
| DB (dev/prod) | MariaDB |

**Request flow:** Browser → Inertia request → Laravel controller → Inertia response with Vue component + props → Vue renders page. Map edits use a dedicated JSON API (`/api/events/{id}/elements`) so the map can save incrementally without full page navigation.

**Environments:** `.env` uses `DB_CONNECTION=sqlite` for testing; `DB_CONNECTION=mariadb` with separate credentials for dev/prod. Standard Laravel database config supports both without migration changes.

---

## 2. Extensibility Architecture

New element types and map layers must be addable by editing a single config file, with no changes to controllers, migrations, or Vue components.

### Element Type Registry

Element subtypes are defined in `config/map_elements.php` (PHP, used for server-side validation) and mirrored in `resources/js/config/elementTypes.js` (JS, drives the UI). Each entry defines:

```js
{
  id: 'buoy',           // stored in map_elements.subtype
  type: 'marker',       // draw mode: 'marker' | 'route' | 'zone' | 'infrastructure'
  label: 'Buoy',        // shown in UI
  icon: 'buoy',         // icon file reference
  defaultStyle: {       // MapLibre paint properties defaults
    color: '#0077cc',
    opacity: 1.0,
  },
  properties: [],       // extra properties schema (e.g. [{key:'width', type:'number', unit:'m'}])
}
```

Adding a new marker, zone, or route subtype = one object added to each config file.

### Layer Registry

Map layers are defined in `config/map_layers.php` and mirrored in `resources/js/config/mapLayers.js`. Each entry defines:

```js
{
  id: 'osm',
  label: 'OpenStreetMap',
  type: 'vector-style',           // 'vector-style' | 'wmts' | 'raster-xyz'
  url: 'https://...',
  attribution: '© OpenStreetMap',
}
```

Adding a new map layer = one object added to each config file. The `LayerSwitcher` component reads the JS registry and renders buttons dynamically. No hard-coded layer logic anywhere.

---

## 3. Data Model

### `events`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK | owner |
| name | string | |
| description | text nullable | |
| created_at / updated_at | timestamps | |

### `event_collaborators`
| Column | Type | Notes |
|---|---|---|
| event_id | bigint FK | |
| user_id | bigint FK | |
| role | enum | `editor`, `viewer` |

### `event_plans`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| event_id | bigint FK | cascades on delete |
| name | string | e.g. "Plan A", "Alternative Route" |
| sort_order | integer | display order; set to `max + 1` on creation |
| created_at / updated_at | timestamps | |

Each event always has at least one plan. A default plan named "Plan 1" is created automatically when an event is created. Event duplication copies all plans and their elements.

### `map_elements`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| event_id | bigint FK | always set; used to scope shared elements |
| event_plan_id | bigint FK nullable | null = shown in all plans of this event |
| type | enum | `route`, `marker`, `zone`, `infrastructure` |
| subtype | string | validated against element type registry |
| name | string nullable | user-supplied label |
| notes | text nullable | free-text notes (vendor info, comments, etc.) |
| geometry | json | GeoJSON geometry object (Point / LineString / Polygon) |
| properties | json | type-specific data: infrastructure dimensions (`width`, `length`, `rotation`); styling (`fill_color`, `stroke_color`, `opacity`); extensible |
| is_locked | boolean | default false; locked elements cannot be moved/edited in the map |
| is_hidden | boolean | default false; hidden elements are not rendered on the map |
| sort_order | integer | render stacking order; set to `max + 1` on creation, reorderable via drag in the element sidebar |
| created_at / updated_at | timestamps | |

**Default behaviour:** When an element is created, `event_plan_id` defaults to `NULL` (shared across all plans). The user can assign it to a specific plan via a "Plan" dropdown in the properties panel.

When loading elements for a plan, the query returns: elements where `event_plan_id = {plan_id}` OR (`event_id = {event_id}` AND `event_plan_id IS NULL`).

### `map_overlays`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| event_id | bigint FK | always set |
| event_plan_id | bigint FK nullable | null = shown in all plans |
| name | string | |
| image_path | string | path to stored file (Laravel storage) |
| bounds | json | `[[sw_lng, sw_lat], [ne_lng, ne_lat]]` — corner coordinates for placement |
| opacity | float | default 1.0, adjustable by user |
| sort_order | integer | stacking order |
| created_at / updated_at | timestamps | |

### `event_invitations`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| event_id | bigint FK | |
| email | string | |
| role | enum | `editor`, `viewer` |
| token | string unique | used in registration link |
| expires_at | timestamp | 7 days after creation |
| created_at | timestamp | |

---

## 4. Frontend Pages & Components

### Inertia Pages
| Route | Page | Description |
|---|---|---|
| `/` | `Dashboard` | List of owned + shared events, create/duplicate buttons |
| `/events/create` | `Events/Create` | Name, description form |
| `/events/{id}` | `Events/Show` | Full-screen map editor |
| `/events/{id}/edit` | `Events/Edit` | Name, description, collaborators management |
| `/login`, `/register`, etc. | Breeze defaults | |

### Map Editor Layout (`Events/Show`)

```
┌─────────────────────────────────────────────────────────┐
│ Toolbar: plan switcher │ layer switcher │ draw tools │ undo │ rotate │
├──────────────────┬──────────────────────────┬───────────┤
│                  │                          │ Properties│
│  Element sidebar │     MapLibre canvas      │ panel     │
│  (list grouped   │                          │ (auto-    │
│  by type, click  │                          │ height,   │
│  to select)      │                          │ top-      │
│                  │                          │ aligned)  │
│                  │                          └───────────┤
│                  │                                      │
└──────────────────┴──────────────────────────────────────┘
```

The properties panel appears on the right when an element is selected, sized to its content. The map canvas fills remaining horizontal space via CSS flex.

### Key Vue Components
- `MapEditor.vue` — mounts MapLibre, owns draw state and undo stack, emits save events
- `ElementSidebar.vue` — scrollable list grouped by type, shows lock/hide toggles per element, click selects on map
- `PropertiesPanel.vue` — form for name, subtype, notes, styling, and type-specific fields; shows calculated measurements (length / area / perimeter)
- `LayerSwitcher.vue` — reads `mapLayers.js` registry and renders layer buttons dynamically
- `DrawToolbar.vue` — reads `elementTypes.js` registry and renders draw mode buttons dynamically
- `OverlayManager.vue` — upload image overlay, adjust bounds and opacity

---

## 5. Map Editing

### Map Layers
Defined in `resources/js/config/mapLayers.js`:

| ID | Label | Type |
|---|---|---|
| `osm` | OpenStreetMap | `vector-style` via openfreemap.org |
| `vlaanderen-satellite` | Vlaanderen Satellite | `wmts` — `geo.api.vlaanderen.be/OMW/wmts`, layer `omwrgb25vl`, tilematrixset `BPL2008VL` |

New layers added to this file appear in `LayerSwitcher` automatically.

### Drawing Tools (`@mapbox/mapbox-gl-draw`, compatible with MapLibre GL JS)

| Tool | Mode | Output geometry |
|---|---|---|
| Route | `draw_line_string` | LineString |
| Marker | `draw_point` | Point |
| Zone | `draw_polygon` | Polygon |
| Infrastructure | custom `draw_rectangle` mode | Polygon (rectangle) |

The custom `draw_rectangle` mode captures two clicks (corner to corner), generates a rectangle polygon, then opens the properties panel to collect `width`, `length`, and `rotation`. Width/length are stored in `properties`; rotation is applied visually via MapLibre paint properties.

### Predefined Subtypes (defined in `config/map_elements.php` + `elementTypes.js`)

**Route subtypes:** `course`, `pedestrian_route`, `vehicle_route`, `barrier`, `fence`

**Marker subtypes:** `buoy`, `start`, `finish`, `checkpoint`, `aid_station`, `medical`, `hazard`, `electricity`, `transition_zone`, `timing_mat`, `spectator_area`, `bag_drop`, `feed_zone`

**Zone subtypes:** `restricted_area`, `parking_zone`, `transition_zone`, `start_zone`, `finish_area`, `spectator_zone`, `media_zone`, `staging_area`, `race_village`, `exclusion_zone` — plus freeform (no subtype)

**Infrastructure subtypes:** `tent`, `generator`, `toilet_block`, `stage`, `podium`, `timing_gantry`

### Instant Calculations (Turf.js, client-side only)
Shown read-only in the properties panel when an element is selected:

| Element type | Displayed |
|---|---|
| Route (all subtypes incl. barrier, fence) | Length in m / km |
| Zone | Area in m² and perimeter in m |
| Infrastructure | Area (width × length m²) |

No server involvement — computed from the GeoJSON geometry in the browser using Turf.js `length()` and `area()`.

### Element Styling
Each element has optional styling stored in `properties.styling`:
- `fill_color` (hex) — fill for zones and infrastructure
- `stroke_color` (hex) — border/line color for all types
- `opacity` (0.0–1.0)

Defaults come from the element type registry (`elementTypes.js`). The properties panel shows a colour picker and opacity slider when an element is selected.

### Lock / Hide
- **Locked** (`is_locked: true`) — element is visible on the map but cannot be selected, moved, or edited via the draw tools. Editing is still possible through the properties panel (for name, notes, styling). Prevents accidental dragging of fixed infrastructure.
- **Hidden** (`is_hidden: true`) — element is not rendered on the map canvas but remains in the sidebar and database. Useful for planning alternatives or decluttering the view.

Lock and hide toggles appear per element in `ElementSidebar.vue` and in the properties panel.

### Map Rotation
- **Free rotation** — right-click drag on the map canvas rotates the view (MapLibre built-in)
- **North-reset button** — in the toolbar, resets bearing to 0°

### Save Strategy
Each create/update/delete of a map element fires an individual API call:
- `POST /api/events/{id}/elements` — create
- `PATCH /api/elements/{id}` — update
- `DELETE /api/elements/{id}` — delete

Changes persist immediately. A subtle status indicator in the toolbar shows "Saving…" / "Saved".

### Undo Stack (client-side only)
Each map edit pushes an inverse operation onto an in-memory stack in Vue state. Not persisted — cleared on page reload. Capped at 50 operations.

| Action | Pushed inverse |
|---|---|
| Create element | Delete that element |
| Update element | Restore previous properties/geometry |
| Delete element | Re-create with original data |

`Ctrl+Z` and an undo button in the toolbar pop the top of the stack and fire the corresponding API call. Redo is out of scope.

The undo stack lives in a `useUndoStack` composable, owned by `MapEditor.vue`.

### Permissions Enforcement
API routes check the authenticated user's role on the event. Viewers receive a read-only map (draw controls hidden, lock/hide/styling controls hidden, API writes return 403).

---

## 6. Image Overlays

Organizers can import an image (PNG, JPG) as a georeferenced underlay beneath map elements — useful for old venue layouts, floor plans, or custom imagery.

**Upload flow:**
1. User clicks "Import Overlay" in the toolbar → selects an image file
2. Image is uploaded via `POST /api/events/{id}/overlays` and stored in Laravel storage
3. Image is placed at the current map viewport bounds as a MapLibre `ImageSource`
4. User can drag the corners to reposition/resize the overlay bounds
5. Opacity slider adjusts transparency
6. On save, `bounds` and `opacity` are persisted to `map_overlays`

**Constraints:** Image files only (no CAD/SVG). Max file size TBD (suggest 10 MB). Overlays are rendered beneath all map elements.

**API:**
```
POST   /api/events/{id}/overlays    — upload image, create overlay record
PATCH  /api/overlays/{id}           — update bounds / opacity
DELETE /api/overlays/{id}           — remove overlay
```

---

## 7. Export

### PNG Export
Accessible via an "Export" button in the toolbar. Options:
- **Digital** — 72 DPI PNG, watermarked
- **Print** — 300 DPI PNG (no watermark)

Implementation: MapLibre's `map.getCanvas().toDataURL()` captures the current viewport at screen resolution. For print quality, the map is temporarily rendered at a higher pixel ratio (`devicePixelRatio` override) before capture. The resulting PNG is downloaded client-side — no server involvement.

### CSV Export
Exports all `map_elements` for the event as a flat CSV:
`id, type, subtype, name, notes, geometry_wkt, width, length, rotation, fill_color, stroke_color`

Geometry is serialised as WKT for readability. Generated server-side via `GET /api/events/{id}/export/csv`, streamed as a download.

---

## 8. Auth & Collaboration

### Authentication
Laravel Breeze (Inertia/Vue preset) provides login, register, password reset, and email verification out of the box.

### Roles
| Role | View map | Edit elements | Manage collaborators | Delete event |
|---|---|---|---|---|
| Owner | ✓ | ✓ | ✓ | ✓ |
| Editor | ✓ | ✓ | ✗ | ✗ |
| Viewer | ✓ | ✗ | ✗ | ✗ |

### Inviting Collaborators
From `Events/Edit`, the owner enters an email and selects a role.

- **Email found** → user is added to `event_collaborators` immediately
- **Email not found** → an `event_invitations` row is created with a unique token; an email is sent with a registration link `/register?invitation={token}`; the registration page pre-fills the email; after account creation, all pending invitations for that email are converted to `event_collaborators` rows and the invitation records deleted

Invitations expire after **7 days**. Visiting an expired token shows an informative error; the owner can re-send.

If an already-authenticated user visits `/register?invitation={token}`, they are redirected to the dashboard and the invitation is accepted immediately without re-registering.

### Event Duplication
Available to all users with access (owner, editor, or viewer). Creates a new event owned by the duplicating user with all `event_plans`, `map_elements`, and `map_overlays` copied (overlay image files are duplicated in storage). Collaborators are not copied. Accessible from the dashboard and `Events/Edit`.

---

## 9. API Routes

```
# Plans
GET    /api/events/{id}/plans          — list plans for event
POST   /api/events/{id}/plans          — create plan (editor/owner)
PATCH  /api/plans/{id}                 — rename plan (editor/owner)
POST   /api/plans/{id}/duplicate       — duplicate plan + elements (editor/owner)
DELETE /api/plans/{id}                 — delete plan (editor/owner; event must retain ≥1 plan)

# Elements
GET    /api/plans/{id}/elements        — list elements for plan (includes shared elements)
POST   /api/plans/{id}/elements        — create element scoped to plan (editor/owner)
POST   /api/events/{id}/elements       — create shared element (no plan; editor/owner)
PATCH  /api/elements/{id}              — update element (editor/owner)
DELETE /api/elements/{id}              — delete element (editor/owner)

# Overlays
POST   /api/plans/{id}/overlays        — upload overlay scoped to plan (editor/owner)
POST   /api/events/{id}/overlays       — upload shared overlay (editor/owner)
PATCH  /api/overlays/{id}              — update bounds/opacity (editor/owner)
DELETE /api/overlays/{id}              — delete overlay (editor/owner)

# Export
GET    /api/plans/{id}/export/csv      — download CSV for a plan (any access)
```

PNG export is client-side only — no API route needed.

---

## 10. Testing Strategy

- **Unit tests:** Models, invitation expiry logic, role checks, element type registry validation
- **Feature tests:** Event CRUD, collaborator invite flow, element API (create/update/delete/permission enforcement), overlay upload, CSV export
- **Database:** SQLite for all tests (fast, zero config)
- Map rendering, draw interactions, and client-side calculations are not tested at the backend level; they are browser-only concerns
