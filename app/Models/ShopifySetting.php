<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Credenciales Shopify persistidas (singleton: fila id=1).
 *
 * Con Dev Dashboard se guardan client_id/client_secret; el access_token
 * (columna access_token) ya no se escribe a mano: lo obtiene el
 * AccessTokenProvider vía client credentials grant y se cachea cifrado
 * hasta token_expires_at. Nada de esto viaja al frontend (solo flags).
 */
class ShopifySetting extends Model
{
    protected $fillable = [
        'store_domain', 'client_id', 'client_secret',
        'access_token', 'token_expires_at',
        'webhook_secret', 'last_check_at', 'last_check_ok', 'last_check_message',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'client_secret' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_check_at' => 'datetime',
        'last_check_ok' => 'boolean',
    ];

    public static function current(): ?self
    {
        return static::query()->first();
    }
}
