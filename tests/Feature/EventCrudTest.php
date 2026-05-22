<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
