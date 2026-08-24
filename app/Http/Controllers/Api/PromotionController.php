<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotions) {}

    #[OA\Get(
        path: '/promotions',
        summary: 'List promotions',
        description: 'Every promotion, running or not. Admin and manager only.',
        security: [['bearerAuth' => []]],
        tags: ['Promotions'],
        parameters: [
            new OA\Parameter(name: 'running', in: 'query', description: 'true returns only promotions live right now, false only those that are not', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of promotions', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Promotion'))),
            new OA\Response(response: 403, description: 'Not allowed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query()->with(['products', 'categories'])->latest();

        if ($request->filled('running')) {
            filter_var($request->input('running'), FILTER_VALIDATE_BOOLEAN)
                ? $query->running()
                : $query->whereNot(fn ($q) => $q->running());
        }

        return response()->json(PromotionResource::collection($query->get()));
    }

    #[OA\Get(
        path: '/promotions/active',
        summary: 'Currently running automatic promotions',
        description: 'Public. Only promotions that apply without a code and are inside their date range - use it for banners and badges. Code-based promotions are never listed here, otherwise the codes would be public.',
        tags: ['Promotions'],
        responses: [
            new OA\Response(response: 200, description: 'Running automatic promotions', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Promotion'))),
        ]
    )]
    public function active(): JsonResponse
    {
        $promotions = Promotion::query()
            ->running()
            ->automatic()
            ->with(['products', 'categories'])
            ->latest()
            ->get();

        return response()->json(PromotionResource::collection($promotions));
    }

    #[OA\Post(
        path: '/promotions/validate',
        summary: 'Check a discount code',
        description: 'Public. Send a code the customer typed; returns the promotion and what it covers, or 404 when the code is unknown, disabled, or outside its date range.',
        tags: ['Promotions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [new OA\Property(property: 'code', type: 'string', example: 'EID2026')]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'The code is valid',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'promotion', ref: '#/components/schemas/Promotion'),
                    new OA\Property(property: 'products', type: 'array', nullable: true, items: new OA\Items(ref: '#/components/schemas/Product'), description: 'Products the code covers. null means the whole store.'),
                ])
            ),
            new OA\Response(response: 404, description: 'Unknown, disabled, or expired code', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'No code supplied', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $promotion = $this->promotions->findByCode($data['code']);

        if (! $promotion) {
            // نفس الرد لكود مش موجود ولكود منتهي - حتى ما نساعد حدا يخمّن أكواد.
            return response()->json([
                'message' => 'This discount code is not valid.',
            ], 404);
        }

        $products = $this->promotions->productsFor($promotion);

        return response()->json([
            'promotion' => new PromotionResource($promotion),
            'products' => $products === null ? null : ProductResource::collection($products),
        ]);
    }

    #[OA\Get(
        path: '/promotions/{promotion}',
        summary: 'Promotion details',
        security: [['bearerAuth' => []]],
        tags: ['Promotions'],
        parameters: [
            new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Promotion data', content: new OA\JsonContent(ref: '#/components/schemas/Promotion')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json(new PromotionResource($promotion->load(['products', 'categories'])));
    }

    #[OA\Post(
        path: '/promotions',
        summary: 'Create a promotion',
        security: [['bearerAuth' => []]],
        tags: ['Promotions'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Promotion')),
            new OA\Response(response: 403, description: 'Not allowed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = Promotion::create($request->safe()->except(['product_ids', 'category_ids']));

        $this->syncTargets($promotion, $request->validated());

        // fresh مش load: is_active بياخد قيمته الافتراضية من الـ DB لما ما
        // تنبعت بالطلب، والنسخة بالذاكرة بتضلها null بدونها.
        return response()->json(
            new PromotionResource($promotion->fresh(['products', 'categories'])),
            201
        );
    }

    #[OA\Put(
        path: '/promotions/{promotion}',
        summary: 'Update a promotion',
        security: [['bearerAuth' => []]],
        tags: ['Promotions'],
        parameters: [
            new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionUpdateInput')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Promotion')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($request->safe()->except(['product_ids', 'category_ids']));

        $this->syncTargets($promotion, $request->validated());

        return response()->json(new PromotionResource($promotion->fresh(['products', 'categories'])));
    }

    #[OA\Patch(
        path: '/promotions/{promotion}/toggle-active',
        summary: 'Turn a promotion on or off',
        description: 'Flips is_active without touching the dates - the quickest way to stop a live promotion.',
        security: [['bearerAuth' => []]],
        tags: ['Promotions'],
        parameters: [
            new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Promotion')),
        ]
    )]
    public function toggleActive(Promotion $promotion): JsonResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return response()->json(new PromotionResource($promotion->fresh(['products', 'categories'])));
    }

    #[OA\Delete(
        path: '/promotions/{promotion}',
        summary: 'Delete a promotion',
        description: 'Admin only. Products keep their prices; only the promotion and its product/category links are removed.',
        security: [['bearerAuth' => []]],
        tags: ['Promotions'],
        parameters: [
            new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Not allowed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted successfully.']);
    }

    /**
     * بيربط المنتجات/التصنيفات حسب النطاق، وبيفضّي الروابط يلي ما عادت تلزم.
     * بدون التفضية بتضل روابط قديمة مخبّاية وبترجع تظهر لو رجع النطاق لقيمته
     * الأولى - يعني خصم بينطبق على منتجات حدا ظنّ إنه شالها.
     */
    private function syncTargets(Promotion $promotion, array $data): void
    {
        if ($promotion->scope === Promotion::SCOPE_PRODUCTS) {
            if (array_key_exists('product_ids', $data)) {
                $promotion->products()->sync($data['product_ids']);
            }

            $promotion->categories()->detach();

            return;
        }

        if ($promotion->scope === Promotion::SCOPE_CATEGORIES) {
            if (array_key_exists('category_ids', $data)) {
                $promotion->categories()->sync($data['category_ids']);
            }

            $promotion->products()->detach();

            return;
        }

        $promotion->products()->detach();
        $promotion->categories()->detach();
    }
}
