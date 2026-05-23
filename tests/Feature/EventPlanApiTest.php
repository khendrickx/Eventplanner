<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_plans(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $event->plans()->createMany([
            ['name' => 'Plan A', 'sort_order' => 1],
            ['name' => 'Plan B', 'sort_order' => 2],
        ]);

        $response = $this->actingAs($user)->getJson("/api/events/{$event->id}/plans");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_editor_can_create_plan(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($editor->id, ['role' => 'editor']);

        $response = $this->actingAs($editor)->postJson("/api/events/{$event->id}/plans", [
            'name' => 'Wet Weather Plan',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('event_plans', ['name' => 'Wet Weather Plan']);
    }

    public function test_viewer_cannot_create_plan(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->postJson("/api/events/{$event->id}/plans", [
            'name' => 'Sneaky Plan',
        ]);

        $response->assertForbidden();
    }

    public function test_editor_can_rename_plan(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan A', 'sort_order' => 1]);

        $response = $this->actingAs($user)->patchJson("/api/plans/{$plan->id}", [
            'name' => 'Renamed Plan',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('event_plans', ['id' => $plan->id, 'name' => 'Renamed Plan']);
    }

    public function test_cannot_delete_last_plan(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $response = $this->actingAs($user)->deleteJson("/api/plans/{$plan->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('event_plans', ['id' => $plan->id]);
    }

    public function test_editor_can_duplicate_plan(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan A', 'sort_order' => 1]);

        $response = $this->actingAs($user)->postJson("/api/plans/{$plan->id}/duplicate");

        $response->assertCreated();
        $this->assertDatabaseCount('event_plans', 2);
        $this->assertDatabaseHas('event_plans', ['name' => 'Plan A (copy)']);
    }

    public function test_duplicate_plan_preserves_group_hierarchy(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $group = \App\Models\MapElement::factory()->create([
            'event_id' => $event->id, 'event_plan_id' => $plan->id,
            'type' => 'group', 'subtype' => null,
        ]);
        \App\Models\MapElement::factory()->create([
            'event_id' => $event->id, 'event_plan_id' => $plan->id,
            'parent_id' => $group->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/plans/{$plan->id}/duplicate");

        $response->assertCreated();

        $copyPlanId  = $response->json('id');
        $copyElements = \App\Models\MapElement::where('event_plan_id', $copyPlanId)->get();
        $copyGroup   = $copyElements->firstWhere('type', 'group');
        $copyChild   = $copyElements->whereNotNull('parent_id')->first();

        $this->assertNotNull($copyGroup);
        $this->assertNotNull($copyChild);
        $this->assertEquals($copyGroup->id, $copyChild->parent_id);
        $this->assertNotEquals($group->id, $copyGroup->id);
    }

    public function test_owner_can_update_plan_properties(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $equipment = [
            ['id' => 'abc123', 'name' => 'Table', 'quantity' => 4, 'unit' => 'pcs', 'notes' => ''],
        ];

        $response = $this->actingAs($user)->patchJson("/api/plans/{$plan->id}", [
            'properties' => ['equipment' => $equipment],
        ]);

        $response->assertOk();
        $plan->refresh();
        $this->assertEquals($equipment, $plan->properties['equipment']);
    }
}
