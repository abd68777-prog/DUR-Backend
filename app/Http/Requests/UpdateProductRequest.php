<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductUpdateInput',
    properties: [
        new OA\Property(property: 'category_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'gold_weight', type: 'number', format: 'float', nullable: true, minimum: 0),
        new OA\Property(property: 'karat', type: 'string', enum: ['18', '21', '24'], nullable: true),
        new OA\Property(property: 'gemstone_type', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'gemstone_carat', type: 'number', format: 'float', nullable: true, minimum: 0),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
        new OA\Property(property: 'stock', type: 'integer', nullable: true, minimum: 0),
        new OA\Property(
            property: 'images',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'binary'),
            description: 'صور إضافية بتنضاف للمنتج (حتى 4 ميجا لكل صورة)'
        ),
    ]
)]
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gold_weight' => ['nullable', 'numeric', 'min:0'],
            'karat' => ['nullable', 'in:18,21,24'],
            'gemstone_type' => ['nullable', 'string', 'max:255'],
            'gemstone_carat' => ['nullable', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:4096'],
        ];
    }
}
