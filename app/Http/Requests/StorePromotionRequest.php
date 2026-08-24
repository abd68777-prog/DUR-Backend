<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PromotionInput',
    required: ['name_ar', 'name_en', 'type', 'value', 'scope'],
    properties: [
        new OA\Property(property: 'name_ar', type: 'string', maxLength: 255, example: 'تخفيضات العيد'),
        new OA\Property(property: 'name_en', type: 'string', maxLength: 255, example: 'Eid Sale'),
        new OA\Property(property: 'code', type: 'string', maxLength: 50, nullable: true, example: 'EID2026', description: 'Omit for a promotion that applies automatically. Stored and matched uppercase.'),
        new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed'], example: 'percentage'),
        new OA\Property(property: 'value', type: 'number', format: 'float', example: 20, description: 'Capped at 100 when type is percentage'),
        new OA\Property(property: 'scope', type: 'string', enum: ['all', 'products', 'categories'], example: 'categories'),
        new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Required when scope is "products"'),
        new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Required when scope is "categories"'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true, description: 'Omit to start immediately'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true, description: 'Omit for no expiry'),
        new OA\Property(property: 'is_active', type: 'boolean', nullable: true, example: true),
    ]
)]
class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية مسؤولية الـ middleware بالـ route
    }

    protected function prepareForValidation(): void
    {
        // الكود بينتخزن capital حتى "eid2026" و"EID2026" يكونوا نفس الشي.
        if (filled($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:promotions,code'],
            'type' => ['required', Rule::in([Promotion::TYPE_PERCENTAGE, Promotion::TYPE_FIXED])],
            // نسبة فوق 100% بتعني سعر سالب، وخصم بصفر ما إله معنى.
            'value' => [
                'required', 'numeric', 'min:0.01',
                Rule::when($this->input('type') === Promotion::TYPE_PERCENTAGE, ['max:100']),
            ],
            'scope' => ['required', Rule::in([
                Promotion::SCOPE_ALL,
                Promotion::SCOPE_PRODUCTS,
                Promotion::SCOPE_CATEGORIES,
            ])],
            'product_ids' => ['required_if:scope,'.Promotion::SCOPE_PRODUCTS, 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['required_if:scope,'.Promotion::SCOPE_CATEGORIES, 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required_if' => 'Select at least one product when the scope is "products".',
            'category_ids.required_if' => 'Select at least one category when the scope is "categories".',
            'ends_at.after_or_equal' => 'The end date must not be before the start date.',
        ];
    }
}
