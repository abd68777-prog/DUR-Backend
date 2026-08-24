<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductUpdateInput',
    properties: [
        new OA\Property(property: 'category_id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255),
        new OA\Property(property: 'name_ar', type: 'string', maxLength: 255),
        new OA\Property(property: 'name_en', type: 'string', maxLength: 255),
        new OA\Property(property: 'description_ar', type: 'string', nullable: true),
        new OA\Property(property: 'description_en', type: 'string', nullable: true),
        new OA\Property(property: 'gold_weight', type: 'number', format: 'float', nullable: true, minimum: 0),
        new OA\Property(property: 'karat', type: 'string', enum: ['18', '21', '22', '24'], nullable: true),
        new OA\Property(property: 'gemstone_type', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'gemstone_carat', type: 'number', format: 'float', nullable: true, minimum: 0),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
        new OA\Property(property: 'has_discount', type: 'boolean', nullable: true, description: 'Switch the manual discount on or off. Turning it on needs discount_value, unless the product already has one stored.'),
        new OA\Property(property: 'discount_value', type: 'number', format: 'float', nullable: true, minimum: 0.01, maximum: 100, example: 20, description: 'Discount percentage, 0.01 to 100'),
        new OA\Property(property: 'stock', type: 'integer', nullable: true, minimum: 0),
        new OA\Property(
            property: 'images',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'binary'),
            description: 'Additional images added to the product (up to 4MB per image)'
        ),
    ]
)]
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // multipart بيبعت كل شي كـ string، فـ "true"/"false" ما بتعديها
        // قاعدة boolean الافتراضية.
        if ($this->has('has_discount')) {
            $this->merge([
                'has_discount' => filter_var($this->input('has_discount'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        $product = $this->route('product');

        // النسبة إلزامية بس لما نفعّل الخصم وما في قيمة محفوظة من قبل - حتى
        // الإدارة تقدر ترجّع تشغّل خصم قديم بإرسال has_discount لحاله.
        $turningOnWithoutStoredValue = $this->has('has_discount')
            && $this->boolean('has_discount')
            && $product?->discount_value === null;

        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'gold_weight' => ['nullable', 'numeric', 'min:0'],
            'karat' => ['nullable', 'in:18,21,22,24'],
            'gemstone_type' => ['nullable', 'string', 'max:255'],
            'gemstone_carat' => ['nullable', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'has_discount' => ['sometimes', 'boolean'],
            'discount_value' => [
                'nullable',
                Rule::requiredIf($turningOnWithoutStoredValue),
                'numeric', 'min:0.01', 'max:100',
            ],
            'stock' => ['nullable', 'integer', 'min:0'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_value.required' => 'Enter the discount percentage when switching the discount on.',
        ];
    }
}
