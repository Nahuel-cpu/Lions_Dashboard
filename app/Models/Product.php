<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $sku
 * @property int $cost_price
 * @property int $sale_price
 * @property int $low_stock_threshold
 */
class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'description',
        'cost_price',
        'sale_price',
        'low_stock_threshold',
        'shopify_id',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
