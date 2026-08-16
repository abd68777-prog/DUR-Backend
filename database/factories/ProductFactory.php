<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'gold_weight' => fake()->randomFloat(3, 1, 50),
            'karat' => fake()->randomElement(['18', '21', '24']),
            'gemstone_type' => fake()->optional()->word(),
            'gemstone_carat' => fake()->optional()->randomFloat(2, 0.1, 5),
            'price' => fake()->randomFloat(2, 100, 5000),
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function lowStock(int $stock = 2): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }
}