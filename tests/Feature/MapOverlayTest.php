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

    public function test_owner_can_list_overlays_for_plan(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        MapOverlay::factory()->create(['event_id' => $event->id, 'event_plan_id' => $plan->id]);
        MapOverlay::factory()->create(['event_id' => $event->id, 'event_plan_id' => null]); // shared

        $response = $this->actingAs($user)->getJson("/api/plans/{$plan->id}/overlays");

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}
