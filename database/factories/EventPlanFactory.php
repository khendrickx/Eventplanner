<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPlan>
 */
class EventPlanFactory extends Factory
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
            'name' => 'Plan ' . fake()->numberBetween(1, 5),
            'sort_order' => 1,
        ];
    }
}
