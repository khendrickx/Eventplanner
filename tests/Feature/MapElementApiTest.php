<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPlan;
use App\Models\MapElement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapElementApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeGeometry(): array
    {
        return ['type' => 'Point', 'coordinates' => [4.35, 50.85]];
    }

    public function test_editor_can_list_elements_for_plan(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        // plan-specific element
        MapElement::factory()->create(['event_id' => $event->id, 'event_plan_id' => $plan->id]);
        // shared element (no plan)
        MapElement::factory()->create(['event_id' => $event->id, 'event_plan_id' => null]);

        $response = $this->actingAs($user)->getJson("/api/plans/{$plan->id}/elements");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_shared_element_does_not_appear_in_wrong_event(): void
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create(['user_id' => $user->id]);
        $event2 = Event::factory()->create(['user_id' => $user->id]);
        $plan1 = $event1->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        $plan2 = $event2->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        MapElement::factory()->create(['event_id' => $event1->id, 'event_plan_id' => null]);

        $response = $this->actingAs($user)->getJson("/api/plans/{$plan2->id}/elements");

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_editor_can_create_element(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $response = $this->actingAs($user)->postJson("/api/plans/{$plan->id}/elements", [
            'type' => 'marker',
            'subtype' => 'start',
            'name' => 'Start Line',
            'geometry' => $this->makeGeometry(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('map_elements', [
            'type' => 'marker',
            'subtype' => 'start',
            'event_plan_id' => $plan->id,
        ]);
    }

    public function test_element_defaults_to_no_plan_when_created_via_event_route(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/events/{$event->id}/elements", [
            'type' => 'marker',
            'subtype' => 'buoy',
            'geometry' => $this->makeGeometry(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('map_elements', [
            'event_id' => $event->id,
            'event_plan_id' => null,
        ]);
    }

    public function test_viewer_cannot_create_element(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
        $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->postJson("/api/plans/{$plan->id}/elements", [
            'type' => 'marker',
            'subtype' => 'start',
            'geometry' => $this->makeGeometry(),
        ]);

        $response->assertForbidden();
    }

    public function test_editor_can_update_element(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $element = MapElement::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($user)->patchJson("/api/elements/{$element->id}", [
            'name' => 'Updated Name',
            'notes' => 'Some notes',
            'is_locked' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('map_elements', [
            'id' => $element->id,
            'name' => 'Updated Name',
            'is_locked' => true,
        ]);
    }

    public function test_viewer_cannot_update_element(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $element = MapElement::factory()->create(['event_id' => $event->id]);
        $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->patchJson("/api/elements/{$element->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_editor_can_delete_element(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $element = MapElement::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($user)->deleteJson("/api/elements/{$element->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('map_elements', ['id' => $element->id]);
    }

    public function test_invalid_subtype_is_rejected(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $response = $this->actingAs($user)->postJson("/api/plans/{$plan->id}/elements", [
            'type' => 'marker',
            'subtype' => 'invalid_subtype',
            'geometry' => $this->makeGeometry(),
        ]);

        $response->assertUnprocessable();
    }
}
