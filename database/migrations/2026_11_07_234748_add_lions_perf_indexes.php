<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indices faltantes para las consultas analíticas del panel:
 * el filtro de categoría (EXISTS) escaneaba toda la tabla order_items
 * por cada pedido al no haber índice en order_items.order_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'order_items_order_id_index');
            $table->index('product_variant_id', 'order_items_variant_id_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id', 'products_category_id_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('city', 'customers_city_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_id_index');
            $table->dropIndex('order_items_variant_id_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_id_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_city_index');
        });
    }
};
