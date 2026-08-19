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
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    #[OA\Get(
        path: '/products',
        summary: 'List products',
        description: 'Paginated list with optional filters. Available to any authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Search by name', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of products',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->list(
            $request->only(['category_id', 'is_active', 'search', 'per_page'])
        );

        $products->through(fn (Product $product) => new ProductResource($product));

        return response()->json($products);
    }

    #[OA\Get(
        path: '/products/{product}',
        summary: 'Product details',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product data', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        return response()->json(new ProductResource($product->load('images', 'category')));
    }

    #[OA\Post(
        path: '/products',
        summary: 'Create a product',
        description: 'admin or manager only. Supports uploading multiple images.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/ProductInput')
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid data', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $request->safe()->except('images'),
            $request->file('images', [])
        );

        return response()->json(new ProductResource($product), 201);
    }

    #[OA\Put(
        path: '/products/{product}',
        summary: 'Update a product',
        description: 'admin or manager only. Uploaded images are added to the existing ones, not replaced.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/ProductUpdateInput')
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Product updated', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid data', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update(
            $product,
            $request->safe()->except('images'),
            $request->file('images', [])
        );

        return response()->json(new ProductResource($product));
    }

    #[OA\Patch(
        path: '/products/{product}/toggle-active',
        summary: 'Activate / deactivate a product',
        description: 'admin or manager only. Flips the current is_active value.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The product\'s new state', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function toggleActive(Product $product): JsonResponse
    {
        $product = $this->productService->toggleActive($product);

        return response()->json(new ProductResource($product));
    }

    #[OA\Delete(
        path: '/products/{product}',
        summary: 'Delete a product',
        description: 'admin only. Deletes the product\'s images from Cloudinary before deleting the record.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    #[OA\Delete(
        path: '/products/{product}/images/{image}',
        summary: 'Delete a product image',
        description: 'admin or manager only. If the deleted image was primary (is_primary), the next remaining image inherits that flag.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Image not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->productService->deleteImage($image);

        return response()->json(['message' => 'Image deleted successfully.']);
    }
}
