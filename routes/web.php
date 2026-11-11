<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('webhooks/shopify', [ShopifyWebhookController::class, 'store'])
    ->name('webhooks.shopify');

Route::middleware(['auth', 'verified', 'can:admin'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('pedidos', [OrdersController::class, 'index'])->name('pedidos');
    Route::post('pedidos/{order}/confirmar', [OrdersController::class, 'conferir'])->name('pedidos.confirmar');

    Route::get('productos', [ProductsController::class, 'index'])->name('productos');
    Route::get('clientes', [CustomersController::class, 'index'])->name('clientes');
    Route::get('actividad', [ActivityController::class, 'recent'])->name('actividad');

    Route::get('inventario', [InventoryController::class, 'index'])->name('inventario');
    Route::patch('inventario/{variant}/restock', [InventoryController::class, 'restock'])->name('inventario.restock');
});

require __DIR__.'/settings.php';
