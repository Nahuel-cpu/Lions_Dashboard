<?php

namespace App\Services\Shopify;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Importa catálogo, clientes y pedidos de Shopify GraphQL hacia el esquema
 * local del dashboard (upserts idempotentes por shopify_id).
 */
final class Importer
{
    public function __construct(private readonly ShopifyConnector $api) {}

    // ------------------------------------------------------------- catálogo

    /**
     * @param  string|null  $after  Cursor de paginación de Shopify.
     * @return array<string, mixed> {processed, cursor, done}
     */
    public function syncProducts(?string $after = null): array
    {
        $first = 50;

        $data = $this->api->requestWithRetry(<<<'GQL'
            query($first: Int!, $after: String) {
              products(first: $first, after: $after) {
                edges {
                  cursor
                  node {
                    id
                    title
                    productType
                    descriptionHtml
                    variants(first: 100) {
                      edges {
                        node {
                          id
                          sku
                          title
                          price
                          inventoryQuantity
                        }
                      }
                    }
                  }
                }
                pageInfo { hasNextPage endCursor }
              }
            }
            GQL, ['first' => $first, 'after' => $after]);

        $edges = $data['products']['edges'] ?? [];
        foreach ((array) $edges as $edge) {
            $this->upsertProduct((array) ($edge['node'] ?? []));
        }

        $pageInfo = $data['products']['pageInfo'] ?? [];
        $cursor = ($pageInfo['endCursor'] ?? null) ?: $after;
        $done = ! (($pageInfo['hasNextPage'] ?? false) && count($edges) > 0);

        return [
            'processed' => count($edges),
            'cursor' => $cursor,
            'done' => $done,
        ];
    }

    // ------------------------------------------------------------ clientes

    /**
     * @return array<string, mixed>
     */
    public function syncCustomers(?string $after = null): array
    {
        $data = $this->api->requestWithRetry(<<<'GQL'
            query($first: Int!, $after: String) {
              customers(first: $first, after: $after) {
                edges {
                  cursor
                  node {
                    id
                    firstName
                    lastName
                    email
                    defaultAddress { city province }
                  }
                }
                pageInfo { hasNextPage endCursor }
              }
            }
            GQL, ['first' => (int) config('shopify.pagination', 250), 'after' => $after]);

        $edges = $data['customers']['edges'] ?? [];
        foreach ((array) $edges as $edge) {
            $this->upsertCustomerNode((array) ($edge['node'] ?? []));
        }

        $pageInfo = $data['customers']['pageInfo'] ?? [];
        $cursor = ($pageInfo['endCursor'] ?? null) ?: $after;
        $done = ! (($pageInfo['hasNextPage'] ?? false) && count($edges) > 0);

        return ['processed' => count($edges), 'cursor' => $cursor, 'done' => $done];
    }

    // --------------------------------------------------------------- pedidos

    /**
     * @return array<string, mixed>
     */
    public function syncOrders(?string $after = null, ?string $since = null): array
    {
        $filters = ['first' => (int) config('shopify.pagination', 250), 'after' => $after];
        if ($since !== null && $since !== '') {
            // Sintaxis de búsqueda de Shopify, SIN %-encoding.
            $filters['query'] = 'updated_at:>='.$since;
        }

        $data = $this->api->requestWithRetry(<<<'GQL'
            query($first: Int!, $after: String, $query: String) {
              orders(first: $first, after: $after, sortKey: CREATED_AT, query: $query) {
                edges {
                  cursor
                  node {
                    id
                    createdAt
                    cancelledAt
                    displayFinancialStatus
                    displayFulfillmentStatus
                    totalPriceSet { shopMoney { amount } }
                    customer { id firstName lastName email defaultAddress { city province } }
                    lineItems(first: 100) {
                      edges {
                        node {
                          quantity
                          variant { id }
                          originalUnitPriceSet { shopMoney { amount } }
                        }
                      }
                    }
                  }
                }
                pageInfo { hasNextPage endCursor }
              }
            }
            GQL, $filters);

        $edges = $data['orders']['edges'] ?? [];
        foreach ((array) $edges as $edge) {
            $this->upsertOrderNode((array) ($edge['node'] ?? []), logActivity: false);
        }

        $pageInfo = $data['orders']['pageInfo'] ?? [];
        $cursor = ($pageInfo['endCursor'] ?? null) ?: $after;
        $done = ! (($pageInfo['hasNextPage'] ?? false) && count($edges) > 0);

        return ['processed' => count($edges), 'cursor' => $cursor, 'done' => $done];
    }

    // ---------------------------------------------------- sincronización completa

    /**
     * Import completo: recorre todas las páginas de productos, clientes y
     * pedidos. Es el mismo trabajo que hace `shopify:import`, pero callable
     * desde la UI (Settings → Shopify). $onPage(label, page, done) reporta
     * avance.
     *
     * @param  callable(string, int, bool): void|null  $onPage
     * @return array{pages: array{productos: int, clientes: int, pedidos: int}}
     */
    public function fullImport(?string $since = null, ?callable $onPage = null): array
    {
        return [
            'pages' => [
                'productos' => $this->paginateAll('Productos', fn ($after) => $this->syncProducts($after), $onPage),
                'clientes' => $this->paginateAll('Clientes', fn ($after) => $this->syncCustomers($after), $onPage),
                'pedidos' => $this->paginateAll('Pedidos', fn ($after) => $this->syncOrders($after, $since), $onPage),
            ],
        ];
    }

    /** Recorre todas las páginas de una entidad. Corte de seguridad: 500 págs. */
    private function paginateAll(string $label, Closure $call, ?callable $onPage = null): int
    {
        $pages = 0;
        $after = null;

        do {
            $result = $call($after);
            $pages++;
            $after = $result['cursor'];

            if ($onPage !== null) {
                $onPage($label, $pages, (bool) $result['done']);
            }

            if ($pages > 500) {
                break;
            }
        } while (! $result['done']);

        return $pages;
    }

    // ---------------------------------------------------- upserts reutilizables

    // ---------------------------------------------------------- helpers privados

    /** @param array<mixed> $node */
    private function upsertProduct(array $node): void
    {
        $gid = (string) ($node['id'] ?? '');
        if ($gid === '') {
            return;
        }

        $variants = array_map(fn ($e) => (array) ($e['node'] ?? []), (array) ($node['variants']['edges'] ?? []));
        if ($variants === []) {
            return;
        }

        /** El precio de venta local: el mayor entre las variantes.
         * En Admin API >= 2025-01 `price` ya es escalar (string decimal),
         * antes era un objeto { amount }.
         */
        $maxPrice = 0.0;
        foreach ($variants as $v) {
            $price = is_array($v['price'] ?? null) ? ($v['price']['amount'] ?? 0) : ($v['price'] ?? 0);
            $maxPrice = max($maxPrice, (float) $price);
        }

        $category = $this->ensureCategory((string) ($node['productType'] ?? ''));
        $sku = $this->uniqueSku((string) ($variants[0]['sku'] ?? ''), $gid);

        $product = Product::updateOrCreate(
            ['shopify_id' => $gid],
            [
                'sku' => $sku,
                'name' => (string) ($node['title'] ?? 'Sin título'),
                'category_id' => $category->id,
                'description' => Str::limit(trim(strip_tags((string) ($node['descriptionHtml'] ?? ''))), 500),
                'cost_price' => (int) round($maxPrice * (float) config('shopify.price_scale', 1) * 0.35),
                'sale_price' => (int) round($maxPrice * (float) config('shopify.price_scale', 1)),
                'low_stock_threshold' => 10,
            ],
        );

        /** @var Collection<int, bool> $usedSizes talle => true (todos los ocupados) */
        $usedSizes = ProductVariant::query()
            ->where('product_id', $product->id)
            ->pluck('size')
            ->mapWithKeys(fn ($s) => [(int) $s => true]);

        $usedSizes = ProductVariant::query()
            ->where('product_id', $product->id)
            ->pluck('size')
            ->mapWithKeys(fn ($s) => [(int) $s => true]);

        $fallback = $usedSizes->keys()->max() ? ((int) $usedSizes->keys()->max()) + 1 : 100;

        foreach ($variants as $v) {
            [$size, $usedSizes, $fallback] = $this->resolveSize((string) ($v['title'] ?? ''), $usedSizes, $fallback);

            ProductVariant::updateOrCreate(
                ['shopify_id' => (string) $v['id']],
                [
                    'product_id' => $product->id,
                    'size' => $size,
                    'stock' => max(0, (int) ($v['inventoryQuantity'] ?? 0)),
                ],
            );

            $usedSizes->put($size, true);
        }
    }

    /**
     * Talle del títido de la variante ("EU 42", "8US"...): el primer número
     * en rango realista de zapatos vale como talle. Si no se puede extraer o
     * el talle ya está ocupado en ese producto, se asigna un talle técnico
     * (>=100) para no violar unique(product_id, size).
     *
     * @param  Collection<int, bool>  $used
     * @return array{0: int, 1: Collection<int, bool>, 2: int}
     */
    private function resolveSize(string $title, Collection $used, int $fallback): array
    {
        $size = null;
        if (preg_match('/(\d+(?:\.\d+)?)/', trim($title), $m) === 1) {
            $candidate = (int) floor((float) $m[1]);
            if ($candidate >= 15 && $candidate <= 60) {
                $size = $candidate;
            }
        }

        if ($size === null || $used->has($size)) {
            $size = max($fallback, (int) $size);
            $fallback = $size + 1;
        }

        return [$size, $used, $fallback];
    }

    private function uniqueSku(string $sku, string $gid): string
    {
        $candidate = $sku !== '' ? $sku : 'SHP-'.$this->numericId($gid);
        if (Product::query()->where('sku', $candidate)->where('shopify_id', '!=', $gid)->exists()) {
            $candidate .= '-'.$this->numericId($gid);
        }

        return $candidate;
    }

    private function ensureCategory(string $productType): Category
    {
        $name = trim($productType) !== '' ? $productType : 'Sin categoría';
        $slug = Str::slug($name) ?: 'sin-categoria';

        return Category::firstOrCreate(['slug' => $slug], ['name' => $name]);
    }

    /** @param array<mixed> $node */
    private function ensureCustomer(array $node): Customer
    {
        $gid = (string) ($node['id'] ?? '');

        $email = trim((string) ($node['email'] ?? ''));
        if ($email === '') {
            $email = 'sin-email+'.$this->numericId($gid).'@shopify.invalid';
        }

        return Customer::updateOrCreate(
            ['shopify_id' => $gid],
            [
                'name' => $this->customerName($node),
                'email' => $email,
                'city' => (string) ($node['defaultAddress']['city'] ?? '(sin ciudad)'),
                'region' => (string) ($node['defaultAddress']['province'] ?? '(sin provincia)'),
                'lat' => 0.0,
                'lng' => 0.0,
            ],
        );
    }

    /** Baja desde pedido sin cliente registrado: devolver un sustituto estable. */
    private function anonymousCustomerId(): int
    {
        $existing = Customer::query()->where('shopify_id', 'SHP-ANONIMO')->first();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        $c = Customer::create([
            'name' => 'Cliente sin registro',
            'email' => 'anonimo@shopify.invalid',
            'city' => '(sin ciudad)',
            'region' => '(sin provincia)',
            'lat' => 0.0,
            'lng' => 0.0,
            'shopify_id' => 'SHP-ANONIMO',
        ]);

        return (int) $c->id;
    }

    /** @param array<mixed> $node */
    private function upsertCustomerNode(array $node): void
    {
        $this->ensureCustomer($node);
    }

    /**
     * Entry única desde el webhook: trae el estado completo del pedido
     * a Shopify, lo sincroniza en base local (idempotente) y devuelve
     * el Order guardado junto con su estado previo (para broadcast).
     *
     * @return array{order: Order|null, previousStatus: string|null}
     */
    public function upsertOrderFromShopify(string $gid): array
    {
        $previous = Order::query()->where('shopify_id', $gid)->first();
        $previousStatus = $previous?->status;

        $data = $this->api->safeRequest(
            <<<'GQL'
                query($gid: ID!) {
                  order(id: $gid) {
                    id
                    createdAt
                    cancelledAt
                    displayFinancialStatus
                    displayFulfillmentStatus
                    totalPriceSet { shopMoney { amount } }
                    customer { id firstName lastName email defaultAddress { city province } }
                    lineItems(first: 100) {
                      edges {
                        node {
                          quantity
                          variant { id }
                          originalUnitPriceSet { shopMoney { amount } }
                        }
                      }
                    }
                  }
                }
                GQL,
            ['gid' => $gid],
        );

        $node = $data['order'] ?? null;
        if (! is_array($node)) {
            return ['order' => null, 'previousStatus' => $previousStatus];
        }

        $this->upsertOrderNode($node, logActivity: true);

        return ['order' => Order::query()->where('shopify_id', $gid)->first(), 'previousStatus' => $previousStatus];
    }

    /** @param array<mixed> $node */
    private function upsertOrderNode(array $node, bool $logActivity = true): void
    {
        $gid = (string) ($node['id'] ?? '');
        if ($gid === '') {
            return;
        }

        $customerData = (array) ($node['customer'] ?? []);
        $customerId = $customerData !== [] && ! empty($customerData['id'])
            ? $this->ensureCustomer($customerData)->id
            : $this->anonymousCustomerId();

        $status = OrderStatusMap::toLocal(
            // En >= 2025-01 los campos display* reemplazan a los "raw".
            $node['displayFinancialStatus'] ?? $node['financialStatus'] ?? null,
            $node['displayFulfillmentStatus'] ?? $node['fulfillmentStatus'] ?? null,
            $node['cancelledAt'] ?? null,
        );
        $total = $this->toLocalMoney((string) (($node['totalPriceSet']['shopMoney']['amount'] ?? 0)));

        $order = Order::updateOrCreate(
            ['shopify_id' => $gid],
            [
                'customer_id' => $customerId,
                'status' => $status,
                'total' => $total,
                'ordered_at' => $this->date($node['createdAt'] ?? null),
            ],
        );

        // Recrear completa la lista de items para que el upsert sea idempotente
        // incluso cuando Shopify agrega o quita line items.
        OrderItem::query()->where('order_id', $order->id)->delete();

        $nodes = array_map(fn ($e) => (array) ($e['node'] ?? []), (array) ($node['lineItems']['edges'] ?? []));
        foreach ($nodes as $line) {
            $variant = ProductVariant::query()
                ->where('shopify_id', (string) ($line['variant']['id'] ?? ''))
                ->first();
            if ($variant === null) {
                continue;
            }

            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $unit = (int) round((float) (($line['originalUnitPriceSet']['shopMoney']['amount'] ?? 0))
                * (float) config('shopify.price_scale', 1));

            OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price' => $unit,
            ]);
        }

        // Actividad sólo para eventos en vivo (webhook); el backfill masivo
        // no inunda el feed con miles de entradas.
        if ($logActivity) {
            Activity::create([
                'type' => 'pedido',
                'title' => 'Actualizado desde Shopify',
                'description' => sprintf('Pedido %s · estado %s', $gid, $status),
                'performed_at' => now(),
            ]);
        }
    }

    /** @param array<mixed> $node */
    private function customerName(array $node): string
    {
        $name = trim((string) ($node['firstName'] ?? '').' '.(string) ($node['lastName'] ?? ''));

        return $name !== '' ? $name : 'Cliente sin nombre';
    }

    private function toLocalMoney(string $amount): int
    {
        $value = (float) str_replace(',', '', $amount);

        return max(0, (int) round($value * (float) config('shopify.price_scale', 1)));
    }

    private function date(?string $iso): string
    {
        return $iso !== null && $iso !== '' ? $iso : now()->toIso8601String();
    }

    private function numericId(string $gid): string
    {
        if (preg_match('#/(\d+)$#', $gid, $m) === 1) {
            return $m[1];
        }

        return substr(md5($gid), 0, 8);
    }
}
