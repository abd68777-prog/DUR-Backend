<?php

namespace App\Support;

use App\Models\Promotion;

/**
 * الخصم يلي فعلاً انطبق على منتج، وأصله.
 */
final readonly class AppliedDiscount
{
    public const SOURCE_PRODUCT = 'product';

    public const SOURCE_PROMOTION = 'promotion';

    public function __construct(
        public string $source,
        public string $type,
        public float $value,
        public float $amount,
        public ?Promotion $promotion = null,
    ) {}

    public static function fromProduct(float $percentage, float $amount): self
    {
        return new self(
            source: self::SOURCE_PRODUCT,
            type: Promotion::TYPE_PERCENTAGE,
            value: $percentage,
            amount: $amount,
        );
    }

    public static function fromPromotion(Promotion $promotion, float $amount): self
    {
        return new self(
            source: self::SOURCE_PROMOTION,
            type: $promotion->type,
            value: (float) $promotion->value,
            amount: $amount,
            promotion: $promotion,
        );
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'type' => $this->type,
            'value' => $this->value,
            'amount' => $this->amount,
            // فاضيين لما يكون الخصم يدوي على المنتج.
            'promotion_id' => $this->promotion?->id,
            'name_ar' => $this->promotion?->name_ar,
            'name_en' => $this->promotion?->name_en,
            'ends_at' => $this->promotion?->ends_at?->toIso8601String(),
        ];
    }
}
