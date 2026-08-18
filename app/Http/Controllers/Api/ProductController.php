<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->list(
            $request->only(['category_id', 'is_active', 'search', 'per_page'])
        );

        $products->through(fn (Product $product) => new ProductResource($product));

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(new ProductResource($product->load('images', 'category')));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $request->safe()->except('images'),
            $request->file('images', [])
        );

        return response()->json(new ProductResource($product), 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update(
            $product,
            $request->safe()->except('images'),
            $request->file('images', [])
        );

        return response()->json(new ProductResource($product));
    }

    public function toggleActive(Product $product): JsonResponse
    {
        $product = $this->productService->toggleActive($product);

        return response()->json(new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json(['message' => 'تم حذف المنتج بنجاح']);
    }

    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->productService->deleteImage($image);

        return response()->json(['message' => 'تم حذف الصورة بنجاح']);
    }
}
