<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'categoria' => 'sometimes|integer|exists:categories,id',
            'orden' => 'sometimes|string|in:ventas,revenue,stock_asc,nombre',
            'q' => 'sometimes|string|max:120',
            'page' => 'sometimes|integer|min:1',
        ]);

        $orden = $validated['orden'] ?? 'ventas';
        $days = 90;
        $from = now()->subDays($days)->startOfDay();

        $products = Product::query()
            ->join('categories as c', 'c.id', '=', 'products.category_id')
            ->selectRaw(
                'products.id, products.sku, products.name, c.name as category, products.sale_price, products.low_stock_threshold,
                COALESCE(SUM(CASE WHEN o.status = \'completado\' AND o.ordered_at >= ? THEN oi.quantity ELSE 0 END), 0) as units,
                COALESCE(SUM(CASE WHEN o.status = \'completado\' AND o.ordered_at >= ? THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as revenue',
                [$from, $from]
            )
            ->leftJoin('product_variants as pv', 'pv.product_id', '=', 'products.id')
            ->leftJoin('order_items as oi', 'oi.product_variant_id', '=', 'pv.id')
            ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
            ->when($validated['categoria'] ?? null, fn ($q, $id) => $q->where('products.category_id', $id))
            ->when($validated['q'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('products.name', 'like', "%$search%")
                ->orWhere('products.sku', 'like', "$search%")))
            ->groupBy('products.id', 'products.sku', 'products.name', 'c.name', 'products.sale_price', 'products.low_stock_threshold')
            ->orderByRaw(match ($orden) {
                'revenue' => 'revenue desc',
                'stock_asc' => '(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = products.id) asc',
                'nombre' => 'products.name asc',
                default => 'units desc',
            })
            ->with('variants:id,product_id,stock,size')
            ->paginate(12)
            ->withQueryString();

        $bestSeller = $products->first()?->name;

        return Inertia::render('products/index', [
            'products' => $products,
            'categorias' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'periodo' => $days,
            'filters' => [
                'categoria' => $validated['categoria'] ?? null,
                'orden' => $orden,
                'q' => $validated['q'] ?? null,
            ],
        ]);
    }
}
