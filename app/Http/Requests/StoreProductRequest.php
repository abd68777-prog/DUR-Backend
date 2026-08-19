<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductInput',
    required: ['category_id', 'slug', 'name_ar', 'name_en', 'price'],
    properties: [
        new OA\Property(property: 'category_id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'gold-ring-21k'),
        new OA\Property(property: 'name_ar', type: 'string', maxLength: 255, example: 'خاتم ذهب عيار 21'),
        new OA\Property(property: 'name_en', type: 'string', maxLength: 255, example: '21K Gold Ring'),
        new OA\Property(property: 'description_ar', type: 'string', nullable: true),
        new OA\Property(property: 'description_en', type: 'string', nullable: true),
        new OA\Property(property: 'gold_weight', type: 'number', format: 'float', nullable: true, minimum: 0, example: 5.5),
        new OA\Property(property: 'karat', type: 'string', enum: ['18', '21', '22', '24'], nullable: true),
        new OA\Property(property: 'gemstone_type', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'gemstone_carat', type: 'number', format: 'float', nullable: true, minimum: 0),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 500.75),
        new OA\Property(property: 'stock', type: 'integer', nullable: true, minimum: 0, example: 10),
        new OA\Property(property: 'is_active', type: 'boolean', nullable: true, example: true),
        new OA\Property(
            property: 'images',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'binary'),
            description: 'Up to 4MB per image'
        ),
    ]
)]
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية مسؤولية الـ middleware بالـ route
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'gold_weight' => ['nullable', 'numeric', 'min:0'],
            'karat' => ['nullable', 'in:18,21,22,24'],
            'gemstone_type' => ['nullable', 'string', 'max:255'],
            'gemstone_carat' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:4096'], // 4MB لكل صورة
        ];
    }
}
