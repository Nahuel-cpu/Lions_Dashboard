<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shopify\DemoWipe;
use App\Services\Shopify\Importer;
use App\Services\Shopify\ShopifyConnector;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill/incremental de Shopify hacia el esquema local.
 *
 *   --full          limpia los datos demo ANTES de importar (reemplazo limpio)
 *   --desde=YYYY-MM-DD  sólo pedidos actualizados en Shopify después de esa fecha
 */
class ShopifyImport extends Command
{
    protected $signature = 'shopify:import {--full : resetea los datos demo antes} {--desde= : fecha ISO para sync incremental de pedidos}';

    protected $description = 'Sincroniza productos, clientes y pedidos desde Shopify hacia el dashboard';

    public function handle(): int
    {
        try {
            $connector = new ShopifyConnector;
            $importer = new Importer($connector);
            $shop = $connector->shopInfo();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('Probá primero: php artisan shopify:ping');

            return self::FAILURE;
        }

        $this->info('Conectado a tienda: '.($shop['shop']['name'] ?? '?'));
        $this->newLine();

        if ($this->option('full')) {
            if (! $this->confirm('Esto BORRA todos los datos demo (productos, clientes, pedidos) antes de importar. ¿Continuar?')) {
                $this->line('Cancelado.');

                return self::SUCCESS;
            }
            $counts = DemoWipe::everything();
            $this->info('Datos demo eliminados: '.(DemoWipe::humanize($counts) ?? 'nada que borrar.'));
        }

        $desde = $this->option('desde') ?: null;

        $pages = $importer->fullImport($desde, function (string $label, int $page, bool $done): void {
            $this->line(sprintf('%s · página %d (%s)', $label, $page, $done ? 'completo' : 'quedan más páginas'));
        })['pages'];

        $this->newLine();
        $this->table(['Entidad', 'Páginas procesadas'], [
            ['Productos', $pages['productos']],
            ['Clientes', $pages['clientes']],
            ['Pedidos', $pages['pedidos']],
        ]);
        $this->info('Resumen final: '.Product::count().' productos · '.ProductVariant::count().' variantes · '
            .Customer::count().' clientes · '.Order::count().' pedidos · '.OrderItem::count().' items · '
            .Category::count().' categorías.');

        $this->newLine();
        $this->line('Siguiente: configurar el webhook SHOPIFY_WEBHOOK_SECRET en .env y el topic orders/updated apuntando a https://<tu-dominio>/webhooks/shopify');

        return self::SUCCESS;
    }
}
