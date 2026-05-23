# Race Planner — Agent Contributor Guide

A collaborative race event planning tool. Event owners invite collaborators to plan race logistics on an interactive map: drawing routes, placing markers, managing infrastructure, uploading overlays, and exporting plans.

---

## Commands

```bash
php artisan test              # Run all tests (SQLite, ~2s)
npm run build                 # Vite production build (run after any JS/Vue change)
npm run dev                   # Vite HMR dev server (port 5173)
php artisan serve             # Laravel dev server (port 8000)
```

Tests use SQLite (`database/testing.sqlite`, auto-created). Never alter `.env` credentials. If MariaDB credentials are needed for dev, ask the user.

**Scratch files:** Use `tmp/` inside the project root (gitignored). Never use `/tmp`.
**npm installs:** `npm install --cache /Users/kilian/Documents/projects/raceplanner/tmp/.npm-cache <package>`

---

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Frontend bridge | Inertia.js (Vue adapter) — no SPA router |
| Frontend | Vue 3 Composition API (`<script setup>` everywhere) |
| CSS | Tailwind CSS 4 via `@tailwindcss/vite` — single `@import "tailwindcss"` in `resources/css/app.css`. No `tailwind.config.js`, no postcss config. |
| Build | Vite — `vite.config.js` uses `@tailwindcss/vite` plugin |
| Map | MapLibre GL JS + `@mapbox/mapbox-gl-draw` + Turf.js |
| Auth | Laravel Breeze (Inertia/Vue preset) |
| Testing DB | SQLite |
| Dev/prod DB | MariaDB (credentials from `.env` — never hardcode) |

---

## Directory Structure

```
app/
  Http/Controllers/
    EventController.php          # Web routes: CRUD, duplication, collaborators
    InvitationController.php     # Invitation accept/decline
    Api/
      EventPlanController.php    # Plan CRUD + duplicate
      MapElementController.php   # Element CRUD (plan-scoped + shared)
      MapOverlayController.php   # Overlay CRUD + file upload
      EventExportController.php  # CSV export
  Models/
    Event.php                    # Has elementsForPlan(), overlaysForPlan()
    EventPlan.php
    MapElement.php
    MapOverlay.php
    EventCollaborator.php
    EventInvitation.php
  Policies/
    EventPolicy.php              # update() = owner only; editContent() = owner+editor; view() = all roles

config/
  map_elements.php               # PHP element type registry (used for API validation)

database/migrations/             # All migrations live here; tests run them fresh each run

resources/
  js/
    Pages/
      Dashboard.vue
      Events/
        Create.vue
        Edit.vue
        Show.vue                 # Renders MapEditor full-screen
      Invitations/               # Accept/decline invitation pages
    Components/
      Map/
        MapEditor.vue            # Core map editor — owns map/draw instances
        DrawToolbar.vue          # Registry-driven draw tool picker
        LayerSwitcher.vue        # Switches between map layers
        PlanSwitcher.vue         # Create/rename/delete/switch plans
        ElementSidebar.vue       # Element list (lock, hide)
        PropertiesPanel.vue      # Edit selected element properties
        OverlayManager.vue       # Upload, opacity, delete overlays
        draw-modes/
          DrawRectangleMode.js   # Custom two-click rectangle for infrastructure
    composables/
      useUndoStack.js
      useMapElements.js          # Element CRUD — MUST use toValue(planIdRef) in URLs
      useMapExport.js            # PNG export with devicePixelRatio override
    config/
      elementTypes.js            # JS element type registry
      mapLayers.js               # OSM vector + Vlaanderen satellite WMTS

routes/
  web.php                        # Inertia page routes
  api.php                        # REST API (session auth, NOT sanctum)

tests/Feature/
  EventCrudTest.php
  EventCollaboratorTest.php
  EventInvitationTest.php
  EventPlanApiTest.php
  MapElementApiTest.php
  MapOverlayTest.php
  EventExportTest.php
```

---

## API Conventions

All API routes are in `routes/api.php` under `Route::middleware('auth')->group(...)`. Auth is **session-based** — no Bearer tokens, no `auth:sanctum`.

### Routes

```
GET    /api/events/{event}/plans
POST   /api/events/{event}/plans
PATCH  /api/plans/{plan}
POST   /api/plans/{plan}/duplicate
DELETE /api/plans/{plan}

GET    /api/plans/{plan}/elements
POST   /api/plans/{plan}/elements      # plan-scoped element
POST   /api/events/{event}/elements    # shared element (event_plan_id = NULL)
PATCH  /api/elements/{element}
DELETE /api/elements/{element}

GET    /api/plans/{plan}/overlays
POST   /api/plans/{plan}/overlays      # plan-scoped overlay
POST   /api/events/{event}/overlays    # shared overlay
PATCH  /api/overlays/{overlay}
DELETE /api/overlays/{overlay}

GET    /api/plans/{plan}/export/csv
```

### Response shape convention

- `index` endpoints return `{ data: [...] }`
- `store`, `update` endpoints return the model directly (plain object, not wrapped)

This is intentional — follow it consistently when adding endpoints.

### Authorization

Always check authorization via `EventPolicy`:

```php
$this->authorize('view', $plan->event);       // read access
$this->authorize('editContent', $plan->event); // create/update/delete (owner + editor)
$this->authorize('update', $event);            // settings/rename (owner only)
```

Never use `update()` for content operations — that's owner-only event settings.

### Element plan scoping

`event_plan_id = NULL` means shared across all plans. When loading elements for a plan:

```php
// Event::elementsForPlan()
WHERE event_plan_id = {planId} OR (event_id = {eventId} AND event_plan_id IS NULL)
```

The same pattern applies to overlays (`Event::overlaysForPlan()`).

### CSV export

`EventExportController::csv()` uses `fputcsv` with `php://temp`. Sanitize the `Content-Disposition` filename with `str_replace(['"', "\r", "\n"], '', $name)` to prevent header injection.

### File storage

Overlays are stored under `storage/app/public/overlays/`. The symlink `public/storage → storage/app/public` is already in place (`php artisan storage:link` was run). Access files via `/storage/{overlay->image_path}` in the frontend.

When duplicating events, physically copy overlay files:

```php
$newPath = 'overlays/' . Str::uuid() . '.' . pathinfo($overlay->image_path, PATHINFO_EXTENSION);
Storage::disk('public')->copy($overlay->image_path, $newPath);
```

---

## Frontend Conventions

### Vue components

- All components use `<script setup>` (Composition API)
- `map` and `draw` in `MapEditor.vue` are plain `let` variables — not reactive refs. Never wrap them in `ref()`
- `canEdit` is a plain `const`, not a reactive ref: `const canEdit = props.event.role !== 'viewer'`

### `useMapElements.js` — critical

The composable accepts `planIdRef` which can be a Vue `ref` OR a plain number. Always extract the value with `toValue()`:

```js
import { ref, toValue } from 'vue'

export function useMapElements(eventId, planIdRef) {
    async function load() {
        const { data } = await axios.get(`/api/plans/${toValue(planIdRef)}/elements`)
        elements.value = data.data  // index wraps in {data:[...]}
    }
    async function create(payload) {
        const { data } = await axios.post(`/api/plans/${toValue(planIdRef)}/elements`, payload)
        elements.value.push(data)   // store returns plain object
        return data
    }
}
```

Passing the ref directly renders as `[object Object]` in the URL.

### Tailwind CSS 4

No config file. No PostCSS setup. Just:

```css
/* resources/css/app.css */
@import "tailwindcss";
```

The `@tailwindcss/vite` plugin (in `vite.config.js`) handles everything. Don't add `tailwind.config.js` or `postcss.config.js`.

### Alias

`@` resolves to `resources/js/` (configured in `vite.config.js`). Use `@/composables/...`, `@/config/...`, etc.

### Registry-driven architecture

Element types and map layers are driven entirely by registries — no `if/switch` on type anywhere except the registry itself:

- `config/map_elements.php` — PHP validation
- `resources/js/config/elementTypes.js` — exports `elementTypes`, `elementTypesBySubtype`, `elementTypesByDrawType`
- `resources/js/config/mapLayers.js` — OSM vector-style + Vlaanderen satellite WMTS

Adding a new element type = add one entry to each registry. Nothing else should need changing.

### DrawRectangleMode.js

Custom `@mapbox/mapbox-gl-draw` mode for infrastructure elements (two-click rectangle). Uses `this.map.fire('draw.create', ...)` then `this.changeMode('simple_select')`. This is the correct pattern — it does not cause duplicate events. Guard against degenerate rectangles: `if (x1 === x2 || y1 === y2) return`.

### MapLibre ImageSource coordinates

Overlay bounds are stored as `[[sw_lng, sw_lat], [ne_lng, ne_lat]]`. MapLibre `ImageSource` expects `[NW, NE, SE, SW]`:

```js
const coords = [
    [bounds[0][0], bounds[1][1]], // NW
    [bounds[1][0], bounds[1][1]], // NE
    [bounds[1][0], bounds[0][1]], // SE
    [bounds[0][0], bounds[0][1]], // SW
]
```

### Map lifecycle guards

Always guard rendering functions against destroyed state:

```js
if (!map || !map.isStyleLoaded()) return
```

In `onUnmounted`: `map?.remove(); map = null` — null the variable so stale references fail loudly.

---

## Known Pitfalls

**PlanSwitcher rename double-fire** — pressing Enter triggers `renamePlan`, then blur fires it again. Fix: set `renamingId.value = null` synchronously *before* the await, and guard with `if (renamingId.value !== plan.id) return` at the top.

**API response shape inconsistency is intentional** — `indexForPlan` wraps in `{data:[...]}`, store/update return plain objects. This matches how `useMapElements` consumes them. Don't "fix" it.

**PropertiesPanel `event_plan_id` from `<select>`** — select values are always strings. Convert: `Number($event.target.value) || null`. Similarly, `parseFloat('')` returns `NaN` — guard numeric inputs.

**npm install** — always pass `--cache tmp/.npm-cache` so the cache stays inside the project sandbox.

---

## Roles

| Role | `view()` | `editContent()` | `update()` |
|---|---|---|---|
| owner | ✓ | ✓ | ✓ |
| editor | ✓ | ✓ | ✗ |
| viewer | ✓ | ✗ | ✗ |

`editContent()` gates: elements, overlays, plans (create/rename/duplicate/delete).
`update()` gates: event settings (name, description).

---

## Plan History

Implementation plans live in `docs/superpowers/plans/`. The continuation guide at `docs/superpowers/plans/CONTINUE.md` describes what has been built across plans and is the canonical record of architectural decisions made during development.

- Plan 1 — Auth & Events (`2026-05-22-01-auth-and-events.md`) ✅
- Plan 2 — Map Editor (`2026-05-22-02-map-editor.md`) ✅
- Plan 3 — Export & Overlays (`2026-05-22-03-export-and-overlays.md`) ✅
- Plan 4 — Groups (`2026-05-23-04-groups.md`) ✅

All 80 tests pass. Update `CONTINUE.md` when adding new plans.
