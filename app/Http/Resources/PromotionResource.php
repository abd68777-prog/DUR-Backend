<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Promotion',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name_ar', type: 'string', example: 'تخفيضات العيد'),
        new OA\Property(property: 'name_en', type: 'string', example: 'Eid Sale'),
        new OA\Property(property: 'code', type: 'string', nullable: true, example: 'EID2026', description: 'null means the promotion applies automatically; otherwise the customer must enter this code'),
        new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed'], example: 'percentage'),
        new OA\Property(property: 'value', type: 'number', format: 'float', example: 20, description: 'Percent when type is percentage, currency amount when fixed'),
        new OA\Property(property: 'scope', type: 'string', enum: ['all', 'products', 'categories'], example: 'categories'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true, description: 'null means it has no start limit'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true, description: 'null means it never expires'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'is_running', type: 'boolean', example: true, description: 'Active AND within its date range right now'),
        new OA\Property(property: 'products', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product'), description: 'Only when scope is "products"'),
        new OA\Property(property: 'categories', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category'), description: 'Only when scope is "categories"'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'scope' => $this->scope,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'is_running' => $this->isRunning(),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
