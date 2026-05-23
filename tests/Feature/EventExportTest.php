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
