<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function index(): JsonResponse
    {
        return response()->json(['message' => 'لوحة التحكم']);
    }

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
