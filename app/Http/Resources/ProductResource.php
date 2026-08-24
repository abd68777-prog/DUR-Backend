<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', example: 'gold-ring-21k'),
        new OA\Property(property: 'category_id', type: 'integer', example: 1),
        new OA\Property(property: 'category', ref: '#/components/schemas/Category'),
        new OA\Property(property: 'name_ar', type: 'string', example: 'خاتم ذهب عيار 21'),
        new OA\Property(property: 'name_en', type: 'string', example: '21K Gold Ring'),
        new OA\Property(property: 'description_ar', type: 'string', nullable: true, example: 'خاتم ذهب مرصّع بالألماس'),
        new OA\Property(property: 'description_en', type: 'string', nullable: true, example: 'A diamond-studded gold ring'),
        new OA\Property(property: 'gold_weight', type: 'number', format: 'float', nullable: true, example: 5.5),
        new OA\Property(property: 'karat', type: 'string', enum: ['18', '21', '22', '24'], nullable: true, example: '21'),
        new OA\Property(property: 'gemstone_type', type: 'string', nullable: true, example: 'ألماس'),
        new OA\Property(property: 'gemstone_carat', type: 'number', format: 'float', nullable: true, example: 0.5),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 500.75, description: 'The original price, never discounted'),
        new OA\Property(property: 'final_price', type: 'number', format: 'float', example: 400.6, description: 'Price after the best running automatic promotion. Equals price when nothing applies - always safe to display.'),
        new OA\Property(property: 'has_discount', type: 'boolean', example: true, description: 'Whether a manual per-product discount is switched on'),
        new OA\Property(property: 'discount_value', type: 'number', format: 'float', nullable: true, example: 20, description: 'The manual discount percentage. Set but ignored while has_discount is false.'),
        new OA\Property(
            property: 'discount',
            nullable: true,
            description: 'The discount actually applied to final_price, or null when there is none. Comes from the manual per-product discount or from a running automatic promotion - whichever is larger. Code-based promotions never appear here.',
            properties: [
                new OA\Property(property: 'source', type: 'string', enum: ['product', 'promotion'], example: 'product', description: '"product" is the manual discount, "promotion" is a campaign'),
                new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed'], example: 'percentage'),
                new OA\Property(property: 'value', type: 'number', format: 'float', example: 20),
                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.15, description: 'Currency amount taken off this product'),
                new OA\Property(property: 'promotion_id', type: 'integer', nullable: true, description: 'null when source is "product"'),
                new OA\Property(property: 'name_ar', type: 'string', nullable: true, example: 'تخفيضات العيد'),
                new OA\Property(property: 'name_en', type: 'string', nullable: true, example: 'Eid Sale'),
                new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
            ],
            type: 'object'
        ),
        new OA\Property(property: 'stock', type: 'integer', example: 10),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'images', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductImage')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = (float) $this->price;
        $discount = app(PricingService::class)->discountFor($this->resource);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'gold_weight' => $this->gold_weight !== null ? (float) $this->gold_weight : null,
            'karat' => $this->karat,
            'gemstone_type' => $this->gemstone_type,
            'gemstone_carat' => $this->gemstone_carat !== null ? (float) $this->gemstone_carat : null,
            'price' => $price,
            'has_discount' => $this->has_discount,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'final_price' => round($price - ($discount?->amount ?? 0.0), 2),
            'discount' => $discount?->toArray(),
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
