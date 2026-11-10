<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales de la tienda Shopify guardadas desde Settings → Shopify,
 * para no depurar solo del .env. Una sola fila (singleton).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('store_domain')->nullable();
            $table->string('access_token')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->boolean('last_check_ok')->nullable();
            $table->text('last_check_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_settings');
    }
};
