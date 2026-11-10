<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $email
 * @property string $city
 * @property string $region
 * @property float $lat
 * @property float $lng
 */
class Customer extends Model
{
    protected $fillable = ['name', 'email', 'city', 'region', 'lat', 'lng', 'shopify_id'];

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
