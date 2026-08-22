<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CategoryUpdateInput',
    properties: [
        new OA\Property(property: 'name_ar', type: 'string', maxLength: 255),
        new OA\Property(property: 'name_en', type: 'string', maxLength: 255),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255),
        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
    ]
)]
class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // multipart/form-data (لازم لرفع الصورة) بيبعت كل شي كـ string، فـ "true"/"false"
        // ما بتعديها قاعدة boolean الافتراضية (بتقبل بس true/false/0/1/"0"/"1").
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
