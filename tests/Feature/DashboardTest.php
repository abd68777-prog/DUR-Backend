<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_view_stats(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'clerk')
            ->getJson('/api/dashboard/stats')
            ->assertForbidden();
    }

    public function test_admin_can_view_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $category->id, 'is_active' => true, 'stock' => 10]);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false, 'stock' => 10]);
        Product::factory()->lowStock(1)->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin, 'clerk')
            ->getJson('/api/dashboard/stats')
            ->assertOk();

        $response->assertJsonPath('products_count.total', 4)
            ->assertJsonPath('products_count.active', 3)
            ->assertJsonPath('products_count.inactive', 1)
            ->assertJsonCount(1, 'low_stock_products')
            ->assertJsonCount(4, 'latest_products');
    }
}