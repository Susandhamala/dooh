<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Screen> */
class ScreenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory()->owner()->verified(),
            'name' => fake()->streetName().' Screen',
            'lat' => fake()->randomFloat(6, 27.65, 27.75), // Kathmandu valley
            'lng' => fake()->randomFloat(6, 85.28, 85.38),
            'width_px' => 1920,
            'height_px' => 1080,
            'orientation' => 'landscape',
            'loop_seconds' => 120,
            'spot_seconds' => 10,
            'slot_price' => 500,
            'status' => 'active',
        ];
    }

    /** A screen with only $slots bookable slots per loop. */
    public function slots(int $slots): static
    {
        return $this->state(['loop_seconds' => $slots * 10, 'spot_seconds' => 10]);
    }
}
