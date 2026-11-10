<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\ShopifySettingsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Settings → Shopify maneja credenciales y borrado masivo: sólo admin.
    Route::middleware('can:admin')->group(function (): void {
        Route::get('settings/shopify', [ShopifySettingsController::class, 'edit'])->name('settings.shopify');
        Route::post('settings/shopify', [ShopifySettingsController::class, 'update'])->name('settings.shopify.update');
        Route::post('settings/shopify/probar', [ShopifySettingsController::class, 'test'])->name('settings.shopify.test');
        Route::post('settings/shopify/sincronizar', [ShopifySettingsController::class, 'sync'])->name('settings.shopify.sync');
        Route::post('settings/shopify/vaciar-demo', [ShopifySettingsController::class, 'wipeDemo'])->name('settings.shopify.vaciar');
        Route::delete('settings/shopify', [ShopifySettingsController::class, 'destroy'])->name('settings.shopify.destroy');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
