<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Métricas agregadas con SQL nativo para el panel Lions.
 */
class AnalyticsService
{
    public const PERIODS = [7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '12 meses'];

    /**
     * KPIs del panel dentro del rango indicado.
     *
     * @return array{revenue: float, profit: float, margin: float, orders: int,
     *     pending: int, completed: int, cancelled: int, avg_ticket: float,
     *     new_customers: int, returning_customers: int, revenue_delta: float}
     */
    public function kpis(CarbonImmutable $from, ?int $categoryId = null): array
    {
        $applyCategory = fn ($q) => $this->applyCategory($q, $categoryId);

        $sales = DB::table('orders as o')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->tap($applyCategory)
            ->selectRaw('COALESCE(SUM(o.total),0) revenue, COUNT(DISTINCT o.id) orders')
            ->first();

        $profitRow = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->tap($applyCategory)
            ->selectRaw('COALESCE(SUM(oi.quantity * (oi.unit_price - p.cost_price)),0) profit')
            ->first();

        $byStatus = DB::table('orders as o')
            ->where('o.ordered_at', '>=', $from)
            ->tap($applyCategory)
            ->selectRaw("SUM(o.status = 'pendiente') pending, SUM(o.status = 'completado') completed, SUM(o.status = 'cancelado') cancelled")
            ->first();

        $newCustomers = DB::table('customers')->where('created_at', '>=', $from)->count('id');

        $returning = DB::table('customers as c')
            ->whereRaw("(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status = 'completado') >= 2")
            ->count('id');

        $revenue = (float) $sales->revenue;
        $orders = max(1, (int) $sales->orders);

        $daysSpan = $from->diffInDays(now());
        $mid = $from->copy()->addDays((int) ($daysSpan / 2))->startOfDay();

        $firstHalfQuery = DB::table('orders as o')
            ->where('o.status', 'completado')->whereBetween('o.ordered_at', [$from, $mid]);
        $secondHalfQuery = DB::table('orders as o')
            ->where('o.status', 'completado')->where('o.ordered_at', '>', $mid);

        $this->applyCategory($firstHalfQuery, $categoryId);
        $this->applyCategory($secondHalfQuery, $categoryId);

        $firstHalf = (float) $firstHalfQuery->sum('o.total');
        $secondHalf = (float) $secondHalfQuery->sum('o.total');

        return [
            'revenue' => $revenue,
            'profit' => (float) $profitRow->profit,
            'margin' => $revenue > 0 ? round((float) $profitRow->profit / $revenue * 100, 1) : 0.0,
            'orders' => (int) $sales->orders,
            'pending' => (int) $byStatus->pending,
            'completed' => (int) $byStatus->completed,
            'cancelled' => (int) $byStatus->cancelled,
            'avg_ticket' => round($revenue / $orders, 0),
            'new_customers' => (int) $newCustomers,
            'returning_customers' => (int) $returning,
            'revenue_delta' => $firstHalf > 0 ? round(($secondHalf - $firstHalf) / $firstHalf * 100, 1) : 0.0,
        ];
    }

    /**
     * Serie temporal por día / semana / mes, con buckets vacíos rellenados.
     *
     * @return list<array{bucket: string, revenue: float, orders: int}>
     */
    public function series(CarbonImmutable $from, string $granularity, ?int $categoryId = null): array
    {
        $fmt = match ($granularity) {
            'week' => "strftime('%Y-%m-%d', date(o.ordered_at, '-6 days', 'weekday 1'))",
            'month' => "strftime('%Y-%m', o.ordered_at)",
            default => "strftime('%Y-%m-%d', o.ordered_at)",
        };

        $rows = DB::table('orders as o')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->tap(fn ($q) => $this->applyCategory($q, $categoryId))
            ->selectRaw("$fmt bucket, SUM(o.total) revenue, COUNT(*) orders")
            ->groupBy(DB::raw($fmt))
            ->orderBy('bucket')
            ->get();

        $byBucket = [];
        foreach ($rows as $row) {
            $byBucket[(string) $row->bucket] = $row;
        }

        $result = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor <= now()) {
            // El bucket actual es el que contiene al cursor; el siguiente cursor
            // salta al inicio del siguiente bucket para no saltarse el
            // mes/semana en curso cuando su inicio cae después de hoy.
            [$bucket, $nextCursor] = match ($granularity) {
                'week' => [
                    $cursor->copy()->startOfWeek()->toDateString(),
                    $cursor->copy()->startOfWeek()->addDays(7),
                ],
                'month' => [
                    $cursor->copy()->startOfMonth()->format('Y-m'),
                    $cursor->copy()->startOfMonth()->addMonth(),
                ],
                default => [$cursor->toDateString(), $cursor->copy()->addDay()],
            };
            if (! isset($result[$bucket])) {
                $row = $byBucket[$bucket] ?? null;
                $result[$bucket] = [
                    'bucket' => $bucket,
                    'revenue' => $row !== null ? (float) $row->revenue : 0.0,
                    'orders' => $row !== null ? (int) $row->orders : 0,
                ];
            }
            $cursor = $nextCursor;
        }

        return array_values($result);
    }

    /** @return list<array{product: string, category: string, units: int, revenue: float}> */
    public function topProducts(CarbonImmutable $from, ?int $categoryId = null, int $limit = 6): array
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->when($categoryId, fn ($q) => $q->where('p.category_id', $categoryId))
            ->selectRaw('p.name product, c.name category, SUM(oi.quantity) units, SUM(oi.quantity * oi.unit_price) revenue')
            ->groupBy('p.name', 'c.name')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'product' => (string) $r->product,
                'category' => (string) $r->category,
                'units' => (int) $r->units,
                'revenue' => (float) $r->revenue,
            ];
        }

        return $result;
    }

    /** @return list<array{city: string, lat: float, lng: float, revenue: float, orders: int}> */
    public function salesMap(CarbonImmutable $from, ?int $categoryId = null): array
    {
        $rows = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->tap(fn ($q) => $this->applyCategory($q, $categoryId))
            ->selectRaw('c.city, AVG(c.lat) lat, AVG(c.lng) lng, SUM(o.total) revenue, COUNT(*) orders')
            ->groupBy('c.city')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'city' => (string) $r->city,
                'lat' => (float) $r->lat,
                'lng' => (float) $r->lng,
                'revenue' => (float) $r->revenue,
                'orders' => (int) $r->orders,
            ];
        }

        return $result;
    }

    /** @return list<array{id: int, product: string, sku: string, size: int, stock: int, threshold: int}> */
    public function lowStock(int $limit = 12): array
    {
        $rows = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereColumn('pv.stock', '<=', 'p.low_stock_threshold')
            ->selectRaw('pv.id, p.name product, p.sku, pv.size, pv.stock, p.low_stock_threshold threshold')
            ->orderBy('pv.stock')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'id' => (int) $r->id,
                'product' => (string) $r->product,
                'sku' => (string) $r->sku,
                'size' => (int) $r->size,
                'stock' => (int) $r->stock,
                'threshold' => (int) $r->threshold,
            ];
        }

        return $result;
    }

    /** @return list<array{id: int, type: string, title: string, description: string, at: string}> */
    public function recentActivity(int $limit = 20): array
    {
        $activities = Activity::query()->orderByDesc('performed_at')->limit($limit)
            ->get(['id', 'type', 'title', 'description', 'performed_at']);

        $result = [];
        foreach ($activities as $activity) {
            $result[] = [
                'id' => (int) $activity->id,
                'type' => (string) $activity->type,
                'title' => (string) $activity->title,
                'description' => (string) $activity->description,
                'at' => $activity->performed_at->toIso8601String(),
            ];
        }

        return $result;
    }

    /**
     * Participación de cada categoría en el ingreso del período.
     *
     * @return list<array{category: string, revenue: float, units: int}>
     */
    public function categoryShare(CarbonImmutable $from, ?int $categoryId): array
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->when($categoryId, fn ($q) => $q->where('p.category_id', $categoryId))
            ->selectRaw('c.name category, SUM(oi.quantity * oi.unit_price) revenue, SUM(oi.quantity) units')
            ->groupBy('c.name')->orderByDesc('revenue')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'category' => (string) $r->category,
                'revenue' => (float) $r->revenue,
                'units' => (int) $r->units,
            ];
        }

        return $result;
    }

    /** @return list<array{city: string, orders: int, revenue: float}> */
    public function topCities(CarbonImmutable $from, ?int $categoryId): array
    {
        $rows = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.status', 'completado')->where('o.ordered_at', '>=', $from)
            ->tap(fn ($q) => $this->applyCategory($q, $categoryId))
            ->selectRaw('c.city, COUNT(*) orders, SUM(o.total) revenue')
            ->groupBy('c.city')->orderByDesc('revenue')->limit(6)
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'city' => (string) $r->city,
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
            ];
        }

        return $result;
    }

    /**
     * Filtra pedidos por categoría vía EXISTS (no duplica filas del pedido).
     */
    private function applyCategory(QueryBuilder $query, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $query->whereExists(function ($sub) use ($categoryId) {
            $sub->selectRaw('1')
                ->from('order_items as oi')
                ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->whereColumn('oi.order_id', 'o.id')
                ->where('p.category_id', $categoryId);
        });
    }
}
