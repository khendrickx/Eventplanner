# Export & Overlays — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add PNG export (client-side), CSV export (server-side), and image overlay upload/placement to the map editor.

**Architecture:** PNG export uses MapLibre's canvas API client-side — no server round-trip. CSV export is a streamed Laravel download. Overlays are uploaded to Laravel storage, stored in `map_overlays`, and rendered as MapLibre `ImageSource` layers.

**Tech Stack:** MapLibre GL JS, Laravel filesystem (local storage), Vue 3, Laravel 13

**Depends on:** Plan 2 (Map Editor) must be complete.

---

## File Map

**Created:**
- `database/migrations/..._create_map_overlays_table.php`
- `app/Models/MapOverlay.php`
- `database/factories/MapOverlayFactory.php`
- `app/Http/Controllers/Api/MapOverlayController.php`
- `app/Http/Controllers/Api/EventExportController.php`
- `resources/js/Components/Map/OverlayManager.vue`
- `resources/js/composables/useMapExport.js`
- `tests/Feature/MapOverlayTest.php`
- `tests/Feature/EventExportTest.php`

**Modified:**
- `routes/api.php`
- `resources/js/Components/Map/MapEditor.vue` — integrate overlay rendering and export button
- `app/Models/EventPlan.php` — add overlays relationship
- `app/Models/Event.php` — add overlays relationship

---

### Task 1: map_overlays migration and model

**Files:**
- Create: `database/migrations/..._create_map_overlays_table.php`
- Create: `app/Models/MapOverlay.php`
- Create: `database/factories/MapOverlayFactory.php`
- Modify: `app/Models/EventPlan.php`
- Modify: `app/Models/Event.php`

- [ ] **Step 1: Generate migration**

```bash
php artisan make:migration create_map_overlays_table
```

- [ ] **Step 2: Write migration**

```php
public function up(): void
{
    Schema::create('map_overlays', function (Blueprint $table) {
        $table->id();
        $table->foreignId('event_id')->constrained()->cascadeOnDelete();
        $table->foreignId('event_plan_id')->nullable()->constrained('event_plans')->nullOnDelete();
        $table->string('name');
        $table->string('image_path');
        $table->json('bounds'); // [[sw_lng, sw_lat], [ne_lng, ne_lat]]
        $table->float('opacity')->default(1.0);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Write MapOverlay model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapOverlay extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'image_path', 'bounds', 'opacity', 'sort_order', 'event_plan_id'];

    protected $casts = ['bounds' => 'array'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EventPlan::class, 'event_plan_id');
    }
}
```

- [ ] **Step 5: Add relationships**

In `app/Models/EventPlan.php`:
```php
public function overlays(): HasMany
{
    return $this->hasMany(MapOverlay::class, 'event_plan_id');
}
```

In `app/Models/Event.php`:
```php
public function overlays(): HasMany
{
    return $this->hasMany(MapOverlay::class);
}

public function overlaysForPlan(int $planId): \Illuminate\Database\Eloquent\Collection
{
    return $this->overlays()
        ->where(fn ($q) => $q
            ->where('event_plan_id', $planId)
            ->orWhereNull('event_plan_id')
        )
        ->orderBy('sort_order')
        ->get();
}
```

- [ ] **Step 6: Write factory**

```bash
php artisan make:factory MapOverlayFactory --model=MapOverlay
```

```php
public function definition(): array
{
    return [
        'event_id' => Event::factory(),
        'event_plan_id' => null,
        'name' => 'Overlay ' . fake()->word(),
        'image_path' => 'overlays/test.png',
        'bounds' => [[4.3, 50.8], [4.4, 50.9]],
        'opacity' => 1.0,
        'sort_order' => 0,
    ];
}
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/ app/Models/MapOverlay.php database/factories/MapOverlayFactory.php app/Models/EventPlan.php app/Models/Event.php
git commit -m "feat: map_overlays migration and model"
```

---

### Task 2: Overlay API — tests then controller

**Files:**
- Create: `tests/Feature/MapOverlayTest.php`
- Create: `app/Http/Controllers/Api/MapOverlayController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapOverlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MapOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_upload_overlay(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $response = $this->actingAs($user)->postJson("/api/plans/{$plan->id}/overlays", [
            'name' => 'Venue Map',
            'image' => UploadedFile::fake()->image('venue.png', 800, 600),
            'bounds' => [[4.3, 50.8], [4.4, 50.9]],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('map_overlays', [
            'event_id' => $event->id,
            'event_plan_id' => $plan->id,
            'name' => 'Venue Map',
        ]);
        $overlay = MapOverlay::first();
        Storage::disk('public')->assertExists($overlay->image_path);
    }

    public function test_viewer_cannot_upload_overlay(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->postJson("/api/plans/{$plan->id}/overlays", [
            'name' => 'Sneaky',
            'image' => UploadedFile::fake()->image('x.png'),
            'bounds' => [[4.3, 50.8], [4.4, 50.9]],
        ]);

        $response->assertForbidden();
    }

    public function test_editor_can_update_overlay_bounds_and_opacity(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $overlay = MapOverlay::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($user)->patchJson("/api/overlays/{$overlay->id}", [
            'bounds' => [[4.35, 50.85], [4.45, 50.95]],
            'opacity' => 0.5,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('map_overlays', [
            'id' => $overlay->id,
            'opacity' => 0.5,
        ]);
    }

    public function test_editor_can_delete_overlay(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('overlays/test.png', 'fake image content');
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $overlay = MapOverlay::factory()->create([
            'event_id' => $event->id,
            'image_path' => 'overlays/test.png',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/overlays/{$overlay->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('map_overlays', ['id' => $overlay->id]);
        Storage::disk('public')->assertMissing('overlays/test.png');
    }
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/MapOverlayTest.php
```

- [ ] **Step 3: Write MapOverlayController**

```bash
php artisan make:controller Api/MapOverlayController
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapOverlay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MapOverlayController extends Controller
{
    public function storeForPlan(Request $request, EventPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan->event);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'file', 'image', 'max:10240'],
            'bounds' => ['required', 'array'],
        ]);

        $path = $request->file('image')->store('overlays', 'public');
        $sortOrder = $plan->event->overlays()->max('sort_order') + 1;

        $overlay = $plan->event->overlays()->create([
            'event_plan_id' => $plan->id,
            'name' => $request->name,
            'image_path' => $path,
            'bounds' => $request->bounds,
            'opacity' => 1.0,
            'sort_order' => $sortOrder,
        ]);

        return response()->json($overlay, 201);
    }

    public function storeShared(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'file', 'image', 'max:10240'],
            'bounds' => ['required', 'array'],
        ]);

        $path = $request->file('image')->store('overlays', 'public');
        $sortOrder = $event->overlays()->max('sort_order') + 1;

        $overlay = $event->overlays()->create([
            'event_plan_id' => null,
            'name' => $request->name,
            'image_path' => $path,
            'bounds' => $request->bounds,
            'opacity' => 1.0,
            'sort_order' => $sortOrder,
        ]);

        return response()->json($overlay, 201);
    }

    public function update(Request $request, MapOverlay $overlay): JsonResponse
    {
        $this->authorize('update', $overlay->event);

        $request->validate([
            'bounds' => ['sometimes', 'array'],
            'opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $overlay->update($request->only(['bounds', 'opacity', 'name']));
        return response()->json($overlay);
    }

    public function destroy(MapOverlay $overlay): JsonResponse
    {
        $this->authorize('update', $overlay->event);

        Storage::disk('public')->delete($overlay->image_path);
        $overlay->delete();

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 4: Add overlay routes**

In `routes/api.php`:

```php
use App\Http\Controllers\Api\MapOverlayController;

Route::middleware('auth')->group(function () {
    // ... existing routes ...
    Route::post('plans/{plan}/overlays', [MapOverlayController::class, 'storeForPlan']);
    Route::post('events/{event}/overlays', [MapOverlayController::class, 'storeShared']);
    Route::patch('overlays/{overlay}', [MapOverlayController::class, 'update']);
    Route::delete('overlays/{overlay}', [MapOverlayController::class, 'destroy']);
});
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/MapOverlayTest.php
```

Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/MapOverlayController.php routes/api.php tests/Feature/MapOverlayTest.php
git commit -m "feat: overlay API (upload, update bounds/opacity, delete)"
```

---

### Task 3: CSV export — tests then controller

**Files:**
- Create: `tests/Feature/EventExportTest.php`
- Create: `app/Http/Controllers/Api/EventExportController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapElement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_csv_for_plan(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        MapElement::factory()->create([
            'event_id' => $event->id,
            'event_plan_id' => $plan->id,
            'type' => 'marker',
            'subtype' => 'start',
            'name' => 'Start Line',
            'notes' => 'North end of the field',
            'geometry' => ['type' => 'Point', 'coordinates' => [4.35, 50.85]],
        ]);

        // shared element (no plan) should also appear
        MapElement::factory()->create([
            'event_id' => $event->id,
            'event_plan_id' => null,
            'type' => 'zone',
            'subtype' => 'parking_zone',
            'name' => 'Parking A',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[4.3, 50.8], [4.4, 50.8], [4.4, 50.9], [4.3, 50.9], [4.3, 50.8]]]],
        ]);

        $response = $this->actingAs($user)->get("/api/plans/{$plan->id}/export/csv");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->getContent();
        $this->assertStringContainsString('Start Line', $content);
        $this->assertStringContainsString('Parking A', $content);
        $this->assertStringContainsString('North end of the field', $content);
    }

    public function test_viewer_can_export_csv(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->get("/api/plans/{$plan->id}/export/csv");

        $response->assertOk();
    }

    public function test_stranger_cannot_export_csv(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $response = $this->actingAs($stranger)->get("/api/plans/{$plan->id}/export/csv");

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventExportTest.php
```

- [ ] **Step 3: Write EventExportController**

```bash
php artisan make:controller Api/EventExportController
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventPlan;
use Illuminate\Http\Response;

class EventExportController extends Controller
{
    public function csv(EventPlan $plan): Response
    {
        $this->authorize('view', $plan->event);

        $elements = $plan->event->elementsForPlan($plan->id);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $plan->event->name . ' - ' . $plan->name . '.csv"',
        ];

        $csv = implode(',', ['id', 'type', 'subtype', 'name', 'notes', 'geometry_type', 'coordinates', 'width', 'length', 'rotation', 'fill_color', 'stroke_color', 'plan']) . "\n";

        foreach ($elements as $el) {
            $props = $el->properties ?? [];
            $styling = $props['styling'] ?? [];
            $csv .= implode(',', [
                $el->id,
                $el->type,
                $el->subtype ?? '',
                $this->escapeCsv($el->name ?? ''),
                $this->escapeCsv($el->notes ?? ''),
                $el->geometry['type'] ?? '',
                $this->escapeCsv(json_encode($el->geometry['coordinates'] ?? [])),
                $props['width'] ?? '',
                $props['length'] ?? '',
                $props['rotation'] ?? '',
                $styling['fill_color'] ?? '',
                $styling['stroke_color'] ?? '',
                $el->event_plan_id ? $plan->name : 'shared',
            ]) . "\n";
        }

        return response($csv, 200, $headers);
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
```

- [ ] **Step 4: Add export route**

In `routes/api.php`:

```php
use App\Http\Controllers\Api\EventExportController;

Route::middleware('auth')->group(function () {
    // ... existing routes ...
    Route::get('plans/{plan}/export/csv', [EventExportController::class, 'csv']);
});
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/EventExportTest.php
```

Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/EventExportController.php routes/api.php tests/Feature/EventExportTest.php
git commit -m "feat: CSV export for plan elements (includes shared elements)"
```

---

### Task 4: OverlayManager component

**Files:**
- Create: `resources/js/Components/Map/OverlayManager.vue`

- [ ] **Step 1: Write OverlayManager.vue**

```vue
<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    eventId: { type: Number, required: true },
    planId: { type: Number, required: true },
    overlays: { type: Array, required: true },
    canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['added', 'updated', 'deleted'])

const uploading = ref(false)
const fileInput = ref(null)
const newOverlayName = ref('')

async function upload() {
    const file = fileInput.value?.files[0]
    if (!file || !newOverlayName.value.trim()) return

    uploading.value = true
    try {
        const form = new FormData()
        form.append('name', newOverlayName.value)
        form.append('image', file)
        // default bounds: current map viewport (passed in as prop or use a fallback)
        form.append('bounds[0][0]', '4.3')
        form.append('bounds[0][1]', '50.8')
        form.append('bounds[1][0]', '4.4')
        form.append('bounds[1][1]', '50.9')

        const { data } = await axios.post(`/api/plans/${props.planId}/overlays`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        newOverlayName.value = ''
        fileInput.value.value = ''
        emit('added', data)
    } finally {
        uploading.value = false
    }
}

async function updateOpacity(overlay, opacity) {
    const { data } = await axios.patch(`/api/overlays/${overlay.id}`, { opacity })
    emit('updated', data)
}

async function deleteOverlay(overlay) {
    if (!confirm(`Remove overlay "${overlay.name}"?`)) return
    await axios.delete(`/api/overlays/${overlay.id}`)
    emit('deleted', overlay.id)
}
</script>

<template>
    <div class="p-3 space-y-3 text-sm">
        <h3 class="font-medium text-xs text-gray-500 uppercase tracking-wide">Overlays</h3>

        <div v-for="overlay in overlays" :key="overlay.id" class="border rounded p-2 space-y-1">
            <div class="flex items-center justify-between">
                <span class="font-medium truncate">{{ overlay.name }}</span>
                <button v-if="canEdit" @click="deleteOverlay(overlay)" class="text-red-400 hover:text-red-600 text-xs">×</button>
            </div>
            <div v-if="canEdit" class="flex items-center gap-2">
                <label class="text-xs text-gray-500">Opacity</label>
                <input
                    type="range" min="0" max="1" step="0.05"
                    :value="overlay.opacity"
                    @change="updateOpacity(overlay, parseFloat($event.target.value))"
                    class="w-full"
                />
                <span class="text-xs w-8 text-right">{{ Math.round(overlay.opacity * 100) }}%</span>
            </div>
        </div>

        <div v-if="canEdit" class="space-y-1">
            <input v-model="newOverlayName" placeholder="Overlay name" class="w-full border rounded px-2 py-1 text-xs" />
            <input ref="fileInput" type="file" accept="image/*" class="w-full text-xs" />
            <button
                @click="upload"
                :disabled="uploading"
                class="w-full py-1.5 bg-black text-white rounded text-xs disabled:opacity-50"
            >
                {{ uploading ? 'Uploading…' : 'Upload Overlay' }}
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/Map/OverlayManager.vue
git commit -m "feat: OverlayManager component for image overlay upload and opacity control"
```

---

### Task 5: PNG export composable and button

**Files:**
- Create: `resources/js/composables/useMapExport.js`
- Modify: `resources/js/Components/Map/MapEditor.vue`

- [ ] **Step 1: Write useMapExport.js**

```js
// resources/js/composables/useMapExport.js

export function useMapExport(getMap) {
    function exportPng({ print = false } = {}) {
        const map = getMap()
        if (!map) return

        const dpr = print ? 3 : 1
        const originalDpr = window.devicePixelRatio

        // Temporarily override pixel ratio for high-res capture
        Object.defineProperty(window, 'devicePixelRatio', {
            get: () => dpr,
            configurable: true,
        })

        map.once('render', () => {
            const canvas = map.getCanvas()
            const url = canvas.toDataURL('image/png')

            const a = document.createElement('a')
            a.href = url
            a.download = print ? 'map-print.png' : 'map-digital.png'
            a.click()

            // Restore
            Object.defineProperty(window, 'devicePixelRatio', {
                get: () => originalDpr,
                configurable: true,
            })
        })

        map.triggerRepaint()
    }

    return { exportPng }
}
```

- [ ] **Step 2: Add export button to MapEditor.vue toolbar**

In `resources/js/Components/Map/MapEditor.vue`, add to the `<script setup>`:

```js
import { useMapExport } from '@/composables/useMapExport.js'

const { exportPng } = useMapExport(() => map)
```

Add to the toolbar in `<template>`, after the undo button:

```html
<div class="w-px h-5 bg-gray-200 shrink-0"></div>
<div class="flex gap-1">
    <button @click="exportPng()" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50">
        Export PNG
    </button>
    <button @click="exportPng({ print: true })" class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50">
        Print PNG
    </button>
</div>
```

- [ ] **Step 3: Add CSV export link to toolbar**

```html
<a
    :href="`/api/plans/${activePlanId}/export/csv`"
    class="text-xs border rounded px-2 py-1.5 hover:bg-gray-50"
    download
>Export CSV</a>
```

- [ ] **Step 4: Integrate overlays into MapEditor**

In `MapEditor.vue`, add overlay state and rendering. In `<script setup>`:

```js
import OverlayManager from './OverlayManager.vue'
import axios from 'axios'

const overlays = ref([])

async function loadOverlays() {
    const { data } = await axios.get(`/api/plans/${activePlanId.value}/overlays`)
    overlays.value = data.data
}

function renderOverlays() {
    if (!map.isStyleLoaded()) return
    overlays.value.forEach(overlay => {
        const sourceId = `overlay-${overlay.id}`
        const layerId = `overlay-layer-${overlay.id}`
        const imageUrl = `/storage/${overlay.image_path}`
        const coords = [
            [overlay.bounds[0][0], overlay.bounds[1][1]], // NW
            [overlay.bounds[1][0], overlay.bounds[1][1]], // NE
            [overlay.bounds[1][0], overlay.bounds[0][1]], // SE
            [overlay.bounds[0][0], overlay.bounds[0][1]], // SW
        ]
        if (!map.getSource(sourceId)) {
            map.addSource(sourceId, { type: 'image', url: imageUrl, coordinates: coords })
            map.addLayer({ id: layerId, type: 'raster', source: sourceId, paint: { 'raster-opacity': overlay.opacity } })
        } else {
            map.setPaintProperty(layerId, 'raster-opacity', overlay.opacity)
        }
    })
}
```

Call `loadOverlays()` alongside `load()` in `onMounted` and `switchPlan`.

Add `OverlayManager` to the sidebar or toolbar:
```html
<!-- Add below ElementSidebar in the body section -->
<div class="w-56 border-r bg-white overflow-y-auto shrink-0">
    <OverlayManager
        :event-id="event.id"
        :plan-id="activePlanId"
        :overlays="overlays"
        :can-edit="canEdit"
        @added="o => { overlays.push(o); renderOverlays() }"
        @updated="o => { const i = overlays.findIndex(x => x.id === o.id); if (i !== -1) { overlays[i] = o; renderOverlays() } }"
        @deleted="id => { overlays = overlays.filter(o => o.id !== id) }"
    />
</div>
```

Also add overlay API route to `routes/api.php`:
```php
Route::get('plans/{plan}/overlays', [MapOverlayController::class, 'indexForPlan']);
```

And add `indexForPlan` to `MapOverlayController`:
```php
public function indexForPlan(EventPlan $plan): JsonResponse
{
    $this->authorize('view', $plan->event);
    $overlays = $plan->event->overlaysForPlan($plan->id)->map(fn ($o) => [
        ...$o->toArray(),
        'image_url' => asset('storage/' . $o->image_path),
    ]);
    return response()->json(['data' => $overlays]);
}
```

- [ ] **Step 5: Run storage link**

```bash
php artisan storage:link
```

- [ ] **Step 6: Build and test in browser**

```bash
npm run build
php artisan serve
```

Verify:
- "Export PNG" downloads the current map view as a PNG
- "Print PNG" downloads a higher-resolution version
- "Export CSV" downloads a CSV with all plan + shared elements
- Uploading an image overlay renders it on the map canvas
- Adjusting opacity changes the overlay transparency

- [ ] **Step 7: Commit**

```bash
git add resources/js/composables/useMapExport.js resources/js/Components/Map/MapEditor.vue resources/js/Components/Map/OverlayManager.vue routes/api.php app/Http/Controllers/Api/MapOverlayController.php
git commit -m "feat: PNG export, CSV export link, overlay rendering with opacity"
```

---

### Task 6: Event duplication copies overlays

**Files:**
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `tests/Feature/EventCrudTest.php`

- [ ] **Step 1: Add failing test**

Append to `tests/Feature/EventCrudTest.php`:

```php
public function test_duplicating_event_copies_overlays(): void
{
    Storage::fake('public');
    Storage::disk('public')->put('overlays/original.png', 'fake');

    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    MapOverlay::factory()->create([
        'event_id' => $event->id,
        'image_path' => 'overlays/original.png',
    ]);

    $this->actingAs($user)->post("/events/{$event->id}/duplicate");

    $this->assertDatabaseCount('map_overlays', 2);
    $copy = Event::where('user_id', $user->id)->where('id', '!=', $event->id)->first();
    $copyOverlay = $copy->overlays()->first();
    $this->assertNotSame('overlays/original.png', $copyOverlay->image_path);
    Storage::disk('public')->assertExists($copyOverlay->image_path);
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventCrudTest.php --filter=overlay
```

- [ ] **Step 3: Update EventController::duplicate() to copy overlays**

```php
public function duplicate(Event $event): RedirectResponse
{
    $this->authorize('duplicate', $event);

    $copy = $event->replicate(['user_id']);
    $copy->user_id = auth()->id();
    $copy->name = $event->name . ' (copy)';
    $copy->save();

    // Copy plans
    $planIdMap = [];
    foreach ($event->plans as $plan) {
        $newPlan = $copy->plans()->create([
            'name' => $plan->name,
            'sort_order' => $plan->sort_order,
        ]);
        $planIdMap[$plan->id] = $newPlan->id;

        // Copy plan-scoped elements
        foreach ($plan->elements as $element) {
            $newEl = $element->replicate(['event_id', 'event_plan_id']);
            $newEl->event_id = $copy->id;
            $newEl->event_plan_id = $newPlan->id;
            $newEl->save();
        }
    }

    // Copy shared elements (event_plan_id = null)
    foreach ($event->elements()->whereNull('event_plan_id')->get() as $element) {
        $newEl = $element->replicate(['event_id', 'event_plan_id']);
        $newEl->event_id = $copy->id;
        $newEl->event_plan_id = null;
        $newEl->save();
    }

    // Copy overlays (duplicate image files)
    foreach ($event->overlays as $overlay) {
        $newPath = 'overlays/' . \Illuminate\Support\Str::uuid() . '.' . pathinfo($overlay->image_path, PATHINFO_EXTENSION);
        \Illuminate\Support\Facades\Storage::disk('public')->copy($overlay->image_path, $newPath);

        $copy->overlays()->create([
            'event_plan_id' => $overlay->event_plan_id ? $planIdMap[$overlay->event_plan_id] : null,
            'name' => $overlay->name,
            'image_path' => $newPath,
            'bounds' => $overlay->bounds,
            'opacity' => $overlay->opacity,
            'sort_order' => $overlay->sort_order,
        ]);
    }

    return redirect()->route('events.show', $copy);
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/EventController.php tests/Feature/EventCrudTest.php
git commit -m "feat: event duplication copies plans, elements, and overlays"
```

---

### Task 7: Run full test suite

- [ ] **Step 1: Run all tests**

```bash
php artisan test
```

Expected: All tests pass across all three plans.

- [ ] **Step 2: Smoke test in browser**

```bash
php artisan serve & npm run dev
```

Walk through the golden path:
1. Register → invitation email pre-fills if token present
2. Create event → default "Plan 1" created
3. Open event → map loads with OSM tiles
4. Switch to Vlaanderen satellite → satellite tiles load
5. Draw a marker → appears in sidebar, properties panel opens
6. Draw a route → length shown in properties panel
7. Draw a zone → area and perimeter shown
8. Draw infrastructure rectangle → width/length fields appear
9. Toggle plan assignment → element disappears from other plan
10. Create second plan → switch between plans
11. Lock an element → can't drag it on the map
12. Hide an element → disappears from canvas
13. Undo → last action reverted
14. Upload overlay → image renders on map
15. Export CSV → file downloaded with correct data
16. Export PNG → map screenshot downloaded
17. Invite collaborator (email found) → added immediately
18. Invite collaborator (new email) → invitation email sent
19. Duplicate event → all plans, elements, overlays copied
