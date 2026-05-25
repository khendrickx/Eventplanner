<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_enable_public_link(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('events.share.update', $event), ['enabled' => true])
            ->assertRedirect();

        $this->assertNotNull($event->fresh()->public_token);
    }

    public function test_owner_can_disable_public_link(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id, 'public_token' => 'abc123']);

        $this->actingAs($user)
            ->post(route('events.share.update', $event), ['enabled' => false])
            ->assertRedirect();

        $this->assertNull($event->fresh()->public_token);
    }

    public function test_non_owner_cannot_manage_share_link(): void
    {
        $owner  = User::factory()->create();
        $editor = User::factory()->create();
        $event  = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($editor->id, ['role' => 'editor']);

        $this->actingAs($editor)
            ->post(route('events.share.update', $event), ['enabled' => true])
            ->assertForbidden();
    }

    public function test_public_link_renders_viewer_page(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id, 'public_token' => 'mytoken123']);

        $this->get(route('public.show', 'mytoken123'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/Show'));
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get(route('public.show', 'doesnotexist'))->assertNotFound();
    }

    public function test_password_protected_event_shows_password_page(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create([
            'user_id'               => $user->id,
            'public_token'          => 'protectedtoken',
            'public_password_hash'  => Hash::make('secret'),
        ]);

        $this->get(route('public.show', 'protectedtoken'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/Password'));
    }

    public function test_correct_password_grants_access(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create([
            'user_id'              => $user->id,
            'public_token'         => 'protectedtoken',
            'public_password_hash' => Hash::make('secret'),
        ]);

        $this->post(route('public.enter', 'protectedtoken'), ['password' => 'secret'])
            ->assertRedirect(route('public.show', 'protectedtoken'));
    }

    public function test_wrong_password_returns_error(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create([
            'user_id'              => $user->id,
            'public_token'         => 'protectedtoken',
            'public_password_hash' => Hash::make('secret'),
        ]);

        $this->post(route('public.enter', 'protectedtoken'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
    }

    public function test_public_api_returns_elements_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id, 'public_token' => 'apitoken']);
        $plan  = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $this->getJson("/api/public/apitoken/plans/{$plan->id}/elements")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_public_api_rejects_wrong_token(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id, 'public_token' => 'realtoken']);
        $plan  = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $this->getJson("/api/public/wrongtoken/plans/{$plan->id}/elements")
            ->assertNotFound();
    }

    public function test_public_api_rejects_mismatched_plan(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $eventA = Event::factory()->create(['user_id' => $userA->id, 'public_token' => 'tokenA']);
        $eventB = Event::factory()->create(['user_id' => $userB->id, 'public_token' => 'tokenB']);
        $planB  = $eventB->plans()->create(['name' => 'Plan B', 'sort_order' => 1]);

        // Token for eventA but plan from eventB → 404
        $this->getJson("/api/public/tokenA/plans/{$planB->id}/elements")
            ->assertNotFound();
    }

    public function test_public_api_blocked_by_password_without_session(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create([
            'user_id'              => $user->id,
            'public_token'         => 'lockedtoken',
            'public_password_hash' => Hash::make('secret'),
        ]);
        $plan = $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);

        $this->getJson("/api/public/lockedtoken/plans/{$plan->id}/elements")
            ->assertForbidden();
    }

    public function test_owner_can_set_password_on_share_link(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('events.share.update', $event), [
                'enabled'  => true,
                'password' => 'mysecret',
            ])
            ->assertRedirect();

        $event->refresh();
        $this->assertNotNull($event->public_token);
        $this->assertTrue(Hash::check('mysecret', $event->public_password_hash));
    }
}
