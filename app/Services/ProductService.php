<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function list(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Product::query()->with(['images', 'category']);

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data, array $images = []): Product
    {
        $product = Product::create($data);

        if (! empty($images)) {
            $this->attachImages($product, $images);
        }

        return $product->load('images', 'category');
    }

    public function update(Product $product, array $data, array $images = []): Product
    {
        $product->update($data);

        if (! empty($images)) {
            $this->attachImages($product, $images);
        }

        return $product->fresh(['images', 'category']);
    }

    public function toggleActive(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        foreach ($product->images as $image) {
            Storage::disk('cloudinary')->delete($image->path);
        }

        return $product->delete();
    }

    public function deleteImage(ProductImage $image): bool
    {
        Storage::disk('cloudinary')->delete($image->path);

        $wasPrimary = $image->is_primary;
        $productId = $image->product_id;

        $deleted = $image->delete();

        // لو حذفنا الصورة الرئيسية، خلي أول صورة متبقية هي الجديدة
        if ($deleted && $wasPrimary) {
            $next = ProductImage::where('product_id', $productId)->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }

        return $deleted;
    }

    private function attachImages(Product $product, array $images): void
    {
        $currentMax = $product->images()->max('sort_order') ?? -1;
        $hasPrimaryAlready = $product->images()->where('is_primary', true)->exists();

        foreach ($images as $index => $file) {
            /** @var UploadedFile $file */
            $path = $file->store('products', 'cloudinary');

            $product->images()->create([
                'path' => $path,
                'sort_order' => $currentMax + $index + 1,
                'is_primary' => ! $hasPrimaryAlready && $index === 0,
            ]);
        }
    }
}
