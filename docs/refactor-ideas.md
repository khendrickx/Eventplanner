# Refactor Ideas

Identified after Plan 4 (Groups). These are improvement proposals — none are blocking.

---

## 1. Decompose `MapEditor.vue` (870 lines)

**Problem:** A single component handles map init, 4 draw modes, element CRUD, undo/redo, GPX import, PNG export, route distance tracking, plan switching, draw-edit lifecycle, layer control, and coordinate display.

**Proposal:** Extract into composables that receive `map`/`draw` as arguments:

- `useMapDraw(map, draw, canEdit)` — draw lifecycle, `onDrawCreate/Update/Delete/Selection`, `startDraw()`, `enterDrawEdit()`, `exitDrawEdit()`
- `useRouteDistanceTracker(map)` — mouse tracking for live distance label
- `useGpxImport(eventId, activePlanId)` — GPX parsing and element creation

`MapEditor.vue` becomes an orchestrator of ~200 lines.

---

## 2. Replace imperative `renderElements()` calls with a watcher

**Problem:** `renderElements()` is called in ~15 places (after CRUD, undo, plan switch, draw-edit exit, lock/hide, GPX import, etc.). Forgetting one call leaves the map stale.

**Proposal:** Replace with:

```js
watch(elements, renderElements, { deep: true })
```

`elements` is a Vue ref, so any mutation triggers re-render automatically. The draw-edit case (elements temporarily hidden from the source) can be handled by a separate `watch(drawEditingId, ...)`. Eliminates ~12 manual `renderElements()` calls.

---

## 3. Dual element type registry drift (PHP + JS)

**Problem:** `config/map_elements.php` and `resources/js/config/elementTypes.js` define the same element types independently. Adding a type requires editing both files; there's no safety net.

**Proposal (low effort):** Add a test that the two registries' `id` sets match exactly — catches drift at CI time without a build step.

**Proposal (higher effort):** Make one the single source of truth. Either expose a `GET /api/element-types` JSON endpoint consumed by JS, or write a `php artisan elements:sync` command that regenerates `map_elements.php` from the JS registry.

---

## 4. Extract `EventController::duplicate()` to a service

**Problem:** Duplication logic (~25 lines: copying plans, elements, ID remapping) lives directly in the controller — not reusable, not unit-testable in isolation.

**Proposal:** `app/Services/EventDuplicator.php`:

```php
class EventDuplicator {
    public function duplicate(Event $source, User $owner): Event { ... }
}
```

Inject into `EventController` via constructor. The service is independently testable and reusable if a bulk-duplicate endpoint is ever added.

---

## 5. Public share password session key scattered across 3 files

**Problem:** `"public_event_{$id}"` is constructed identically in `PublicEventController`, `PublicApiController`, and tests. A typo silently breaks password gating.

**Proposal:** `app/Support/PublicShareSession.php`:

```php
class PublicShareSession {
    public static function key(int $eventId): string {
        return "public_event_{$eventId}";
    }
    public static function authorize(int $eventId): void {
        session([self::key($eventId) => true]);
    }
    public static function isAuthorized(int $eventId): bool {
        return session()->has(self::key($eventId));
    }
}
```

---

## 6. `Event::roleFor()` + `Event::isAccessibleBy()` issue double DB queries

**Problem:** Both methods call `$this->collaborators()` (with parentheses — always a new query builder, never uses the cached relation). When both are called in the same request, you get two identical `SELECT` statements.

**Proposal:** Change both methods to `$this->collaborators` (without parentheses) so Laravel's relation cache is used after the first load. Or eager-load in the controller: `$event->load('collaborators')` before the policy check.
