<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Category',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'image', type: 'string', format: 'uri', nullable: true, example: 'https://res.cloudinary.com/demo/image/upload/v1/categories/abc123.jpg'),
        new OA\Property(property: 'name_ar', type: 'string', example: 'خواتم'),
        new OA\Property(property: 'name_en', type: 'string', example: 'Rings'),
        new OA\Property(property: 'slug', type: 'string', example: 'rings'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'products_count', type: 'integer', nullable: true, example: 12, description: 'Only present in specific places such as /dashboard/stats'),
    ]
)]
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image' => $this->image,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
