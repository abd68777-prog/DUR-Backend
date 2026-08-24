<?php

namespace App\Models;

use App\Models\Concerns\RoutableBySlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, RoutableBySlug;

    protected $fillable = [
        'category_id',
        'slug',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'gold_weight',
        'karat',
        'gemstone_type',
        'gemstone_carat',
        'price',
        'has_discount',
        'discount_value',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gold_weight' => 'decimal:3',
            'gemstone_carat' => 'decimal:2',
            'price' => 'decimal:2',
            'has_discount' => 'boolean',
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * قيمة الخصم اليدوي بالليرة على هالمنتج، أو صفر إذا ما في.
     *
     * has_discount لحاله ما بيكفي: ممكن تكون مفعّلة والقيمة لسه فاضية.
     */
    public function ownDiscountAmount(): float
    {
        if (! $this->has_discount || $this->discount_value === null) {
            return 0.0;
        }

        $price = (float) $this->price;

        return round(min($price * ((float) $this->discount_value / 100), $price), 2);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
