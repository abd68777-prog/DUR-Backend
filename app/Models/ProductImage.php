<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'path', 'sort_order', 'is_primary'];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => (string) cloudinary()->image($this->path)->toUrl(),
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
