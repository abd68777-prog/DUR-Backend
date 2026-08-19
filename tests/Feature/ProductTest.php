<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_products(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(3)->create();

        $this->actingAs($user, 'clerk')
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $user = User::factory()->create();
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $categoryA->id]);
        Product::factory()->count(1)->create(['category_id' => $categoryB->id]);

        $this->actingAs($user, 'clerk')
            ->getJson("/api/products?category_id={$categoryA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_view_a_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user, 'clerk')
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('id', $product->id);
    }

    public function test_customer_cannot_create_product(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($customer, 'clerk')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'slug' => 'gold-ring',
                'name_ar' => 'خاتم ذهب',
                'name_en' => 'Gold Ring',
                'price' => 500,
            ])
            ->assertForbidden();
    }

    public function test_manager_can_create_product(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create();

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'slug' => 'gold-ring',
                'name_ar' => 'خاتم ذهب',
                'name_en' => 'Gold Ring',
                'price' => 500,
                'karat' => '22',
            ])
            ->assertCreated()
            ->assertJsonPath('name_ar', 'خاتم ذهب')
            ->assertJsonPath('name_en', 'Gold Ring')
            ->assertJsonPath('karat', '22');

        $this->assertDatabaseHas('products', ['slug' => 'gold-ring', 'name_ar' => 'خاتم ذهب']);
    }

    public function test_creating_product_requires_valid_data(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'slug', 'name_ar', 'name_en', 'price']);
    }

    public function test_manager_can_update_product(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create(['name_ar' => 'قديم']);

        $this->actingAs($manager, 'clerk')
            ->putJson("/api/products/{$product->id}", ['name_ar' => 'جديد'])
            ->assertOk()
            ->assertJsonPath('name_ar', 'جديد');
    }

    public function test_manager_can_toggle_product_active_state(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $this->actingAs($manager, 'clerk')
            ->patchJson("/api/products/{$product->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('is_active', false);
    }

    public function test_manager_cannot_delete_product(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create();

        $this->actingAs($manager, 'clerk')
            ->deleteJson("/api/products/{$product->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin, 'clerk')
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
