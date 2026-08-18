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
        summary: 'قائمة المنتجات',
        description: 'قائمة مع pagination وفلاتر اختيارية. متاح لأي مستخدم مسجّل دخول.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', description: 'بحث بالاسم', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'قائمة المنتجات (مقسّمة صفحات)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'مش مسجّل دخول', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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
        summary: 'تفاصيل منتج',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'بيانات المنتج', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 404, description: 'المنتج غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        return response()->json(new ProductResource($product->load('images', 'category')));
    }

    #[OA\Post(
        path: '/products',
        summary: 'إنشاء منتج',
        description: 'admin أو manager فقط. يدعم رفع صور متعددة.',
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
            new OA\Response(response: 201, description: 'تم إنشاء المنتج', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'بيانات غير صحيحة', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
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
        summary: 'تعديل منتج',
        description: 'admin أو manager فقط. الصور المرفوعة بتنضاف للموجودة، ما بتستبدلها.',
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
            new OA\Response(response: 200, description: 'تم تعديل المنتج', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'المنتج غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'بيانات غير صحيحة', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
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
        summary: 'تفعيل / تعطيل المنتج',
        description: 'admin أو manager فقط. بتعكس قيمة is_active الحالية.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'الحالة الجديدة للمنتج', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'المنتج غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function toggleActive(Product $product): JsonResponse
    {
        $product = $this->productService->toggleActive($product);

        return response()->json(new ProductResource($product));
    }

    #[OA\Delete(
        path: '/products/{product}',
        summary: 'حذف منتج',
        description: 'admin فقط. بيحذف صور المنتج من Cloudinary قبل حذف السجل.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'المنتج غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json(['message' => 'تم حذف المنتج بنجاح']);
    }

    #[OA\Delete(
        path: '/products/{product}/images/{image}',
        summary: 'حذف صورة منتج',
        description: 'admin أو manager فقط. لو الصورة المحذوفة كانت الرئيسية (is_primary)، أول صورة متبقية بترثها.',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الصورة', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'الصورة غير موجودة', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->productService->deleteImage($image);

        return response()->json(['message' => 'تم حذف الصورة بنجاح']);
    }
}
