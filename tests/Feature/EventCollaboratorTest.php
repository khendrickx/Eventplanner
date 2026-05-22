<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCollaboratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_existing_user_as_collaborator(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create(['email' => 'co@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post("/events/{$event->id}/collaborators", [
            'email' => 'co@example.com',
            'role' => 'editor',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);
    }

    public function test_non_owner_cannot_add_collaborator(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($editor->id, ['role' => 'editor']);

        $response = $this->actingAs($editor)->post("/events/{$event->id}/collaborators", [
            'email' => 'stranger@example.com',
            'role' => 'viewer',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_update_collaborator_role(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($collaborator->id, ['role' => 'viewer']);

        $response = $this->actingAs($owner)->patch(
            "/events/{$event->id}/collaborators/{$collaborator->id}",
            ['role' => 'editor']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);
    }

    public function test_owner_can_remove_collaborator(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($collaborator->id, ['role' => 'viewer']);

        $response = $this->actingAs($owner)->delete(
            "/events/{$event->id}/collaborators/{$collaborator->id}"
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
        ]);
    }
}
