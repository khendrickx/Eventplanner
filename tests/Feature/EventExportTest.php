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

    public function test_csv_plan_column_distinguishes_shared_from_plan_specific(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        MapElement::factory()->create([
            'event_id' => $event->id,
            'event_plan_id' => $plan->id,
            'name' => 'Plan Element',
            'geometry' => ['type' => 'Point', 'coordinates' => [4.35, 50.85]],
        ]);
        MapElement::factory()->create([
            'event_id' => $event->id,
            'event_plan_id' => null,
            'name' => 'Shared Element',
            'geometry' => ['type' => 'Point', 'coordinates' => [4.35, 50.85]],
        ]);

        $content = $this->actingAs($user)->get("/api/plans/{$plan->id}/export/csv")->getContent();
        $rows = array_filter(array_map('str_getcsv', explode("\n", trim($content))));
        $rows = array_values($rows);

        // Find plan column index from header
        $header = $rows[0];
        $planIdx = array_search('plan', $header);

        $planValues = array_column(array_slice($rows, 1), $planIdx);
        $this->assertContains('Plan 1', $planValues);
        $this->assertContains('shared', $planValues);
    }

    public function test_csv_escapes_special_characters(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        MapElement::factory()->create([
            'event_id' => $event->id,
            'event_plan_id' => $plan->id,
            'name' => 'Start, Line "A"',
            'notes' => "Line one\nLine two",
            'geometry' => ['type' => 'Point', 'coordinates' => [4.35, 50.85]],
        ]);

        $content = $this->actingAs($user)->get("/api/plans/{$plan->id}/export/csv")->getContent();
        // fputcsv quotes fields with commas/quotes and doubles internal quotes (RFC 4180)
        $this->assertStringContainsString('"Start, Line ""A"""', $content);
        $this->assertStringContainsString('Line one', $content);
    }
}
