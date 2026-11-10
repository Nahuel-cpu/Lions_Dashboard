<?php

namespace App\Http\Controllers;

use App\Events\StockLow;
use App\Models\Activity;
use App\Models\Category;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'categoria' => 'sometimes|integer|exists:categories,id',
            'solo_low' => 'sometimes|in:0,1',
            'q' => 'sometimes|string|max:120',
            'page' => 'sometimes|integer|min:1',
            'orden' => 'sometimes|string|in:stock_asc,stock_desc,nombre',
        ]);

        $orden = $validated['orden'] ?? 'stock_asc';

        $variants = ProductVariant::query()
            ->join('products as p', 'p.id', '=', 'product_variants.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->when($validated['categoria'] ?? null, fn ($q, $id) => $q->where('p.category_id', $id))
            ->when(($validated['solo_low'] ?? '0') === '1', fn ($q) => $q->whereColumn('product_variants.stock', '<=', 'p.low_stock_threshold'))
            ->when($validated['q'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('p.name', 'like', "%$search%")
                ->orWhere('p.sku', 'like', "$search%")))
            ->select('product_variants.id as id', 'product_variants.size', 'product_variants.stock', 'p.id as product_id', 'p.name', 'p.sku', 'p.sale_price', 'p.low_stock_threshold', 'c.name as category')
            ->orderByRaw(match ($orden) {
                'stock_desc' => 'product_variants.stock desc',
                'nombre' => 'p.name asc',
                default => 'product_variants.stock asc',
            })
            ->orderBy('p.name')
            ->paginate(20)
            ->withQueryString();

        $alerts = ProductVariant::query()
            ->join('products as p', 'p.id', '=', 'product_variants.product_id')
            ->whereColumn('product_variants.stock', '<=', 'p.low_stock_threshold')
            ->select('product_variants.id', 'p.name as product', 'p.sku', 'product_variants.size', 'product_variants.stock', 'p.low_stock_threshold as threshold')
            ->orderBy('product_variants.stock')->limit(8)
            ->get();

        $totalStock = (int) ProductVariant::sum('stock');
        $valorInventario = (float) ProductVariant::query()
            ->join('products as p', 'p.id', '=', 'product_variants.product_id')
            ->selectRaw('SUM(product_variants.stock * p.cost_price) v')
            ->value('v');
        $skuBajo = (int) ProductVariant::query()
            ->join('products as p', 'p.id', '=', 'product_variants.product_id')
            ->whereColumn('product_variants.stock', '<=', 'p.low_stock_threshold')
            ->count();

        return Inertia::render('inventory/index', [
            'variants' => $variants,
            'alertas' => $alerts,
            'resumen' => ['total_stock' => $totalStock, 'valor' => $valorInventario, 'sku_bajo' => $skuBajo],
            'categorias' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'filters' => [
                'categoria' => $validated['categoria'] ?? null,
                'solo_low' => ($validated['solo_low'] ?? '0') === '1',
                'q' => $validated['q'] ?? null,
                'orden' => $orden,
            ],
        ]);
    }

    public function restock(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'cantidad' => 'required|integer|min:1|max:500',
        ]);

        $product = $variant->product;
        $variant->increment('stock', (int) $data['cantidad']);
        $variant->refresh();

        $at = now()->toIso8601String();
        Activity::create([
            'type' => 'stock',
            'title' => 'Reposición de stock',
            'description' => sprintf('%s talle %d +%d unidades → %d', $product->name, $variant->size, $data['cantidad'], $variant->stock),
            'performed_at' => now(),
        ]);

        if ($variant->stock <= $product->low_stock_threshold) {
            broadcast(new StockLow($product->name, $variant->size, $variant->stock, $at))->toOthers();

            return back()->with('warning', sprintf('Reposición cargada; %s talle %d sigue en stock bajo (%d).', $product->name, $variant->size, $variant->stock));
        }

        return back()->with('success', sprintf('+%d unidades de %s talle %d.', $data['cantidad'], $product->name, $variant->size));
    }
}
