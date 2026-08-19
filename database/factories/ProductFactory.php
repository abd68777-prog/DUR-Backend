<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $nameEn = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'slug' => Str::slug($nameEn).'-'.fake()->unique()->numberBetween(1, 100000),
            'name_ar' => fake()->words(3, true),
            'name_en' => $nameEn,
            'description_ar' => fake()->sentence(),
            'description_en' => fake()->sentence(),
            'gold_weight' => fake()->randomFloat(3, 1, 50),
            'karat' => fake()->randomElement(['18', '21', '22', '24']),
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
