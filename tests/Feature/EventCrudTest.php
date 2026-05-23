<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MapOverlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('events', 1)
        );
    }

    public function test_authenticated_user_can_create_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/events', [
            'name' => 'Tour de France 2026',
            'description' => 'Annual cycling race',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', [
            'name' => 'Tour de France 2026',
            'user_id' => $user->id,
        ]);
    }

    public function test_event_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/events', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_unauthenticated_user_cannot_create_event(): void
    {
        $response = $this->post('/events', ['name' => 'Test']);

        $response->assertRedirect('/login');
    }

    public function test_owner_can_update_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch("/events/{$event->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'name' => 'Updated Name']);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->patch("/events/{$event->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_delete_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/events/{$event->id}");

        $response->assertRedirect('/');
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->delete("/events/{$event->id}");

        $response->assertForbidden();
    }

    public function test_collaborator_can_view_event(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($collaborator->id, ['role' => 'viewer']);

        $response = $this->actingAs($collaborator)->get("/events/{$event->id}");

        $response->assertOk();
    }

    public function test_non_collaborator_cannot_view_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->get("/events/{$event->id}");

        $response->assertForbidden();
    }

    public function test_any_user_with_access_can_duplicate_event(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Race 2026']);
        $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->post("/events/{$event->id}/duplicate");

        $response->assertRedirect();
        $this->assertDatabaseCount('events', 2);
        $newEvent = Event::where('user_id', $viewer->id)->first();
        $this->assertNotNull($newEvent);
        $this->assertSame('Race 2026 (copy)', $newEvent->name);
        $this->assertCount(0, $newEvent->collaborators);
    }

    public function test_stranger_cannot_duplicate_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->post("/events/{$event->id}/duplicate");

        $response->assertForbidden();
    }

    public function test_creating_event_automatically_creates_default_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/events', ['name' => 'Race 2026']);

        $event = Event::where('name', 'Race 2026')->first();
        $this->assertCount(1, $event->plans);
        $this->assertSame('Plan 1', $event->plans->first()->name);
    }

    public function test_duplicating_event_copies_all_plans(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->plans()->create(['name' => 'Plan A', 'sort_order' => 1]);
        $event->plans()->create(['name' => 'Plan B', 'sort_order' => 2]);

        $this->actingAs($owner)->post("/events/{$event->id}/duplicate");

        $copy = Event::where('user_id', $owner->id)->where('id', '!=', $event->id)->first();
        $this->assertCount(2, $copy->plans);
        $this->assertSame('Plan A', $copy->plans->first()->name);
    }

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

    public function test_event_duplicate_preserves_group_hierarchy(): void
    {
        $user  = \App\Models\User::factory()->create();
        $event = \App\Models\Event::factory()->create(['user_id' => $user->id]);
        $plan  = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $group = \App\Models\MapElement::factory()->create([
            'event_id' => $event->id, 'event_plan_id' => $plan->id,
            'type' => 'group', 'subtype' => null,
        ]);
        \App\Models\MapElement::factory()->create([
            'event_id' => $event->id, 'event_plan_id' => $plan->id,
            'parent_id' => $group->id,
        ]);

        $this->actingAs($user)->post(route('events.duplicate', $event))->assertRedirect();

        $copy = \App\Models\Event::where('user_id', $user->id)
            ->where('id', '!=', $event->id)->firstOrFail();

        $copyElements = $copy->elements()->get();
        $copyGroup    = $copyElements->firstWhere('type', 'group');
        $copyChild    = $copyElements->whereNotNull('parent_id')->first();

        $this->assertNotNull($copyGroup);
        $this->assertNotNull($copyChild);
        $this->assertEquals($copyGroup->id, $copyChild->parent_id);
        $this->assertNotEquals($group->id, $copyGroup->id);
    }
}
