<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('cloudinary');
    }

    public function test_manager_can_upload_images_when_creating_product(): void
    {
        $manager = User::factory()->manager()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($manager, 'clerk')
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'slug' => 'gold-ring',
                'name_ar' => 'خاتم ذهب',
                'name_en' => 'Gold Ring',
                'price' => 500,
                'images' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.jpg'),
                ],
            ])
            ->assertCreated();

        $response->assertJsonCount(2, 'images');

        $product = Product::first();
        $this->assertCount(2, $product->images);

        $first = $product->images()->orderBy('sort_order')->first();
        $second = $product->images()->orderBy('sort_order')->skip(1)->first();

        $this->assertTrue((bool) $first->is_primary);
        $this->assertFalse((bool) $second->is_primary);

        Storage::disk('cloudinary')->assertExists($first->path);
        Storage::disk('cloudinary')->assertExists($second->path);
    }

    public function test_adding_images_to_product_with_existing_primary_does_not_reassign_primary(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create();
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0, 'is_primary' => true]);

        $this->actingAs($manager, 'clerk')
            ->putJson("/api/products/{$product->id}", [
                'images' => [UploadedFile::fake()->image('new.jpg')],
            ])
            ->assertOk();

        $newImage = $product->images()->orderBy('sort_order', 'desc')->first();

        $this->assertFalse((bool) $newImage->is_primary);
    }

    public function test_manager_can_delete_a_product_image(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);
        Storage::disk('cloudinary')->put($image->path, 'fake-contents');

        $this->actingAs($manager, 'clerk')
            ->deleteJson("/api/products/{$product->id}/images/{$image->id}")
            ->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('cloudinary')->assertMissing($image->path);
    }

    public function test_deleting_primary_image_promotes_next_image_as_primary(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create();
        $primary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $secondary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        Storage::disk('cloudinary')->put($primary->path, 'fake-contents');

        $this->actingAs($manager, 'clerk')
            ->deleteJson("/api/products/{$product->id}/images/{$primary->id}")
            ->assertOk();

        $this->assertTrue((bool) $secondary->fresh()->is_primary);
    }

    public function test_deleting_product_removes_its_images_from_storage(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);
        Storage::disk('cloudinary')->put($image->path, 'fake-contents');

        $this->actingAs($admin, 'clerk')
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('cloudinary')->assertMissing($image->path);
    }
}
