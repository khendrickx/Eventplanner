<?php

namespace Tests\Feature;

use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_unknown_email_sends_invitation_mail(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->post("/events/{$event->id}/collaborators", [
            'email' => 'new@example.com',
            'role' => 'editor',
        ]);

        Mail::assertSent(EventInvitationMail::class, fn ($mail) =>
            $mail->hasTo('new@example.com')
        );
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'email' => 'new@example.com',
            'role' => 'editor',
        ]);
    }

    public function test_visiting_expired_token_shows_error(): void
    {
        $invitation = EventInvitation::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get("/invitations/{$invitation->token}");

        $response->assertInertia(fn ($page) => $page
            ->component('Invitations/Expired')
        );
    }

    public function test_authenticated_user_visiting_valid_token_gets_added_immediately(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $invitation = EventInvitation::factory()->create([
            'event_id' => $event->id,
            'email' => $user->email,
            'role' => 'editor',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get("/invitations/{$invitation->token}");

        $response->assertRedirect('/');
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseMissing('event_invitations', ['id' => $invitation->id]);
    }

    public function test_pending_invitations_are_accepted_when_user_registers(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventInvitation::factory()->create([
            'event_id' => $event->id,
            'email' => 'invited@example.com',
            'role' => 'editor',
            'expires_at' => now()->addDays(7),
        ]);

        $this->post('/register', [
            'name' => 'New User',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $newUser = User::where('email', 'invited@example.com')->first();
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $newUser->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseMissing('event_invitations', ['email' => 'invited@example.com']);
    }

    public function test_register_page_prefills_email_from_invitation_token(): void
    {
        $invitation = EventInvitation::factory()->create([
            'email' => 'invited@example.com',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get("/register?invitation={$invitation->token}");

        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Register')
            ->where('invitationEmail', 'invited@example.com')
        );
    }
}
