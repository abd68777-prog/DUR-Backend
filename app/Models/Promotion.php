<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const SCOPE_ALL = 'all';

    public const SCOPE_PRODUCTS = 'products';

    public const SCOPE_CATEGORIES = 'categories';

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'type',
        'value',
        'scope',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * مفعّل + ضمن الفترة الزمنية. تاريخ null معناه مفتوح من هداك الطرف.
     */
    public function scopeRunning(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    /** عروض بتنطبق لحالها بدون ما الزبون يدخّل شي. */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->whereNull('code');
    }

    /** عروض بدها كود. */
    public function scopeRequiringCode(Builder $query): Builder
    {
        return $query->whereNotNull('code');
    }

    public function isRunning(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($at));
    }

    public function appliesTo(Product $product): bool
    {
        return match ($this->scope) {
            self::SCOPE_ALL => true,
            self::SCOPE_PRODUCTS => $this->products->contains('id', $product->id),
            self::SCOPE_CATEGORIES => $this->categories->contains('id', $product->category_id),
            default => false,
        };
    }

    /**
     * قيمة الخصم بالليرة على سعر معيّن. الخصم الثابت ما بينزل السعر تحت الصفر.
     */
    public function discountOn(float $price): float
    {
        $discount = $this->type === self::TYPE_PERCENTAGE
            ? $price * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $price), 2);
    }
}
