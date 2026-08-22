<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_categories(): void
    {
        // مؤقتاً public بدون تسجيل دخول (راجع routes/api.php للتفاصيل).
        Category::factory()->count(2)->create();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_guest_can_view_a_category(): void
    {
        // مؤقتاً public بدون تسجيل دخول (راجع routes/api.php للتفاصيل).
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('id', $category->id);
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
            ->postJson('/api/categories', ['name_ar' => 'خواتم', 'name_en' => 'Rings', 'slug' => 'rings'])
            ->assertForbidden();
    }

    public function test_manager_can_create_category(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/categories', ['name_ar' => 'خواتم', 'name_en' => 'Rings', 'slug' => 'rings'])
            ->assertCreated()
            ->assertJsonPath('slug', 'rings')
            ->assertJsonPath('name_en', 'Rings');
    }

    public function test_category_slug_must_be_unique(): void
    {
        $manager = User::factory()->manager()->create();
        Category::factory()->create(['slug' => 'rings']);

        $this->actingAs($manager, 'clerk')
            ->postJson('/api/categories', ['name_ar' => 'خواتم أخرى', 'name_en' => 'Other Rings', 'slug' => 'rings'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_manager_can_update_category(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create(['name_ar' => 'قديم']);

        $this->actingAs($manager, 'clerk')
            ->putJson("/api/categories/{$category->id}", ['name_ar' => 'جديد'])
            ->assertOk()
            ->assertJsonPath('name_ar', 'جديد');
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

    public function test_manager_can_upload_category_image(): void
    {
        Storage::fake('cloudinary');
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'clerk')
            ->postJson('/api/categories', [
                'name_ar' => 'خواتم',
                'name_en' => 'Rings',
                'slug' => 'rings',
                'image' => UploadedFile::fake()->image('category.jpg'),
            ])
            ->assertCreated();

        $this->assertNotNull($response->json('image'));
    }

    public function test_string_is_active_from_multipart_form_data_is_accepted(): void
    {
        // الفرونت-إند بيبعت multipart/form-data (لازم لرفع الصورة)، ويلي بيخلي is_active
        // توصل كـ string "true"/"false" مش boolean حقيقي.
        Storage::fake('cloudinary');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'clerk')
            ->post('/api/categories', [
                'name_ar' => 'خواتم',
                'name_en' => 'Rings',
                'slug' => 'rings',
                'is_active' => 'false',
                'image' => UploadedFile::fake()->image('category.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('is_active', false);
    }
}
