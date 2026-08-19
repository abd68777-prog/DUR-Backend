<?php

namespace App\Http\Resources;

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
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 500.75),
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
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
