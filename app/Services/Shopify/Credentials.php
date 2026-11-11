<?php

namespace App\Services\Shopify;

use App\Models\ShopifySetting;

/**
 * Resuelve las credenciales Shopify con prioridad:
 * fila en shopify_settings (guardada desde Settings → Shopify)  >  .env.
 */
final class Credentials
{
    private static ?ShopifySetting $row = null;

    public static function store(): string
    {
        return self::row()?->store_domain ?: (string) config('shopify.store');
    }

    public static function clientId(): string
    {
        return trim((string) (self::row()?->client_id ?: (string) config('shopify.client_id')));
    }

    public static function clientSecret(): string
    {
        return trim((string) (self::row()?->client_secret ?: (string) config('shopify.client_secret')));
    }

    /** Token estático legacy (SHOPIFY_ACCESS_TOKEN en .env). Vacío = usar client credentials. */
    public static function token(): string
    {

        return (string) config('shopify.token');
    }

    public static function webhookSecret(): string
    {
        return self::row()?->webhook_secret ?: (string) config('shopify.webhook_secret');
    }

    public static function configured(): bool
    {
        return self::store() !== ''
            && (self::clientId() !== '' && self::clientSecret() !== '' || self::token() !== '');
    }

    private static function row(): ?ShopifySetting
    {
        if (self::$row === null) {
            self::$row = ShopifySetting::current();
        }

        return self::$row;
    }

    /** El resolver guarda en caché la fila: invalidarla tras escrituras. */
    public static function forget(): void
    {
        self::$row = null;
    }
}
