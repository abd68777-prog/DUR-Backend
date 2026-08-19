<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    #[OA\Get(
        path: '/categories',
        summary: 'List categories',
        description: 'All categories (no pagination). Available to any authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of categories',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Category'))
            ),
            new OA\Response(response: 401, description: 'Not authenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(CategoryResource::collection(Category::latest()->get()));
    }

    #[OA\Get(
        path: '/categories/{category}',
        summary: 'Category details',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category data', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 404, description: 'Category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Category $category): JsonResponse
    {
        return response()->json(new CategoryResource($category));
    }

    #[OA\Post(
        path: '/categories',
        summary: 'Create a category',
        description: 'admin or manager only. Supports uploading a single image.',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/CategoryInput')
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid data (e.g. duplicate slug)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            $request->safe()->except('image'),
            $request->file('image')
        );

        return response()->json(new CategoryResource($category), 201);
    }

    #[OA\Put(
        path: '/categories/{category}',
        summary: 'Update a category',
        description: 'admin or manager only. Uploading a new image replaces the old one.',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/CategoryUpdateInput')
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid data', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categoryService->update(
            $category,
            $request->safe()->except('image'),
            $request->file('image')
        );

        return response()->json(new CategoryResource($category));
    }

    #[OA\Delete(
        path: '/categories/{category}',
        summary: 'Delete a category',
        description: 'admin only. Deletes the category\'s products with it (cascade).',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
