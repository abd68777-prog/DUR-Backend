<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CategoryInput',
    required: ['name_ar', 'name_en', 'slug'],
    properties: [
        new OA\Property(property: 'name_ar', type: 'string', maxLength: 255, example: 'خواتم'),
        new OA\Property(property: 'name_en', type: 'string', maxLength: 255, example: 'Rings'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'rings'),
        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', nullable: true, example: true),
    ]
)]
class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية مسؤولية الـ middleware بالـ route
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
