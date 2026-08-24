<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- الصلاحيات

    public function test_guests_cannot_list_promotions(): void
    {
        $this->getJson('/api/promotions')->assertStatus(401);
    }

    public function test_customers_cannot_list_promotions(): void
    {
        // اللستة بتكشف الأكواد، فما بتنفتح لغير الإدارة.
        $this->actingAs(User::factory()->create(), 'clerk')
            ->getJson('/api/promotions')
            ->assertStatus(403);
    }

    public function test_a_manager_can_list_promotions(): void
    {
        Promotion::factory()->count(3)->create();

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->getJson('/api/promotions')
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_a_manager_cannot_delete_a_promotion(): void
    {
        $promotion = Promotion::factory()->create();

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->deleteJson("/api/promotions/{$promotion->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('promotions', ['id' => $promotion->id]);
    }

    public function test_an_admin_can_delete_a_promotion(): void
    {
        $promotion = Promotion::factory()->forProducts()->create();
        $promotion->products()->attach(Product::factory()->create());

        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->deleteJson("/api/promotions/{$promotion->id}")
            ->assertOk();

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
        // الربط بينمسح معه، والمنتج بيضل موجود.
        $this->assertDatabaseCount('product_promotion', 0);
        $this->assertDatabaseCount('products', 1);
    }

    // ------------------------------------------------------------------ الإنشاء

    public function test_a_manager_can_create_a_store_wide_promotion(): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'تخفيضات العيد',
                'name_en' => 'Eid Sale',
                'type' => 'percentage',
                'value' => 20,
                'scope' => 'all',
            ])
            ->assertCreated()
            ->assertJsonPath('name_en', 'Eid Sale')
            ->assertJsonPath('scope', 'all')
            ->assertJsonPath('code', null)
            ->assertJsonPath('is_running', true);
    }

    public function test_creating_a_promotion_requires_the_core_fields(): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name_ar', 'name_en', 'type', 'value', 'scope']);
    }

    public function test_a_product_scoped_promotion_requires_products(): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'products',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids']);
    }

    public function test_a_category_scoped_promotion_requires_categories(): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'categories',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_ids']);
    }

    public function test_a_percentage_promotion_cannot_exceed_one_hundred(): void
    {
        // 120% معناها سعر سالب.
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'type' => 'percentage',
                'value' => 120,
                'scope' => 'all',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_a_fixed_promotion_may_exceed_one_hundred(): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'type' => 'fixed',
                'value' => 5000,
                'scope' => 'all',
            ])
            ->assertCreated();
    }

    public function test_the_end_date_cannot_precede_the_start_date(): void
    {
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'all',
                'starts_at' => now()->addWeek()->toIso8601String(),
                'ends_at' => now()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_a_code_is_stored_uppercase(): void
    {
        // حتى "eid2026" و"EID2026" يكونوا نفس الكود.
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'code' => 'eid2026',
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'all',
            ])
            ->assertCreated()
            ->assertJsonPath('code', 'EID2026');
    }

    public function test_a_code_must_be_unique(): void
    {
        Promotion::factory()->withCode('EID2026')->create();

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->postJson('/api/promotions', [
                'name_ar' => 'عرض',
                'name_en' => 'Promo',
                'code' => 'eid2026',
                'type' => 'percentage',
                'value' => 10,
                'scope' => 'all',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    // ------------------------------------------------------------------ التعديل

    public function test_a_manager_can_update_a_promotion(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->putJson("/api/promotions/{$promotion->id}", ['value' => 25])
            ->assertOk()
            ->assertJsonPath('value', 25);
    }

    public function test_updating_the_value_of_a_percentage_promotion_still_caps_at_one_hundred(): void
    {
        // النوع مش مبعوت بالطلب، فلازم ينقرأ من المخزّن.
        $promotion = Promotion::factory()->percentage(10)->create();

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->putJson("/api/promotions/{$promotion->id}", ['value' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_changing_the_scope_drops_links_that_no_longer_apply(): void
    {
        // وإلا بيضلوا مخبّيين وبيرجعوا يشتغلوا لو رجع النطاق.
        $promotion = Promotion::factory()->forProducts()->create();
        $promotion->products()->attach(Product::factory()->create());

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->putJson("/api/promotions/{$promotion->id}", ['scope' => 'all'])
            ->assertOk();

        $this->assertDatabaseCount('product_promotion', 0);
    }

    public function test_updating_products_replaces_the_previous_selection(): void
    {
        $old = Product::factory()->create();
        $new = Product::factory()->create();

        $promotion = Promotion::factory()->forProducts()->create();
        $promotion->products()->attach($old);

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->putJson("/api/promotions/{$promotion->id}", ['product_ids' => [$new->id]])
            ->assertOk();

        $this->assertSame([$new->id], $promotion->fresh()->products->pluck('id')->all());
    }

    public function test_a_manager_can_toggle_a_promotion_off(): void
    {
        $promotion = Promotion::factory()->create(['is_active' => true]);

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->patchJson("/api/promotions/{$promotion->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('is_running', false);
    }

    public function test_promotions_can_be_filtered_to_the_running_ones(): void
    {
        Promotion::factory()->count(2)->create();
        Promotion::factory()->expired()->create();
        Promotion::factory()->inactive()->create();

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->getJson('/api/promotions?running=true')
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->getJson('/api/promotions?running=false')
            ->assertOk()
            ->assertJsonCount(2);
    }

    // ------------------------------------------------------- /promotions/active

    public function test_active_promotions_are_public(): void
    {
        Promotion::factory()->count(2)->create();

        $this->getJson('/api/promotions/active')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_active_promotions_never_leak_discount_codes(): void
    {
        // لو ظهرت هون، أي زبون بياخد الكود بدون ما حدا يعطيه ياه.
        Promotion::factory()->withCode('SECRET2026')->create();
        Promotion::factory()->create();

        $response = $this->getJson('/api/promotions/active')->assertOk();

        $this->assertCount(1, $response->json());
        $response->assertDontSee('SECRET2026');
    }

    public function test_active_promotions_exclude_expired_and_disabled_ones(): void
    {
        Promotion::factory()->create();
        Promotion::factory()->expired()->create();
        Promotion::factory()->upcoming()->create();
        Promotion::factory()->inactive()->create();

        $this->getJson('/api/promotions/active')
            ->assertOk()
            ->assertJsonCount(1);
    }

    // ----------------------------------------------------- /promotions/validate

    public function test_a_valid_code_returns_the_promotion(): void
    {
        Promotion::factory()->withCode('EID2026')->percentage(15)->create();

        $this->postJson('/api/promotions/validate', ['code' => 'EID2026'])
            ->assertOk()
            ->assertJsonPath('promotion.code', 'EID2026')
            ->assertJsonPath('promotion.value', 15)
            ->assertJsonPath('products', null); // نطاق المتجر كله
    }

    public function test_code_matching_ignores_case_and_spacing(): void
    {
        Promotion::factory()->withCode('EID2026')->create();

        $this->postJson('/api/promotions/validate', ['code' => '  eid2026 '])
            ->assertOk()
            ->assertJsonPath('promotion.code', 'EID2026');
    }

    public function test_a_code_returns_the_products_it_covers(): void
    {
        $covered = Product::factory()->create();
        Product::factory()->create();

        $promotion = Promotion::factory()->forProducts()->withCode('RING10')->create();
        $promotion->products()->attach($covered);

        $this->postJson('/api/promotions/validate', ['code' => 'RING10'])
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $covered->id);
    }

    public function test_a_category_code_returns_every_product_in_that_category(): void
    {
        $rings = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $rings->id]);
        Product::factory()->create();

        $promotion = Promotion::factory()->forCategories()->withCode('RINGS20')->create();
        $promotion->categories()->attach($rings);

        $this->postJson('/api/promotions/validate', ['code' => 'RINGS20'])
            ->assertOk()
            ->assertJsonCount(2, 'products');
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->postJson('/api/promotions/validate', ['code' => 'NOPE'])
            ->assertStatus(404)
            ->assertJson(['message' => 'This discount code is not valid.']);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        Promotion::factory()->withCode('OLD2025')->expired()->create();

        $this->postJson('/api/promotions/validate', ['code' => 'OLD2025'])
            ->assertStatus(404);
    }

    public function test_a_disabled_code_is_rejected(): void
    {
        Promotion::factory()->withCode('OFF2026')->inactive()->create();

        $this->postJson('/api/promotions/validate', ['code' => 'OFF2026'])
            ->assertStatus(404);
    }

    public function test_an_automatic_promotion_cannot_be_claimed_as_a_code(): void
    {
        Promotion::factory()->create(); // code = null

        $this->postJson('/api/promotions/validate', ['code' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }
}
