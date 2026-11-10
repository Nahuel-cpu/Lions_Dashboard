<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyOrderFromWebhook;
use App\Services\Shopify\Credentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe webhooks de la Admin API de Shopify (POST /webhooks/shopify).
 * Verifica la firma HMAC-SHA256 sobre el body crudo y encola el
 * procesamiento por topic para responder rápido (Shopify exige <5s).
 */
class ShopifyWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret = Credentials::webhookSecret();
        if ($secret === '') {
            Log::warning('Webhook de Shopify sin secret configurado');

            return response()->json(['error' => 'webhook secret no configurado'], 503);
        }

        $raw = (string) $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        $received = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        if (! hash_equals($expected, $received)) {
            Log::notice('Webhook de Shopify con firma inválida', ['topic' => $request->header('X-Shopify-Topic')]);

            return response()->json(['error' => 'firma inválida'], 401);
        }

        $topic = (string) $request->header('X-Shopify-Topic', '');
        $payload = json_decode($raw, true);

        // Topics soportados: orders/create, orders/update, orders/cancelled...
        // El payload REST trae admin_graphql_api_id: el job resuelve el estado
        // completo del pedido vía GraphQL usando ese GID.
        if (str_starts_with($topic, 'orders/') && is_array($payload)) {
            $gid = $payload['admin_graphql_api_id'] ?? null;
            if (is_string($gid) && $gid !== '') {
                SyncShopifyOrderFromWebhook::dispatch($gid);
            } else {
                Log::info('Webhook de pedidos sin admin_graphql_api_id (se ignora)', ['topic' => $topic]);
            }
        }

        return response()->json(['status' => 'accepted'], 202);
    }
}
