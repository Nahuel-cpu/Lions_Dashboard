<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales OAuth del Dev Dashboard de Shopify (client credentials grant).
 * La Admin API ya no entrega tokens shpat_ estáticos desde el admin: la app
 * intercambia client_id + client_secret por un token de acceso que dura 24h
 * y se renueva solo (se cachea en access_token/token_expires_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_settings', function (Blueprint $table): void {
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->timestamp('token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_settings', function (Blueprint $table): void {
            $table->dropColumn(['client_id', 'client_secret', 'token_expires_at']);
        });
    }
};
