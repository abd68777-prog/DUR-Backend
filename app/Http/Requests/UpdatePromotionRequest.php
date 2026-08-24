<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PromotionUpdateInput',
    description: 'Every field is optional - send only what changes.',
    properties: [
        new OA\Property(property: 'name_ar', type: 'string', maxLength: 255),
        new OA\Property(property: 'name_en', type: 'string', maxLength: 255),
        new OA\Property(property: 'code', type: 'string', maxLength: 50, nullable: true, description: 'Send null to turn a coded promotion into an automatic one'),
        new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed']),
        new OA\Property(property: 'value', type: 'number', format: 'float'),
        new OA\Property(property: 'scope', type: 'string', enum: ['all', 'products', 'categories']),
        new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Replaces the current selection entirely'),
        new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Replaces the current selection entirely'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
    ]
)]
class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
        $promotion = $this->route('promotion');

        // النوع الجديد إذا انبعت، وإلا النوع المخزّن - حتى سقف الـ 100% يضل
        // ينطبق لما ينبعت value لحاله على عرض نسبة موجود.
        $type = $this->input('type', $promotion?->type);

        return [
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('promotions', 'code')->ignore($promotion)],
            'type' => ['sometimes', Rule::in([Promotion::TYPE_PERCENTAGE, Promotion::TYPE_FIXED])],
            'value' => [
                'sometimes', 'numeric', 'min:0.01',
                Rule::when($type === Promotion::TYPE_PERCENTAGE, ['max:100']),
            ],
            'scope' => ['sometimes', Rule::in([
                Promotion::SCOPE_ALL,
                Promotion::SCOPE_PRODUCTS,
                Promotion::SCOPE_CATEGORIES,
            ])],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
