<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomersController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'tipo' => 'sometimes|string|in:todos,nuevos,recurrentes,vip',
            'q' => 'sometimes|string|max:120',
            'page' => 'sometimes|integer|min:1',
            'orden' => 'sometimes|string|in:revenue,pedidos,reciente',
        ]);

        $tipo = $validated['tipo'] ?? 'todos';
        $orden = $validated['orden'] ?? 'revenue';
        $periodoDias = 30;
        $from = now()->subDays($periodoDias)->startOfDay();

        $query = DB::table('customers as cu')
            ->selectRaw(
                'cu.id, cu.name, cu.email, cu.city, cu.created_at,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = cu.id AND o.status = \'completado\') as completed_orders,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = cu.id AND o.status = \'pendiente\') as pending_orders,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = cu.id) as total_orders,
                (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.customer_id = cu.id AND o.status = \'completado\') as revenue,
                (SELECT MAX(o.ordered_at) FROM orders o WHERE o.customer_id = cu.id) as last_order'
            )
            ->when($validated['q'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('cu.name', 'like', "$search%")
                ->orWhere('cu.email', 'like', "%$search%")
                ->orWhere('cu.city', 'like', "%$search%")))
            ->when($tipo === 'nuevos', fn ($q) => $q->where('cu.created_at', '>=', $from))
            ->when($tipo === 'recurrentes', fn ($q) => $q->whereRaw(
                "(SELECT COUNT(*) FROM orders o WHERE o.customer_id = cu.id AND o.status = 'completado') >= 2"
            ))
            ->when($tipo === 'vip', fn ($q) => $q->whereRaw(
                "(SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.customer_id = cu.id AND o.status = 'completado') >= 800000"
            ));

        $customers = $query->orderByRaw(match ($orden) {
            'pedidos' => 'completed_orders desc',
            'reciente' => 'cu.created_at desc',
            default => 'revenue desc',
        })
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'nuevos' => DB::table('customers')->where('created_at', '>=', $from)->count(),
            'recurrentes' => DB::table('customers as cu')->whereRaw(
                "(SELECT COUNT(*) FROM orders o WHERE o.customer_id = cu.id AND o.status = 'completado') >= 2"
            )->count(),
            'total' => DB::table('customers')->count(),
            'ticket_promedio' => (float) DB::table('orders')->where('status', 'completado')->where('ordered_at', '>=', $from)->avg('total'),
        ];

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'summary' => $summary,
            'periodoDias' => $periodoDias,
            'filters' => [
                'tipo' => $tipo,
                'orden' => $orden,
                'q' => $validated['q'] ?? null,
            ],
        ]);
    }
}
