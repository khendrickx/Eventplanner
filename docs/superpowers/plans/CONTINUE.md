# Continuation Guide — Race Planner

> **For agentic workers:** Use `superpowers:subagent-driven-development` to execute each plan task-by-task. Read the relevant plan file in full before dispatching any subagents. Do NOT skip the two-stage review (spec compliance + code quality) after each task.

---

## Current state (as of 2026-05-23)

**Plan 1 — Auth & Events: ✅ COMPLETE**

All 48 tests pass. Git HEAD: `56fc8fb`

What exists:
- Laravel 13 + Inertia.js + Vue 3 + Tailwind CSS 4 (via `@tailwindcss/vite`)
- Laravel Breeze auth (login, register, password reset, email verification)
- `events`, `event_collaborators`, `event_invitations`, `event_plans` tables + models
- `EventPolicy` (owner/editor/viewer roles)
- Event CRUD, duplication, collaborator management, email invitation flow
- Event plans: each event auto-gets "Plan 1"; plans copy on event duplication
- Vue pages: `Dashboard`, `Events/Create`, `Events/Edit`, `Events/Show` (stub — map editor placeholder)
- Sanctum already in `composer.json` (no install needed)
- `routes/api.php` does NOT exist yet — Plan 2 creates it

**Plan 2 — Map Editor: ✅ COMPLETE**

All 63 tests pass. Git HEAD: `9fd869b`

What was added:
- `map_elements` table + `MapElement` model
- `EventPlan` API (create, rename, duplicate, delete) — `routes/api.php` with session auth
- `MapElement` API (index per plan, create plan-scoped/shared, update, delete)
- Element type registry: `config/map_elements.php` + `resources/js/config/elementTypes.js`
- Layer registry: `resources/js/config/mapLayers.js` (OSM vector + Vlaanderen satellite WMTS)
- Composables: `useUndoStack.js`, `useMapElements.js`
- Vue components: `MapEditor.vue`, `LayerSwitcher.vue`, `DrawToolbar.vue`, `PlanSwitcher.vue`, `ElementSidebar.vue`, `PropertiesPanel.vue`
- Custom draw mode: `DrawRectangleMode.js` (two-click rectangle for infrastructure)
- Full map editor wired in `Events/Show.vue` + `EventController::show()` updated to pass plans
- `routes/api.php` uses `auth` middleware (session-based, NOT `auth:sanctum`)
- `EventPolicy` has separate `update()` (owner-only) and `editContent()` (owner+editor) methods

**Plan 3 — Export & Overlays: ✅ COMPLETE**

All 74 tests pass. Git HEAD: `803b7f9`

What was added:
- `map_overlays` table + `MapOverlay` model + factory
- `Event::overlaysForPlan()` — same shared/plan-scoped pattern as elements
- Overlay API: upload (plan-scoped or shared), list per plan (with `image_url`), update bounds/opacity, delete (removes file from storage)
- CSV export: `GET /api/plans/{plan}/export/csv` — streams RFC 4180 CSV via `fputcsv`, includes shared elements
- `OverlayManager.vue` — upload, opacity slider, delete; integrated into MapEditor sidebar
- `useMapExport.js` — PNG export with temporary `devicePixelRatio` override for print quality
- MapEditor toolbar: Export PNG, Print PNG, Export CSV buttons
- Overlay rendering: MapLibre `ImageSource` layers per overlay, removed on delete
- Event duplication now copies: plans → elements (plan-scoped + shared) → overlays (with file copy via `Storage::disk('public')->copy()`)

**Plan 4 — Groups (Hierarchical Elements): ✅ COMPLETE**

All 80 tests pass. Git HEAD: `e2c3fa5`

What was added:
- `parent_id` nullable FK (self-referencing) on `map_elements`; `type` column widened from enum to varchar(50) to accept `'group'`
- `properties` nullable JSON column on `event_plans`
- `group` element type added to PHP registry (`config/map_elements.php`) and JS registry (`elementTypes.js`)
- `MapElement` model: `parent_id` in `$fillable`, `parent()`/`children()` relations, `copyCollection()` static two-pass helper
- `EventPlan` model: `properties` in `$fillable` + cast as array
- `StoreMapElementRequest` / `UpdateMapElementRequest`: `parent_id` validation with group-nesting guard (groups cannot have a parent) and event-scoped `exists` check
- `EventPlanController::update()` accepts `properties`; `duplicate()` uses `MapElement::copyCollection()`
- `EventController::duplicate()` refactored to use `MapElement::copyCollection()` for both plan-scoped and shared elements
- `DrawToolbar.vue`: Group draw button emitting `{ mode: 'group', subtype: null }`
- `MapEditor.vue`: `expandedGroupIds` (ref([])), `activePlan` computed, `toggleGroupExpansion()`, `renderElements()` hides children of collapsed groups, `onDrawCreate` auto-assigns `parent_id` when a group is selected, split right panel (`PropertiesPanel` vs `PlanPropertiesPanel`)
- `ElementSidebar.vue`: Groups section with collapsible children (indented with `border-l-2 border-indigo-200`); ungrouped elements in type buckets below
- `EquipmentList.vue`: reusable add/edit/remove component using `crypto.randomUUID()` for IDs
- `PropertiesPanel.vue`: element-only panel; Level field (shown when `parent_id != null`); EquipmentList for element equipment; subtype hidden for groups
- `PlanPropertiesPanel.vue`: plan name + EquipmentList for plan-level equipment; shown when no element is selected
- `php artisan storage:link` already run (symlink exists at `public/storage`)

---

## Execution order

1. Execute **Plan 2** first: `docs/superpowers/plans/2026-05-22-02-map-editor.md`
2. Execute **Plan 3** after Plan 2 is complete: `docs/superpowers/plans/2026-05-22-03-export-and-overlays.md`

---

## Environment

- **Working directory:** `/Users/kilian/Documents/projects/raceplanner`
- **Tmp folder:** `tmp/` inside the project root (gitignored). Use this for scratch files. Do NOT use `/tmp`.
- **Tests:** `php artisan test` — SQLite, runs in under 2 s
- **Build:** `npm run build` — Vite, run after any Vue/JS changes
- **Dev server:** `php artisan serve` (port 8000) + `npm run dev` (Vite HMR)
- **Database:** SQLite for tests (`database/testing.sqlite` auto-created). MariaDB for dev — do NOT hardcode credentials; ask the user if `.env` DB config is needed.
- **Mail:** `MAIL_MAILER=log` is sufficient for tests (invitation mails go to `storage/logs/laravel.log`)

## Key npm packages (already installed after Plan 1 session)

```
maplibre-gl  @mapbox/mapbox-gl-draw  @turf/turf
```

These were approved and installed. Verify with `npm ls maplibre-gl` before assuming they're present.

---

## Critical implementation notes for Plan 2

These corrections were made during design and must be followed exactly:

### 1. `useMapElements.js` — use `toValue()` for reactive plan ID

The composable receives `planIdRef` (a Vue ref, not a raw number). All API URL template strings must use `toValue(planIdRef)`:

```js
import { toValue } from 'vue'

export function useMapElements(planIdRef) {
  async function fetchElements() {
    const id = toValue(planIdRef)   // NOT: planIdRef.value or planIdRef directly
    const res = await axios.get(`/api/plans/${id}/elements`)
    // ...
  }
}
```

Passing a raw ref would render as `[object Object]` in the URL.

### 2. North-reset button — wrap in a function

`map` is a plain JS variable (not a Vue ref), inaccessible directly in the template. Use a wrapper:

```js
// In MapEditor.vue <script setup>
function resetNorth() {
  map.resetNorth()
}
```

Then in template: `@click="resetNorth"` — not `@click="map.resetNorth()"`.

### 3. DrawRectangleMode.js — custom two-click rectangle

The plan includes `resources/js/Components/Map/DrawRectangleMode.js` as a custom `@mapbox/mapbox-gl-draw` mode for infrastructure elements. It captures two clicks (corner to corner), generates a rectangle polygon, then fires `draw.create`. Follow the plan's task 11b exactly.

### 4. Element plan scoping — NULL means shared

When loading elements for a plan, the query is:
```
WHERE event_plan_id = {planId} OR (event_id = {eventId} AND event_plan_id IS NULL)
```

New elements default to `event_plan_id = NULL` (shared across all plans). The properties panel shows a "Plan" dropdown to assign an element to a specific plan.

### 5. Extensibility — no hardcoded switch/case

Element types and map layers must be driven entirely by the registries:
- `config/map_elements.php` (PHP validation)
- `resources/js/config/elementTypes.js` (JS UI)
- `resources/js/config/mapLayers.js` (JS UI)

The `DrawToolbar`, `LayerSwitcher`, and `MapElementController` must read from these registries. No `if/switch` on element type anywhere except the registry-driven rendering.

---

## Critical implementation notes for Plan 3

### 1. Storage link

Plan 3 uploads overlay images to Laravel storage. Run once before testing:
```bash
php artisan storage:link
```

This creates a symlink `public/storage → storage/app/public`. Safe to run — no download involved.

### 2. Event duplication must copy overlay files

When an event is duplicated (`EventController::duplicate()`), overlay image files must be physically copied in storage (not just the DB record). Use `Storage::copy()`:

```php
$newPath = 'overlays/' . basename($overlay->image_path);
Storage::copy($overlay->image_path, $newPath);
```

This was specified in the original design but easy to miss.

### 3. PNG export is client-side only

No API route needed. `map.getCanvas().toDataURL('image/png')` captures the current viewport. For print quality, temporarily set a higher `devicePixelRatio` before capture, then restore.

---

## Running all tests

```bash
php artisan test
```

Expected after Plan 1: **48 tests, 48 passed**  
Expected after Plan 2: more tests added for map element and event plan APIs  
Expected after Plan 3: more tests added for overlays and CSV export

All tests must pass before marking a plan complete.
