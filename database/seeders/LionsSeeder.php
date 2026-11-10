<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LionsSeeder extends Seeder
{
    /** Ciudad => [lat, lng, peso de ventas] */
    private const CITIES = [
        'Buenos Aires' => [-34.6037, -58.3816, 34],
        'Córdoba' => [-31.4201, -64.1888, 14],
        'Rosario' => [-32.9442, -60.6505, 9],
        'Mendoza' => [-32.8895, -68.8458, 7],
        'La Plata' => [-34.9215, -57.9545, 6],
        'Mar del Plata' => [-38.0055, -57.5426, 5],
        'Salta' => [-24.7829, -65.4237, 4],
        'Santa Fe' => [-31.6135, -60.7048, 4],
        'San Juan' => [-31.5375, -68.5264, 3],
        'Resistencia' => [-27.4511, -58.9867, 3],
        'Neuquén' => [-38.9516, -68.0591, 3],
        'Tandil' => [-37.3217, -59.1331, 2],
        'Bahía Blanca' => [-38.7183, -62.2663, 2],
        'Ushuaia' => [-54.8019, -68.3030, 1],
    ];

    /** Categoria => [nombres de modelos, rango de precio] */
    private const CATALOG = [
        'Zapatillas' => [
            ['Runner Pro', 74], ['Urban Court', 68], ['Air Flex', 81], ['Training Prime', 59], ['City Jogger', 63], ['Trail Boxer', 72],
        ],
        'Botas' => [
            ['Timber Roja', 139], ['Sierra Cordón', 152], ['Chelsea Cuero', 174], ['Biker Negra', 191], ['Awka All Terrain', 158],
        ],
        'Sandalias' => [
            ['Verano Cork', 31], ['Playa Leo', 27], ['Town Slide', 34],
        ],
        'Mocasines' => [
            ['Classic Piel', 127], ['Oficina Elite', 142], ['Driving Soft', 119],
        ],
        'Oxfords' => [
            ['Berkshire Miño', 149], ['Gala Noche', 168],
        ],
    ];

    private const SIZES = [36, 37, 38, 39, 40, 41, 42, 43, 44, 45];

    private const FIRST_NAMES = ['Lucía', 'Martín', 'Sofía', 'Diego', 'Camila', 'Juan', 'Valentina', 'Esteban', 'Julieta', 'Ramiro', 'Martina', 'Facundo', 'Agustina', 'Nicolás', 'Antonella', 'Tomás', 'Florencia', 'Ignacio', 'Micaela', 'Bruno'];

    private const LAST_NAMES = ['García', 'Fernández', 'González', 'Rodríguez', 'Moreno', 'Acuña', 'Sosa', 'Ledesma', 'Moya', 'Barrios', 'Torres', 'Rivera', 'Juárez', 'Miranda', 'Bustos', 'Córdoba', 'Salvatierra', 'Ibarra', 'Quintero', 'Valdez'];

    /**
     * @param  array<mixed>  $items
     */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    public function run(): void
    {
        mt_srand(20261107);

        DB::disableQueryLog();

        $categories = [
            'Zapatillas' => 'zapatillas', 'Botas' => 'botas', 'Sandalias' => 'sandalias',
            'Mocasines' => 'mocasines', 'Oxfords' => 'oxfords',
        ];
        $categoryIds = [];
        foreach ($categories as $name => $slug) {
            $categoryIds[$name] = Category::create(['name' => $name, 'slug' => $slug])->id;
        }

        // ---- Productos + variantes ----
        $variantData = [];
        $productWeights = [];
        foreach (self::CATALOG as $categoryName => $models) {
            foreach ($models as [$name, $thousands]) {
                $sku = strtoupper(Str::slug(Str::substr($name, 0, 19))).'-'.mt_rand(100, 999);
                $variantPrice = $thousands * 1000 + mt_rand(-4000, 8000);
                $cost = (int) ($variantPrice * (0.52 + mt_rand(-6, 8) / 100));

                $product = Product::create([
                    'sku' => $sku,
                    'name' => $name,
                    'category_id' => $categoryIds[$categoryName],
                    'description' => $categoryName.' Lions · '.$name,
                    'cost_price' => max(10, $cost),
                    'sale_price' => $variantPrice,
                    'low_stock_threshold' => 12,
                ]);

                foreach (self::SIZES as $size) {
                    $stock = mt_rand(0, 55);
                    // El ~9% de las variantes quedan con poco stock
                    if (mt_rand(1, 100) <= 9) {
                        $stock = mt_rand(0, 9);
                    }
                    $variantData[] = ['product_id' => $product->id, 'size' => $size, 'stock' => $stock];
                    $productWeights[] = $product->id;
                }
            }
        }
        foreach (array_chunk($variantData, 500) as $chunk) {
            DB::table('product_variants')->insert($chunk);
        }
        $productIds = Product::query()->pluck('id')->all();
        $products = Product::query()->with('variants')->get()->keyBy('id');

        // ---- Clientes ----
        $customerRows = [];
        $emails = [];
        $cityPool = [];
        foreach (self::CITIES as $city => [$lat, $lng, $weight]) {
            for ($i = 0, $n = max(1, $weight); $i < $n * 10; $i++) {
                $cityPool[] = [$city, $lat, $lng];
            }
        }
        // self::CITIES es un array literal no vacío: cityPool nunca queda vacío.
        $count = 640;
        for ($i = 0; $i < $count; $i++) {
            [$city, $lat, $lng] = $this->pick($cityPool);
            $name = $this->pick(self::FIRST_NAMES).' '.$this->pick(self::LAST_NAMES);
            $email = Str::slug($name, '.').'@'.Str::lower($this->pick(['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.ar']));
            if (isset($emails[$email])) {
                $email = Str::slug($name, '.').$i.'@gmail.com';
            }
            $emails[$email] = true;
            $customerRows[] = [
                'name' => $name,
                'email' => $email,
                'city' => $city,
                'region' => $city,
                'lat' => $lat,
                'lng' => $lng,
                'created_at' => now()->subDays(mt_rand(3, 140))->subHours(mt_rand(0, 23)),
                'updated_at' => now()->subDays(mt_rand(3, 140)),
            ];
        }
        foreach (array_chunk($customerRows, 500) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        $customerIds = Customer::query()->pluck('id')->all();
        // Buenos Aires debe concentrar más pedidos: peso
        $weightedCustomerIds = [];
        $customers = Customer::query()->get(['id', 'city']);
        foreach ($customers as $customer) {
            $weight = self::CITIES[$customer->city][2] ?? 3;
            for ($i = 0, $n = max(1, $weight); $i < $n; $i++) {
                $weightedCustomerIds[] = $customer->id;
            }
        }

        // ---- Pedidos (últimos 150 días) ----
        $orderRows = [];
        $itemRows = [];
        $now = now();
        foreach (range(149, 0) as $daysAgo) {
            $day = $now->copy()->subDays($daysAgo);
            $orderCount = mt_rand(6, 24);
            for ($i = 0; $i < $orderCount; $i++) {
                $orderedAt = $day->copy()->startOfDay()->addMinutes(mt_rand(0, 1439));
                // Los pedidos recientes tienen más chance de estar pendientes
                $statusMatch = $daysAgo < 4 ? mt_rand(1, 100) : mt_rand(1, 100) + 25;
                $status = $statusMatch <= 12 ? 'pendiente' : ($statusMatch <= 19 ? 'cancelado' : 'completado');
                if ($daysAgo < 3 && mt_rand(1, 100) <= 45) {
                    $status = 'pendiente';
                }

                $orderRows[] = [
                    'customer_id' => $this->pick($weightedCustomerIds),
                    'status' => $status,
                    'total' => 0,
                    'ordered_at' => $orderedAt->format('Y-m-d H:i:s'),
                    'created_at' => $orderedAt->format('Y-m-d H:i:s'),
                    'updated_at' => $orderedAt->format('Y-m-d H:i:s'),
                ];
            }
        }
        foreach (array_chunk($orderRows, 500) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $orders = Order::query()->where('status', '!=', 'cancelado')->get(['id', 'ordered_at']);
        foreach ($orders as $order) {
            $itemCount = mt_rand(1, 4);
            $total = 0;
            for ($i = 0; $i < $itemCount; $i++) {
                $productId = $this->pick($productIds);
                /** @var Product $product */
                $product = $products[$productId];
                $variant = $product->variants->random();
                $quantity = mt_rand(1, 3);
                $total += $product->sale_price * $quantity;
                $itemRows[] = [
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                    'created_at' => $order->ordered_at,
                    'updated_at' => $order->ordered_at,
                ];
            }
            DB::table('orders')->where('id', $order->id)->update(['total' => $total]);
        }
        foreach (array_chunk($itemRows, 1000) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }

        // ---- Actividad reciente ----
        $cityNames = array_keys(self::CITIES);
        $activityRows = [];
        $skuPlaceholder = 'RNR-401';
        for ($i = 17; $i >= 0; $i--) {
            $case = mt_rand(1, 5);
            $code = strtoupper(Str::random(2)).mt_rand(1000, 9999);
            $person = $this->pick(self::FIRST_NAMES).' '.$this->pick(self::LAST_NAMES);
            $qty = mt_rand(1, 5);
            switch ($case) {
                case 1:
                    $type = 'pedido';
                    $title = 'Nuevo pedido';
                    $description = "Pedido #{$code} · {$qty} pares · {$person}";
                    break;
                case 2:
                    $type = 'envio';
                    $title = 'Envío despachado';
                    $description = "Pedido #{$code} salió del depósito";
                    break;
                case 3:
                    $type = 'stock';
                    $title = 'Stock actualizado';
                    $description = "Reposición: Runner Pro talle {$qty} (+{$code})";
                    break;
                case 4:
                    $type = 'cliente';
                    $title = 'Cliente nuevo';
                    $description = "{$person} se registró desde {$this->pick($cityNames)}";
                    break;
                default:
                    $type = 'venta';
                    $title = 'Venta confirmada';
                    $description = "Pedido #{$code} cobrado (${skuPlaceholder})";
            }
            $activityRows[] = [
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'performed_at' => now()->subMinutes($i * mt_rand(7, 40))->format('Y-m-d H:i:s'),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];
        }
        DB::table('activities')->insert($activityRows);

        $this->command?->info('Lions: pedidos '.Order::count().', clientes '.Customer::count().', productos '.Product::count().', variantes '.ProductVariant::count());
    }
}
