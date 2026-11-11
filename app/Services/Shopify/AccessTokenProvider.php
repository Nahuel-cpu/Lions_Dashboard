<?php

namespace App\Services\Shopify;

use App\Models\ShopifySetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Obtiene y cachea el token de acceso de la Admin API:
 *
 * - Modo actual (Dev Dashboard): client credentials grant contra
 *   https://{store}/admin/oauth/access_token con client_id + client_secret.
 *   El token dura 24h y se guarda cifrado hasta token_expires_at.
 * - Modo legacy (.env con SHOPIFY_ACCESS_TOKEN o token manual guardado):
 *   se usa tal cual, sin pedir nada a /oauth.
 */
final class AccessTokenProvider
{
    /** Margen de seguridad antes de expirar (renueva 5 min antes). */
    private const RENEW_MARGIN_SECONDS = 300;

    public static function get(string $store, string $legacyToken = ''): string
    {
        if ($legacyToken !== '') {
            return $legacyToken;
        }

        $clientId = Credentials::clientId();
        $clientSecret = Credentials::clientSecret();

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('No hay token ni client credentials de Shopify. Guardá el Client ID y el Secreto del cliente en Settings → Shopify (o SHOPIFY_CLIENT_ID / SHOPIFY_CLIENT_SECRET en .env).');
        }

        $fresh = self::cachedValidToken($store);
        if ($fresh !== '') {
            return $fresh;
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://'.$store.'/admin/oauth/access_token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $response->successful()) {
            $detail = (string) ($response->json('error_description')
                ?? $response->json('error')
                ?? substr($response->body(), 0, 300));

            throw new RuntimeException('No se pudo obtener el token de Shopify: '.$detail);
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 86_399);
        if ($token === '') {
            throw new RuntimeException('Shopify devolvió un token vacío.');
        }

        self::store($store, $token, $expiresIn);

        return $token;
    }

    private static function cachedValidToken(string $store): string
    {
        $row = ShopifySetting::current();

        if ($row === null
            || $row->store_domain !== $store
            || $row->access_token === null
            || $row->access_token === ''
            || $row->token_expires_at === null
            || $row->token_expires_at->lte(now()->addSeconds(self::RENEW_MARGIN_SECONDS))) {
            return '';
        }

        return $row->access_token;
    }

    private static function store(string $store, string $token, int $expiresIn): void
    {
        $row = ShopifySetting::query()->firstOrNew();

        if ($row->store_domain !== $store) {
            $row->access_token = null;
            $row->token_expires_at = null;
        }

        $row->store_domain = $store;
        $row->access_token = $token;
        $row->token_expires_at = now()->addSeconds(max($expiresIn - self::RENEW_MARGIN_SECONDS, 60));
        $row->save();

        Credentials::forget();
    }
}
