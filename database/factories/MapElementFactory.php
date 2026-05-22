<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\MapElement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MapElement>
 */
class MapElementFactory extends Factory
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
            'event_plan_id' => null,
            'type' => 'marker',
            'subtype' => 'buoy',
            'name' => null,
            'notes' => null,
            'geometry' => ['type' => 'Point', 'coordinates' => [fake()->longitude(), fake()->latitude()]],
            'properties' => null,
            'is_locked' => false,
            'is_hidden' => false,
            'sort_order' => 0,
        ];
    }
}
