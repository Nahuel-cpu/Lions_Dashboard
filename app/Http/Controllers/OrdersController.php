<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\Activity;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'estado' => 'sometimes|string|in:todos,pendiente,completado,cancelado',
            'q' => 'sometimes|string|max:120',
            'page' => 'sometimes|integer|min:1',
        ]);

        $status = $validated['estado'] ?? 'todos';

        $orders = Order::query()
            ->with('customer:id,name,email,city')
            ->when($status !== 'todos', fn ($q) => $q->where('status', $status))
            ->when($validated['q'] ?? null, function ($query, $search) {

                $query->where(function ($w) use ($search) {
                    $w->whereHas('customer', fn ($c) => $c->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%"))
                        ->orWhere('id', is_numeric($search) ? (int) $search : -1);
                });
            })
            ->orderByDesc('ordered_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'todos' => Order::count(),
            'pendiente' => Order::where('status', 'pendiente')->count(),
            'completado' => Order::where('status', 'completado')->count(),
            'cancelado' => Order::where('status', 'cancelado')->count(),
        ];

        $todayRevenue = (float) Order::where('status', 'completado')
            ->where('ordered_at', '>=', now()->startOfDay())->sum('total');

        return Inertia::render('orders/index', [
            'orders' => $orders,
            'counts' => $counts,
            'todayRevenue' => $todayRevenue,
            'filters' => ['estado' => $status, 'q' => $validated['q'] ?? null],
        ]);
    }

    public function conferir(Request $request, Order $order): RedirectResponse
    {
        $from = $order->status;

        if ($order->shopify_id !== null) {
            return back()->with('error', 'El pedido '.$order->id.' viene de Shopify: gestioná su estado en la tienda.');
        }

        if ($from === 'completado') {
            return back()->with('error', 'El pedido '.$order->id.' ya estaba completado.');
        }

        $order->update(['status' => 'completado']);

        $at = now()->toIso8601String();
        Activity::create([
            'type' => 'venta',
            'title' => 'Pedido confirmado',
            'description' => sprintf('Pedido #%d completado · %s', $order->id, '$'.number_format($order->total, 0, ',', '.')),
            'performed_at' => now(),
        ]);

        broadcast_safely(new OrderStatusChanged((string) $order->id, $from, 'completado', $at));

        return back()->with('success', 'Pedido #'.$order->id.' confirmado.');
    }
}
