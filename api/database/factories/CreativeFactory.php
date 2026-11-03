<?php

namespace Database\Factories;

use App\Models\Creative;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An ad that already went through processing (no real file), for booking and review tests.
 *
 * @extends Factory<Creative>
 */
class CreativeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->catchPhrase(),
            'category' => 'retail',
            'kind' => 'image',
            'width_px' => 1920,
            'height_px' => 1080,
            'mime' => 'image/webp',
            'size_bytes' => 120000,
            'status' => 'approved',
            'declared_at' => now(),
            'terms_version' => config('terms.version'),
        ];
    }

    public function inReview(): static
    {
        return $this->state(['status' => 'in_review']);
    }

    public function video(float $seconds = 8): static
    {
        return $this->state(['kind' => 'video', 'mime' => 'video/mp4', 'duration_s' => $seconds]);
    }
}
