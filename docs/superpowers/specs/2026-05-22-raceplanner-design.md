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
| Build | Vite |
| DB (test) | SQLite |
| DB (dev/prod) | MariaDB |

**Request flow:** Browser → Inertia request → Laravel controller → Inertia response with Vue component + props → Vue renders page. Map edits use a dedicated JSON API (`/api/events/{id}/elements`) so the map can save incrementally without full page navigation.

**Environments:** `.env` uses `DB_CONNECTION=sqlite` for testing; `DB_CONNECTION=mariadb` with separate credentials for dev/prod. Standard Laravel database config supports both without migration changes.

---

## 2. Data Model

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

### `map_elements`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| event_id | bigint FK | |
| type | enum | `route`, `marker`, `zone`, `infrastructure` |
| subtype | string | e.g. `start`, `buoy`, `tent`, `restricted_area` |
| name | string nullable | user-supplied label |
| geometry | json | GeoJSON geometry object (Point / LineString / Polygon) |
| properties | json | dimensions for infrastructure (`width`, `length`, `rotation` in degrees); extensible for future properties |
| sort_order | integer | controls render stacking order; set to `max + 1` on creation, reorderable via drag in the element sidebar |
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

## 3. Frontend Pages & Components

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
│ Toolbar: layer switcher │ draw tools │ undo │ save      │
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
- `ElementSidebar.vue` — scrollable list grouped by type, click selects element on map
- `PropertiesPanel.vue` — form for name, subtype, and type-specific fields (width/length/rotation for infrastructure)
- `LayerSwitcher.vue` — toggles between OSM and Vlaanderen satellite
- `DrawToolbar.vue` — buttons for each draw mode (route, marker type picker, zone, infrastructure rectangle)

---

## 4. Map Editing

### Map Layers
- **OpenStreetMap** via openfreemap.org vector tiles (default) — loaded as a MapLibre style URL
- **Vlaanderen satellite** via WMTS endpoint (`geo.api.vlaanderen.be/OMW/wmts`, layer `omwrgb25vl`, tilematrixset `BPL2008VL`) — added as a raster tile source, toggled via `LayerSwitcher`

### Drawing Tools (`@mapbox/mapbox-gl-draw`, compatible with MapLibre GL JS)

| Tool | Mode | Output geometry |
|---|---|---|
| Route | `draw_line_string` | LineString |
| Marker | `draw_point` | Point |
| Zone | `draw_polygon` | Polygon |
| Infrastructure | custom `draw_rectangle` mode | Polygon (rectangle) |

The custom `draw_rectangle` mode captures two clicks (corner to corner), generates a rectangle polygon, then opens the properties panel to collect `width`, `length`, and `rotation`. Width/length are stored in `properties`; rotation is applied visually via MapLibre paint properties.

### Predefined Subtypes

**Marker subtypes:** `buoy`, `start`, `finish`, `checkpoint`, `aid_station`, `medical`, `hazard`, `electricity`, `transition_zone`, `timing_mat`, `spectator_area`, `bag_drop`, `feed_zone`

**Zone subtypes:** `restricted_area`, `parking_zone`, `transition_zone`, `start_zone`, `finish_area`, `spectator_zone`, `media_zone`, `staging_area`, `race_village`, `exclusion_zone` — plus freeform (no subtype)

**Infrastructure subtypes:** `tent`, `generator`, `toilet_block`, `stage`, `podium`, `timing_gantry`

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
API routes check the authenticated user's role on the event. Viewers receive a read-only map (draw controls hidden, API writes return 403).

---

## 5. Auth & Collaboration

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
Available to all users with access (owner, editor, or viewer). Creates a new event owned by the duplicating user with all `map_elements` copied. Collaborators are not copied. Accessible from the dashboard and `Events/Edit`.

---

## 6. API Routes

```
GET    /api/events/{id}/elements       — list all elements (auth + access check)
POST   /api/events/{id}/elements       — create element (editor/owner only)
PATCH  /api/elements/{id}              — update element (editor/owner only)
DELETE /api/elements/{id}              — delete element (editor/owner only)
```

---

## 7. Testing Strategy

- **Unit tests:** Models, invitation expiry logic, role checks
- **Feature tests:** Event CRUD, collaborator invite flow, element API (create/update/delete/permission enforcement)
- **Database:** SQLite for all tests (fast, zero config)
- Map rendering and draw interactions are not tested at the backend level; they are browser-only concerns
