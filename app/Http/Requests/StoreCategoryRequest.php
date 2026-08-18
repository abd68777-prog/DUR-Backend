<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CategoryInput',
    required: ['name', 'slug'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'خواتم'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'rings'),
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
