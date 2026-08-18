<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Category',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'خواتم'),
        new OA\Property(property: 'slug', type: 'string', example: 'rings'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'products_count', type: 'integer', nullable: true, example: 12, description: 'موجود فقط بمكان محدد مثل /dashboard/stats'),
    ]
)]
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
