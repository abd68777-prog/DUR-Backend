<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'عرض '.fake()->unique()->numberBetween(1, 100000),
            'name_en' => 'Promotion '.fake()->unique()->numberBetween(1, 100000),
            'code' => null,
            'type' => Promotion::TYPE_PERCENTAGE,
            'value' => 10,
            'scope' => Promotion::SCOPE_ALL,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function withCode(string $code = 'EID2026'): static
    {
        return $this->state(fn () => ['code' => strtoupper($code)]);
    }

    public function percentage(float $percent): static
    {
        return $this->state(fn () => [
            'type' => Promotion::TYPE_PERCENTAGE,
            'value' => $percent,
        ]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => [
            'type' => Promotion::TYPE_FIXED,
            'value' => $amount,
        ]);
    }

    public function forProducts(): static
    {
        return $this->state(fn () => ['scope' => Promotion::SCOPE_PRODUCTS]);
    }

    public function forCategories(): static
    {
        return $this->state(fn () => ['scope' => Promotion::SCOPE_CATEGORIES]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
