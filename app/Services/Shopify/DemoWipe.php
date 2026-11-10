<?php

namespace App\Services\Shopify;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Borrado de datos demo compartido por el comando shopify:import y la UI
 * de Settings → Shopify. Cuenta y borra en orden de FKs respetado.
 *
 * - demoOnly(): deja lo importado de Shopify (filas con shopify_id).
 * - everything(): reemplazo limpio total, como `shopify:import --full`.
 */
final class DemoWipe
{
    /** @return array<string, int> cantidades borradas por tabla */
    public static function demoOnly(): array
    {
        $counts = [];

        $orphanOrders = Order::query()->whereNull('shopify_id')->pluck('id');
        $counts['items'] = $orphanOrders->isNotEmpty()
            ? OrderItem::query()->whereIn('order_id', $orphanOrders)->delete()
            : 0;

        $counts = [
            'actividades' => Activity::query()->delete(),
            'items' => $counts['items'],
            'pedidos' => $orphanOrders->isEmpty() ? 0 : Order::query()->whereIn('id', $orphanOrders)->delete(),
            'variantes' => ProductVariant::query()->whereNull('shopify_id')->delete(),
            'productos' => Product::query()->whereNull('shopify_id')->delete(),
            'clientes' => Customer::query()->whereNull('shopify_id')->delete(),
            'categorias' => Category::query()->doesntHave('products')->delete(),
        ];

        return array_filter($counts);
    }

    /** @return array<string, int> cantidades borradas por tabla */
    public static function everything(): array
    {
        $counts = [
            'actividades' => Activity::query()->delete(),
            'items' => OrderItem::query()->delete(),
            'pedidos' => Order::query()->delete(),
            'variantes' => ProductVariant::query()->delete(),
            'productos' => Product::query()->delete(),
            'clientes' => Customer::query()->delete(),
            'categorias' => Category::query()->delete(),
        ];

        return array_filter($counts);
    }

    /**
     * Resumen legible: "3 pedidos, 5 clientes…", o null si no borró nada.
     *
     * @param  array<string, int>  $counts
     */
    public static function humanize(array $counts): ?string
    {
        $names = [
            'actividades' => 'actividades',
            'items' => 'ítems',
            'pedidos' => 'pedidos',
            'variantes' => 'variantes',
            'productos' => 'productos',
            'clientes' => 'clientes',
            'categorias' => 'categorías',
        ];

        $parts = collect($counts)
            ->filter(fn ($n) => $n > 0)
            ->map(fn ($n, $key) => $n.' '.($names[$key] ?? $key))
            ->values();

        return $parts->isNotEmpty() ? $parts->implode(', ') : null;
    }
}
