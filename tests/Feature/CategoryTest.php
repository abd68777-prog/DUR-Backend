<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_categories(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(2)->create();

        $this->actingAs($user, 'clerk')
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_customer_cannot_create_category(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'clerk')
            ->postJson('/api/categories', ['name' => 'خواتم', 'slug' => 'rings'])
            ->assertForbidden();
    }

    public function test_manager_can_create_category(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/categories', ['name' => 'خواتم', 'slug' => 'rings'])
            ->assertCreated()
            ->assertJsonPath('slug', 'rings');
    }

    public function test_category_slug_must_be_unique(): void
    {
        $manager = User::factory()->manager()->create();
        Category::factory()->create(['slug' => 'rings']);

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/categories', ['name' => 'خواتم أخرى', 'slug' => 'rings'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_manager_can_update_category(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create(['name' => 'قديم']);

        $this->actingAs($manager, 'clerk')
            ->putJson("/api/categories/{$category->id}", ['name' => 'جديد'])
            ->assertOk()
            ->assertJsonPath('name', 'جديد');
    }

    public function test_updating_category_ignores_its_own_slug_in_uniqueness_check(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create(['slug' => 'rings']);

        $this->actingAs($manager, 'clerk')
            ->putJson("/api/categories/{$category->id}", ['slug' => 'rings'])
            ->assertOk();
    }

    public function test_manager_cannot_delete_category(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create();

        $this->actingAs($manager, 'clerk')
            ->deleteJson("/api/categories/{$category->id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin, 'clerk')
            ->deleteJson("/api/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deleting_category_cascades_to_its_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin, 'clerk')
            ->deleteJson("/api/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}