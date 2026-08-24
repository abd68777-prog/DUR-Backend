<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_fetched_by_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'gold-ring-21k']);

        $this->getJson('/api/products/gold-ring-21k')
            ->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('slug', 'gold-ring-21k');
    }

    public function test_a_product_can_still_be_fetched_by_id(): void
    {
        // روابط قديمة عند الفرونت لازم تضل تشتغل.
        $product = Product::factory()->create(['slug' => 'gold-ring-21k']);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('slug', 'gold-ring-21k');
    }

    public function test_a_category_can_be_fetched_by_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'rings']);

        $this->getJson('/api/categories/rings')
            ->assertOk()
            ->assertJsonPath('id', $category->id);
    }

    public function test_a_category_can_still_be_fetched_by_id(): void
    {
        $category = Category::factory()->create(['slug' => 'rings']);

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('slug', 'rings');
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/products/does-not-exist')
            ->assertStatus(404)
            ->assertJson(['message' => 'The requested resource was not found.']);
    }

    public function test_a_product_without_a_slug_is_still_reachable_by_id(): void
    {
        // منتجات انعملت قبل ما ينضاف عمود slug.
        $product = Product::factory()->create(['slug' => null]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('id', $product->id);
    }

    public function test_a_numeric_slug_wins_over_a_matching_id(): void
    {
        // منتج slug تبعه "2026" ما لازم يتلغبط مع المنتج يلي id تبعه 2026.
        $bySlug = Product::factory()->create(['slug' => '2026']);

        $this->getJson('/api/products/2026')
            ->assertOk()
            ->assertJsonPath('id', $bySlug->id);
    }

    public function test_mutating_routes_accept_a_slug_too(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create(['slug' => 'gold-ring-21k', 'is_active' => true]);

        $this->actingAs($manager, 'clerk')
            ->putJson('/api/products/gold-ring-21k', ['name_ar' => 'جديد'])
            ->assertOk()
            ->assertJsonPath('name_ar', 'جديد');

        $this->actingAs($manager, 'clerk')
            ->patchJson('/api/products/gold-ring-21k/toggle-active')
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertSame($product->id, Product::where('slug', 'gold-ring-21k')->sole()->id);
    }

    public function test_products_can_be_filtered_by_category_slug(): void
    {
        $rings = Category::factory()->create(['slug' => 'rings']);
        $bracelets = Category::factory()->create(['slug' => 'bracelets']);
        Product::factory()->count(2)->create(['category_id' => $rings->id]);
        Product::factory()->count(3)->create(['category_id' => $bracelets->id]);

        $this->getJson('/api/products?category_id=rings')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_products_can_still_be_filtered_by_category_id(): void
    {
        $rings = Category::factory()->create(['slug' => 'rings']);
        Product::factory()->count(2)->create(['category_id' => $rings->id]);
        Product::factory()->count(3)->create();

        $this->getJson("/api/products?category_id={$rings->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filtering_by_an_unknown_category_returns_nothing(): void
    {
        // مهم: مش يرجّع كل المنتجات كإنه ما في فلتر.
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?category_id=does-not-exist')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_empty_category_filter_is_ignored(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?category_id=')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_a_numeric_category_slug_wins_over_a_matching_id(): void
    {
        $first = Category::factory()->create(['slug' => 'first']);
        $numeric = Category::factory()->create(['slug' => (string) $first->id]);

        Product::factory()->count(2)->create(['category_id' => $first->id]);
        Product::factory()->count(1)->create(['category_id' => $numeric->id]);

        // "{$first->id}" بيطابق slug تبع $numeric، فلازم يرجّع منتجاته هو.
        $this->getJson("/api/products?category_id={$first->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
