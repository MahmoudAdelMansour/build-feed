<?php

namespace Database\Factories;

use App\Models\SponsoredPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsoredPost>
 */
class SponsoredPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'is_active' => fake()->boolean(80), // 80% chance of active
        ];
    }

    /**
     * Indicate that the post is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the post is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
