<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الخصم يلي بينعكس على سعر المنتج بالـ API.
 */
class PromotionPricingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * JSON ما بيميّز 500 عن 500.0، فمنقرأ القيمة ومنحوّلها بدل ما نقارن
     * حرفياً - وإلا التستات بتنكسر على شكل الترميز مش على المنطق.
     *
     * @return array{price: float, final: float, discount: ?array}
     */
    private function pricing(string $slug): array
    {
        $response = $this->getJson("/api/products/{$slug}")->assertOk();

        return [
            'price' => (float) $response->json('price'),
            'final' => (float) $response->json('final_price'),
            'discount' => $response->json('discount'),
        ];
    }

    public function test_a_product_with_no_promotion_reports_its_own_price(): void
    {
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['price']);
        $this->assertSame(500.0, $pricing['final']);
        $this->assertNull($pricing['discount']);
    }

    public function test_a_store_wide_percentage_promotion_discounts_every_product(): void
    {
        Promotion::factory()->percentage(20)->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['price'], 'price must stay the original');
        $this->assertSame(400.0, $pricing['final']);
        $this->assertSame('percentage', $pricing['discount']['type']);
        $this->assertSame(20.0, (float) $pricing['discount']['value']);
        $this->assertSame(100.0, (float) $pricing['discount']['amount']);
    }

    public function test_a_fixed_promotion_takes_a_flat_amount_off(): void
    {
        Promotion::factory()->fixed(75)->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $pricing = $this->pricing('ring');

        $this->assertSame(425.0, $pricing['final']);
        $this->assertSame(75.0, (float) $pricing['discount']['amount']);
    }

    public function test_a_fixed_promotion_never_pushes_the_price_below_zero(): void
    {
        Promotion::factory()->fixed(900)->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $pricing = $this->pricing('ring');

        $this->assertSame(0.0, $pricing['final']);
        $this->assertSame(500.0, (float) $pricing['discount']['amount']);
    }

    public function test_a_product_scoped_promotion_only_touches_its_own_products(): void
    {
        $discounted = Product::factory()->create(['price' => 500, 'slug' => 'discounted']);
        Product::factory()->create(['price' => 500, 'slug' => 'untouched']);

        $promotion = Promotion::factory()->forProducts()->percentage(50)->create();
        $promotion->products()->attach($discounted);

        $this->assertSame(250.0, $this->pricing('discounted')['final']);

        $untouched = $this->pricing('untouched');
        $this->assertSame(500.0, $untouched['final']);
        $this->assertNull($untouched['discount']);
    }

    public function test_a_category_scoped_promotion_covers_every_product_in_it(): void
    {
        $rings = Category::factory()->create();
        $other = Category::factory()->create();

        Product::factory()->create(['price' => 500, 'slug' => 'ring-a', 'category_id' => $rings->id]);
        Product::factory()->create(['price' => 200, 'slug' => 'ring-b', 'category_id' => $rings->id]);
        Product::factory()->create(['price' => 500, 'slug' => 'bracelet', 'category_id' => $other->id]);

        $promotion = Promotion::factory()->forCategories()->percentage(10)->create();
        $promotion->categories()->attach($rings);

        $this->assertSame(450.0, $this->pricing('ring-a')['final']);
        $this->assertSame(180.0, $this->pricing('ring-b')['final']);
        $this->assertNull($this->pricing('bracelet')['discount']);
    }

    public function test_a_product_added_to_the_category_later_is_covered_automatically(): void
    {
        $rings = Category::factory()->create();
        $promotion = Promotion::factory()->forCategories()->percentage(10)->create();
        $promotion->categories()->attach($rings);

        // منتج انضاف بعد ما انعمل العرض.
        Product::factory()->create(['price' => 300, 'slug' => 'new-ring', 'category_id' => $rings->id]);

        $this->assertSame(270.0, $this->pricing('new-ring')['final']);
    }

    public function test_an_expired_promotion_is_ignored(): void
    {
        Promotion::factory()->percentage(20)->expired()->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['final']);
        $this->assertNull($pricing['discount']);
    }

    public function test_a_promotion_that_has_not_started_is_ignored(): void
    {
        Promotion::factory()->percentage(20)->upcoming()->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $this->assertNull($this->pricing('ring')['discount']);
    }

    public function test_a_deactivated_promotion_is_ignored(): void
    {
        Promotion::factory()->percentage(20)->inactive()->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $this->assertNull($this->pricing('ring')['discount']);
    }

    public function test_a_code_promotion_never_discounts_the_listed_price(): void
    {
        // وإلا كل الزباين بياخدوا الخصم بدون ما يدخّلوا الكود.
        Promotion::factory()->percentage(30)->withCode('EID2026')->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['final']);
        $this->assertNull($pricing['discount']);
    }

    public function test_the_customer_gets_the_largest_of_several_promotions(): void
    {
        $product = Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        Promotion::factory()->percentage(10)->create();          // 50
        Promotion::factory()->fixed(120)->create();              // 120  <- الأكبر
        $small = Promotion::factory()->forProducts()->percentage(5)->create();
        $small->products()->attach($product);                    // 25

        $pricing = $this->pricing('ring');

        $this->assertSame(380.0, $pricing['final']);
        $this->assertSame(120.0, (float) $pricing['discount']['amount']);
    }

    public function test_listing_products_does_not_run_a_query_per_product(): void
    {
        // ProductResource بيسأل عن العروض لكل منتج. لازم PromotionService
        // يتحمّل مرة وحدة بالطلب، وإلا كل منتج بيعمل استعلاماته الخاصة.
        Promotion::factory()->count(3)->percentage(10)->create();
        Product::factory()->count(20)->create(['price' => 100]);

        DB::enableQueryLog();
        $this->getJson('/api/products?per_page=20')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(12, $queries, "Expected a handful of queries, ran {$queries}");
    }
}
