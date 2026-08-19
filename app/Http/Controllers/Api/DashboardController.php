<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 5;

    #[OA\Get(
        path: '/dashboard',
        summary: 'Dashboard welcome message',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'admin or manager only', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'Dashboard']);
    }

    #[OA\Get(
        path: '/dashboard/stats',
        summary: 'Dashboard statistics',
        description: 'admin only. Product counts, breakdown by category, low-stock products (below 5), and the latest 10 products.',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'products_count',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 42),
                                new OA\Property(property: 'active', type: 'integer', example: 35),
                                new OA\Property(property: 'inactive', type: 'integer', example: 7),
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'products_by_category', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category')),
                        new OA\Property(property: 'low_stock_products', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                        new OA\Property(property: 'latest_products', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function stats(): JsonResponse
    {
        return response()->json([
            'products_count' => [
                'total' => Product::count(),
                'active' => Product::where('is_active', true)->count(),
                'inactive' => Product::where('is_active', false)->count(),
            ],
            'products_by_category' => CategoryResource::collection(
                Category::withCount('products')->get()
            ),
            'low_stock_products' => ProductResource::collection(
                Product::where('stock', '<', self::LOW_STOCK_THRESHOLD)
                    ->with('category')
                    ->orderBy('stock')
                    ->get()
            ),
            'latest_products' => ProductResource::collection(
                Product::with('category')
                    ->latest()
                    ->take(10)
                    ->get()
            ),
        ]);
    }
}
