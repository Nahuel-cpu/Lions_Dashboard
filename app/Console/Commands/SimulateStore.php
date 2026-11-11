<?php

namespace App\Console\Commands;

use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\StockLow;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shopify\Credentials;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Simula operaciones de la tienda en tiempo real:
 * nuevos pedidos, confirmaciones, reposiciones y registros de clientes.
 */
class SimulateStore extends Command
{
    /** @var array<int, array{0: string, 1: float, 2: float}> */
    private const CITIES = [
        ['Buenos Aires', -34.6037, -58.3816],
        ['Córdoba', -31.4201, -64.1888],
        ['Rosario', -32.9442, -60.6505],
        ['Mendoza', -32.8895, -68.8458],
        ['La Plata', -34.9215, -57.9545],
        ['Neuquén', -38.9516, -68.0591],
    ];

    protected $signature = 'lions:simular {--intervalo=8 : Segundos entre sucesos}';

    protected $description = 'Genera actividad simulada de la tienda en vivo (pedidos, ingresos, reposiciones)';

    /** @var Collection<int, Product> */
    private Collection $products;

    public function handle(): int
    {

        if (Credentials::configured()) {
            $this->error('Shopify está conectado: el simulador está deshabilitado para no mezclar datos demo con los reales.');

            return self::FAILURE;
        }

        $intervalo = max(2, (int) $this->option('intervalo'));
        $this->products = Product::query()->with('variants')->get();

        if ($this->products->isEmpty()) {
            $this->error('Ejecutá primero: php artisan db:seed');

            return self::FAILURE;
        }

        $this->info("Lions: simulación iniciada (intervalo {$intervalo}s). Ctrl+C para detener.");

        for (; ;) {
            sleep($intervalo);

            try {
                $roll = mt_rand(1, 100);

                if ($roll <= 45) {
                    $this->nuevoPedido();
                } elseif ($roll <= 65) {
                    $this->confirmarPedido();
                } elseif ($roll <= 80) {
                    $this->reposicion();
                } else {
                    $this->nuevoCliente();
                }
            } catch (\Throwable $e) {
                $this->error('Error: '.$e->getMessage());
            }
        }
    }

    private function nuevoPedido(): void
    {
        $customer = Customer::query()->inRandomOrder()->first();

        /** @var Product $product */
        $product = $this->products->random();

        /** @var ProductVariant|null $variant */
        $variant = $product->variants->firstWhere('stock', '>', 0);

        if ($customer === null || $variant === null) {
            return;
        }

        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pendiente',
            'total' => 0,
            'ordered_at' => now(),
        ]);

        $qty = min(mt_rand(1, 3), $variant->stock);
        $total = $product->sale_price * $qty;

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => $product->sale_price,
        ]);

        $variant->decrement('stock', $qty);

        if ($variant->stock - $qty <= $product->low_stock_threshold) {
            broadcast(new StockLow($product->name, $variant->size, max(0, $variant->stock - $qty), now()->toIso8601String()))->toOthers();
        }

        $order->update(['total' => $total]);

        Activity::create([
            'type' => 'pedido',
            'title' => 'Nuevo pedido',
            'description' => sprintf('Pedido #%d · %d pares · %s (%s)', $order->id, $qty, $customer->name, $customer->city),
            'performed_at' => now(),
        ]);

        broadcast(new OrderCreated(
            (string) $order->id,
            $customer->name,
            (float) $total,
            $customer->city,
            $qty,
            now()->toIso8601String(),
        ))->toOthers();

        $this->line(sprintf('[%s] pedido #%d +$%s', now()->format('H:i:s'), $order->id, number_format($total, 0, ',', '.')));
    }

    private function confirmarPedido(): void
    {
        /** @var Order|null $order */
        $order = Order::query()->where('status', 'pendiente')->inRandomOrder()->first();

        if ($order === null) {
            return;
        }

        $order->update(['status' => 'completado']);

        Activity::create([
            'type' => 'venta',
            'title' => 'Venta confirmada',
            'description' => sprintf('Pedido #%d cobrado · $%s', $order->id, number_format($order->total, 0, ',', '.')),
            'performed_at' => now(),
        ]);

        broadcast(new OrderStatusChanged((string) $order->id, 'pendiente', 'completado', now()->toIso8601String()))->toOthers();

        $this->line(sprintf('[%s] venta #%d confirmada', now()->format('H:i:s'), $order->id));
    }

    private function reposicion(): void
    {
        /** @var Product $product */
        $product = $this->products->random();

        /** @var ProductVariant|null $variant */
        $variant = $product->variants->sortBy('stock')->first();

        if ($variant === null) {
            return;
        }

        $added = mt_rand(20, 120);
        $variant->increment('stock', $added);

        Activity::create([
            'type' => 'stock',
            'title' => 'Reposición de stock',
            'description' => sprintf('%s talle %d +%d → %d unidades', $product->name, $variant->size, $added, $variant->stock),
            'performed_at' => now(),
        ]);

        if ($variant->stock <= $product->low_stock_threshold) {
            broadcast(new StockLow($product->name, $variant->size, $variant->stock, now()->toIso8601String()))->toOthers();
        }

        $this->line(sprintf('[%s] stock %s talle %d +%d', now()->format('H:i:s'), $product->name, $variant->size, $added));
    }

    private function nuevoCliente(): void
    {
        [$city, $lat, $lng] = self::CITIES[mt_rand(0, count(self::CITIES) - 1)];

        $customer = Customer::create([
            'name' => fake()->firstName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'city' => $city,
            'region' => $city,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        Activity::create([
            'type' => 'cliente',
            'title' => 'Cliente nuevo',
            'description' => $customer->name.' comenzó a comprar desde '.$city,
            'performed_at' => now(),
        ]);

        $this->line(sprintf('[%s] cliente: %s (%s)', now()->format('H:i:s'), $customer->name, $city));
    }
}
