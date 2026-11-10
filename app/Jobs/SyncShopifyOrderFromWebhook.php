<?php

namespace App\Jobs;

use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Services\Shopify\Importer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa un webhook de pedido: resincroniza ese pedido desde Shopify
 * (idempotente) y difunde los mismos eventos en vivo que produce el
 * simulador, para que el feed de actividad y las alertas reaccionen igual.
 */
final class SyncShopifyOrderFromWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $gid) {}

    public function handle(Importer $importer): void
    {
        $resultado = $importer->upsertOrderFromShopify($this->gid);

        $order = $resultado['order'];
        if ($order === null) {
            Log::notice('Webhook de Shopify: pedido no recuperable', ['gid' => $this->gid]);

            return;
        }

        $now = now()->toIso8601String();
        $previo = $resultado['previousStatus'];

        if ($previo === null) {
            // Pedido nuevo: el feed lo trata como ingreso.
            $customer = $order->customer()->first();
            $items = $order->items()->pluck('quantity')->sum();

            OrderCreated::dispatch(
                (string) $order->id,
                $customer !== null ? $customer->name : 'Cliente Shopify',
                (float) $order->total,
                $customer !== null ? $customer->city : '(sin ciudad)',
                (int) $items,
                $now,
            )->toOthers();

            return;
        }

        if ($previo !== $order->status) {
            OrderStatusChanged::dispatch((string) $order->id, (string) $previo, (string) $order->status, $now)->toOthers();
        }
    }
}
