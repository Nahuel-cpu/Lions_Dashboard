<?php

namespace App\Services\Shopify;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente mínimo de la Admin API GraphQL de Shopify.
 *
 * El token de acceso se resuelve con AccessTokenProvider: si hay un token
 * estático legacy (SHOPIFY_ACCESS_TOKEN) se usa tal cual; si no, se
 * obtiene por client credentials grant con el Client ID/Secreto guardados.
 * Shopify limita por "peso" de query (leaky bucket): cada request lleva un
 * pequeño delay según los puntos disponibles que reporta la respuesta
 * (extensions.cost.throttleStatus.currentlyAvailable).
 */
class ShopifyConnector
{
    private string $endpoint;

    private string $store;

    private string $legacyToken;

    private int $pointsRemaining = 100;

    public function __construct()
    {
        $store = Credentials::store();
        $this->legacyToken = Credentials::token();
        $version = (string) config('shopify.api_version', '2025-01');

        if ($store === '') {
            throw new RuntimeException('Shopify no está configurado: guardá las credenciales en Settings → Shopify, o SHOPIFY_STORE_DOMAIN en .env');
        }

        if ($this->legacyToken === '' && (Credentials::clientId() === '' || Credentials::clientSecret() === '')) {
            throw new RuntimeException('Faltan credenciales de Shopify: guardá el Client ID y el Secreto del cliente del Dev Dashboard en Settings → Shopify.');
        }

        $this->store = $store;
        $this->endpoint = 'https://'.preg_replace('!^https?://!', '', $store).'/admin/api/'.$version.'/graphql.json';
    }

    /** Info de la tienda; usado por shopify:ping para validar credenciales.
     * @return array<string, mixed>
     */
    public function shopInfo(): array
    {
        return $this->request(<<<'GQL'
            query {
              shop { name myshopifyDomain primaryDomain { url } }
            }
            GQL);
    }

    /**
     * Ejecuta una query GraphQL y devuelve el nodo "data". Lanza
     * RuntimeException con los errores de Shopify (throttling incluido).
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function request(string $gql, array $variables = []): array
    {
        $body = $this->call($gql, $variables);

        if (empty($body['data'])) {
            throw new RuntimeException('GraphQL no devolvió "data": '.json_encode($body, JSON_UNESCAPED_SLASHES));
        }

        if (! empty($body['errors'])) {
            foreach ((array) $body['errors'] as $error) {
                if (($error['extensions']['code'] ?? null) === 'THROTTLED') {
                    throw new ShopifyThrottledException;
                }
            }

            throw new RuntimeException('Errores GraphQL: '.json_encode($body['errors'], JSON_UNESCAPED_SLASHES));
        }

        $available = $body['extensions']['cost']['throttleStatus']['currentlyAvailable'] ?? null;
        if (is_numeric($available)) {
            $this->pointsRemaining = (int) $available;
        }

        return $body['data'];
    }

    /**
     * Con reintento único si Shopify responde THROTTLED.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function requestWithRetry(string $gql, array $variables = []): array
    {
        try {
            return $this->request($gql, $variables);
        } catch (ShopifyThrottledException) {
            sleep(3);

            return $this->request($gql, $variables);
        }
    }

    /**
     * Versión tolerante: captura excepciones para log en jobs.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>|null
     */
    public function safeRequest(string $gql, array $variables = []): ?array
    {
        try {
            return $this->request($gql, $variables);
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('Shopify GraphQL falló', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * HTTP crudo con delay del "bucket": espera 2s si quedan pocos puntos.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function call(string $gql, array $variables): array
    {
        if ($this->pointsRemaining < 20) {
            sleep(2);
        } else {
            usleep(500_000);
        }

        $response = Http::withHeaders(['X-Shopify-Access-Token' => $this->resolveToken()])
            ->asJson()
            ->timeout(60)
            ->post($this->endpoint, ['query' => $gql, 'variables' => $variables ?: new \stdClass])
            ->throw();

        return $response->json() ?? [];
    }

    /**
     * Token estático legacy si existe; si no, client credentials (con caché).
     */
    private function resolveToken(): string
    {
        return AccessTokenProvider::get($this->store, $this->legacyToken);
    }
}
