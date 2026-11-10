<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Shopify\Credentials;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Re-ejecutable: limpia el contenido demo previo y lo vuelve a sembrar.
 *
 * Si Shopify está conectado Y hay datos reales importados, no toca nada:
 * para demo limpio usar `php artisan migrate:fresh --seed`, que además
 * reinicia la conexión Shopify guardada.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $restoReal = Order::query()->whereNotNull('shopify_id')->exists()
            || Product::query()->whereNotNull('shopify_id')->exists();

        if (Credentials::configured() && $restoReal) {
            $this->command->error('Shopify está conectado con datos reales importados: db:seed no pisa ese contenido.');
            $this->command->line('Para estado demo limpio: php artisan migrate:fresh --seed (borra TODO, incluida la conexión Shopify guardada).');

            return;
        }

        // Limpieza de demo previo (orden de FKs respetado).
        Activity::query()->delete();
        OrderItem::query()->delete();
        Order::query()->delete();
        ProductVariant::query()->delete();
        Product::query()->delete();
        Customer::query()->delete();
        Category::query()->delete();

        // Usuario admin idempotente.
        User::updateOrCreate(
            ['email' => 'admin@lions.com'],
            [
                'name' => 'Admin Lions',
                'password' => Hash::make('lions1234'),
                'email_verified_at' => now(),
            ],
        );

        $this->call(LionsSeeder::class);
    }
}
