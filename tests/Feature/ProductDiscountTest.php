<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الخصم اليدوي لكل منتج - الآلية المعتمدة بالنسخة الحالية (الطلب عبر واتساب).
 */
class ProductDiscountTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{price: float, final: float, discount: ?array} */
    private function pricing(string $slug): array
    {
        // JSON ما بيميّز 500 عن 500.0، فمنحوّل بدل ما نقارن حرفياً.
        $response = $this->getJson("/api/products/{$slug}")->assertOk();

        return [
            'price' => (float) $response->json('price'),
            'final' => (float) $response->json('final_price'),
            'discount' => $response->json('discount'),
        ];
    }

    // ------------------------------------------------------------ حساب السعر

    public function test_a_product_without_a_discount_keeps_its_price(): void
    {
        Product::factory()->create(['price' => 500, 'slug' => 'ring', 'has_discount' => false]);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['final']);
        $this->assertNull($pricing['discount']);
    }

    public function test_a_discounted_product_reports_the_reduced_price(): void
    {
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => 20,
        ]);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['price'], 'the original price must survive');
        $this->assertSame(400.0, $pricing['final']);
        $this->assertSame('product', $pricing['discount']['source']);
        $this->assertSame(20.0, (float) $pricing['discount']['value']);
        $this->assertSame(100.0, (float) $pricing['discount']['amount']);
        $this->assertNull($pricing['discount']['promotion_id']);
    }

    public function test_turning_the_flag_off_keeps_the_value_but_drops_the_discount(): void
    {
        // هيك الإدارة بتوقف الخصم مؤقتاً بدون ما تفقد النسبة.
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => false,
            'discount_value' => 20,
        ]);

        $response = $this->getJson('/api/products/ring')->assertOk();

        $this->assertSame(500.0, (float) $response->json('final_price'));
        $this->assertNull($response->json('discount'));
        $this->assertSame(20.0, (float) $response->json('discount_value'), 'the value stays for later');
    }

    public function test_the_flag_without_a_value_is_treated_as_no_discount(): void
    {
        // صفوف قديمة أو تعديل ناقص ما لازم تكسر السعر.
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => null,
        ]);

        $pricing = $this->pricing('ring');

        $this->assertSame(500.0, $pricing['final']);
        $this->assertNull($pricing['discount']);
    }

    public function test_a_hundred_percent_discount_lands_on_zero(): void
    {
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => 100,
        ]);

        $this->assertSame(0.0, $this->pricing('ring')['final']);
    }

    public function test_fractional_percentages_are_rounded_to_two_decimals(): void
    {
        Product::factory()->create([
            'price' => 333.33,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => 12.5,
        ]);

        $pricing = $this->pricing('ring');

        $this->assertSame(41.67, (float) $pricing['discount']['amount']);
        $this->assertSame(291.66, $pricing['final']);
    }

    // ------------------------------------------------- التداخل مع العروض

    public function test_the_larger_of_the_manual_discount_and_a_promotion_wins(): void
    {
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => 10,          // 50
        ]);
        Promotion::factory()->percentage(30)->create();   // 150 <- الأكبر

        $pricing = $this->pricing('ring');

        $this->assertSame(350.0, $pricing['final']);
        $this->assertSame('promotion', $pricing['discount']['source']);
    }

    public function test_the_manual_discount_wins_when_it_is_the_larger_one(): void
    {
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => 40,          // 200 <- الأكبر
        ]);
        Promotion::factory()->percentage(5)->create();    // 25

        $pricing = $this->pricing('ring');

        $this->assertSame(300.0, $pricing['final']);
        $this->assertSame('product', $pricing['discount']['source']);
    }

    public function test_discounts_never_stack(): void
    {
        Product::factory()->create([
            'price' => 500,
            'slug' => 'ring',
            'has_discount' => true,
            'discount_value' => 20,          // 100
        ]);
        Promotion::factory()->percentage(20)->create();   // 100

        // 400، مش 300.
        $this->assertSame(400.0, $this->pricing('ring')['final']);
    }

    // ------------------------------------------------------------- الإدارة

    public function test_a_manager_can_create_a_product_with_a_discount(): void
    {
        $manager = User::factory()->manager()->create();
        $category = \App\Models\Category::factory()->create();

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'slug' => 'gold-ring',
                'name_ar' => 'خاتم ذهب',
                'name_en' => 'Gold Ring',
                'price' => 500,
                'has_discount' => true,
                'discount_value' => 25,
            ])
            ->assertCreated()
            ->assertJsonPath('has_discount', true)
            ->assertJsonPath('final_price', 375);
    }

    public function test_switching_the_discount_on_requires_a_percentage(): void
    {
        $manager = User::factory()->manager()->create();
        $category = \App\Models\Category::factory()->create();

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'slug' => 'gold-ring',
                'name_ar' => 'خاتم ذهب',
                'name_en' => 'Gold Ring',
                'price' => 500,
                'has_discount' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_a_percentage_above_one_hundred_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create();

        $this->actingAs($manager, 'clerk')
            ->putJson("/api/products/{$product->id}", [
                'has_discount' => true,
                'discount_value' => 150,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_a_manager_can_add_a_discount_to_an_existing_product(): void
    {
        // الحالة الفعلية: الزبون بعت رابط المنتج عالواتساب، والإدارة بتحط الخصم.
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create(['price' => 1000, 'slug' => 'bracelet']);

        $this->actingAs($manager, 'clerk')
            ->putJson('/api/products/bracelet', [
                'has_discount' => true,
                'discount_value' => 15,
            ])
            ->assertOk()
            ->assertJsonPath('final_price', 850);

        $this->assertTrue($product->fresh()->has_discount);
    }

    public function test_switching_a_stored_discount_back_on_needs_no_percentage(): void
    {
        $manager = User::factory()->manager()->create();
        Product::factory()->create([
            'price' => 1000,
            'slug' => 'bracelet',
            'has_discount' => false,
            'discount_value' => 15,
        ]);

        $this->actingAs($manager, 'clerk')
            ->putJson('/api/products/bracelet', ['has_discount' => true])
            ->assertOk()
            ->assertJsonPath('final_price', 850);
    }

    public function test_a_string_flag_from_multipart_is_accepted(): void
    {
        // الفرونت بيبعت multipart لرفع الصور، فالقيم بتوصل نصوص.
        $manager = User::factory()->manager()->create();
        Product::factory()->create(['price' => 500, 'slug' => 'ring']);

        $this->actingAs($manager, 'clerk')
            ->put('/api/products/ring', [
                'has_discount' => 'true',
                'discount_value' => '20',
            ])
            ->assertOk()
            ->assertJsonPath('has_discount', true)
            ->assertJsonPath('final_price', 400);
    }
}
