<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventInvitation>
 */
class EventInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'email' => fake()->safeEmail(),
            'role' => 'viewer',
            'token' => \Illuminate\Support\Str::random(32),
            'expires_at' => now()->addDays(7),
        ];
    }
}
