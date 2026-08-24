<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionService
{
    /**
     * العروض التلقائية الشغّالة، محمّلة مرة وحدة بالطلب.
     *
     * بدون هالتخزين كل منتج بلستة من 50 منتج بيعمل استعلاماته الخاصة.
     *
     * @var Collection<int, Promotion>|null
     */
    private ?Collection $automatic = null;

    /** @return Collection<int, Promotion> */
    public function runningAutomatic(): Collection
    {
        return $this->automatic ??= Promotion::query()
            ->running()
            ->automatic()
            ->with(['products:id', 'categories:id'])
            ->get();
    }

    /**
     * أكبر خصم بينطبق على المنتج، أو null إذا ما في عرض.
     * لو أكتر من عرض بينطبق، الزبون بياخد الأفضل إله.
     */
    public function bestFor(Product $product): ?Promotion
    {
        $price = (float) $product->price;

        return $this->runningAutomatic()
            ->filter(fn (Promotion $promotion) => $promotion->appliesTo($product))
            ->sortByDesc(fn (Promotion $promotion) => $promotion->discountOn($price))
            ->first();
    }

    /**
     * بيلاقي عرض شغّال بهالكود. المطابقة بدون حساسية لحالة الأحرف لأن
     * الأكواد بتنخزن capital وبتتحوّل قبل التحقق.
     */
    public function findByCode(string $code): ?Promotion
    {
        return Promotion::query()
            ->running()
            ->where('code', strtoupper(trim($code)))
            ->with(['products:id,slug,name_ar,name_en', 'categories:id,slug,name_ar,name_en'])
            ->first();
    }

    /**
     * المنتجات يلي بينطبق عليها العرض. للعرض على مستوى المتجر منرجّع null
     * بدل ما نعدّد الكاتالوج كله.
     *
     * @return Collection<int, Product>|null
     */
    public function productsFor(Promotion $promotion): ?Collection
    {
        return match ($promotion->scope) {
            Promotion::SCOPE_ALL => null,
            Promotion::SCOPE_PRODUCTS => $promotion->products,
            Promotion::SCOPE_CATEGORIES => Product::whereIn(
                'category_id',
                $promotion->categories->pluck('id')
            )->get(),
            default => null,
        };
    }
}
