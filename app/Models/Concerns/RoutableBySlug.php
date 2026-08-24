<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * بيخلي روابط الـ API تشتغل بالـ slug: /api/products/gold-ring-21k
 *
 * الـ id بيضل شغّال كمان عن قصد - في روابط قديمة عند الفرونت، وفي منتجات
 * انعملت قبل ما ينضاف عمود slug فقيمتها null وما إلها إلا الـ id.
 */
trait RoutableBySlug
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        // binding صريح متل {product:id} بيتصرف متل الأصل بدون تدخّل.
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        if ($model = $this->where('slug', $value)->first()) {
            return $model;
        }

        // الرقمي بس منجرّبه كـ id، حتى ما ندوّر على slug نصّي بعمود رقمي.
        return is_numeric($value)
            ? $this->whereKey($value)->first()
            : null;
    }
}
