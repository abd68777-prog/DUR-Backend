<?php

namespace App\Services;

use App\Models\Product;
use App\Support\AppliedDiscount;

/**
 * مصدر الحقيقة الوحيد لسعر المنتج النهائي.
 *
 * في مصدرين للخصم: خصم يدوي على المنتج نفسه (الآلية الحالية، لأن الطلب
 * بيصير عبر واتساب)، وعروض ترويجية (للتوسّع المستقبلي). ما بيتراكموا -
 * الزبون بياخد الأكبر إله.
 */
class PricingService
{
    public function __construct(private PromotionService $promotions) {}

    public function discountFor(Product $product): ?AppliedDiscount
    {
        $price = (float) $product->price;

        $candidates = [];

        $own = $product->ownDiscountAmount();

        if ($own > 0) {
            $candidates[] = AppliedDiscount::fromProduct((float) $product->discount_value, $own);
        }

        if ($promotion = $this->promotions->bestFor($product)) {
            $candidates[] = AppliedDiscount::fromPromotion($promotion, $promotion->discountOn($price));
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (AppliedDiscount $a, AppliedDiscount $b) => $b->amount <=> $a->amount);

        return $candidates[0];
    }

    public function finalPrice(Product $product): float
    {
        $price = (float) $product->price;

        return round($price - ($this->discountFor($product)?->amount ?? 0.0), 2);
    }
}
