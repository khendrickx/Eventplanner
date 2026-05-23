<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\MapOverlay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MapOverlay>
 */
class MapOverlayFactory extends Factory
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
            'name' => 'Overlay ' . fake()->word(),
            'image_path' => 'overlays/test.png',
            'bounds' => [[4.3, 50.8], [4.4, 50.9]],
            'opacity' => 1.0,
            'sort_order' => 0,
        ];
    }
}
