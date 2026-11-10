<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IDs globales de Shopify (gid://shopify/...) para upserts idempotentes
 * durante la sincronización: nunca se crean duplicados al re-importar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('shopify_id')->nullable();
            $table->unique('shopify_id');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('shopify_id')->nullable();
            $table->unique('shopify_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('shopify_id')->nullable();
            $table->unique('shopify_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shopify_id')->nullable();
            $table->unique('shopify_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('shopify_id');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('shopify_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('shopify_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('shopify_id');
        });
    }
};
