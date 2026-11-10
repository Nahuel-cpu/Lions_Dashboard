<?php

namespace App\Console\Commands;

use App\Services\Shopify\ShopifyConnector;
use Illuminate\Console\Command;
use RuntimeException;

/** Valida credenciales de Shopify y muestra info básica de la tienda. */
class ShopifyPing extends Command
{
    protected $signature = 'shopify:ping';

    protected $description = 'Verifica la conexión con la Admin API de Shopify (gráficaQL)';

    public function handle(): int
    {
        try {
            $data = (new ShopifyConnector)->shopInfo();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('Cómo completarlo: en el admin de la tienda → Configuración → Apps → Develop apps → Create app (scopes read_orders, read_customers, read_products, read_inventory). El token shpat_ se ve una sola vez al instalar.');

            return self::FAILURE;
        }

        $this->info('Conexión OK con Shopify:');
        $this->line('  Tienda: '.($data['shop']['name'] ?? '?'));
        $this->line('  Dominio: '.($data['shop']['myshopifyDomain'] ?? '?'));
        $this->line('  API URL: '.($data['shop']['primaryDomain']['url'] ?? '?'));

        return self::SUCCESS;
    }
}
