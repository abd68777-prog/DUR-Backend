<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/categories',
        summary: 'قائمة التصنيفات',
        description: 'كل التصنيفات (بدون pagination). متاح لأي مستخدم مسجّل دخول.',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'قائمة التصنيفات',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Category'))
            ),
            new OA\Response(response: 401, description: 'مش مسجّل دخول', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(CategoryResource::collection(Category::latest()->get()));
    }

    #[OA\Get(
        path: '/categories/{category}',
        summary: 'تفاصيل تصنيف',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'بيانات التصنيف', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 404, description: 'التصنيف غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Category $category): JsonResponse
    {
        return response()->json(new CategoryResource($category));
    }

    #[OA\Post(
        path: '/categories',
        summary: 'إنشاء تصنيف',
        description: 'admin أو manager فقط.',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CategoryInput')
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء التصنيف', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'بيانات غير صحيحة (مثلاً slug مكرر)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json(new CategoryResource($category), 201);
    }

    #[OA\Put(
        path: '/categories/{category}',
        summary: 'تعديل تصنيف',
        description: 'admin أو manager فقط.',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: '#/components/schemas/CategoryUpdateInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تعديل التصنيف', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'التصنيف غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'بيانات غير صحيحة', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(new CategoryResource($category));
    }

    #[OA\Delete(
        path: '/categories/{category}',
        summary: 'حذف تصنيف',
        description: 'admin فقط. بيحذف منتجات التصنيف معه (cascade).',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'صلاحية غير كافية', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'التصنيف غير موجود', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'تم حذف التصنيف بنجاح']);
    }
}
