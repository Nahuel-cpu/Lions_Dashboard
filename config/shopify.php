<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conexión a Shopify (Admin API GraphQL)
    |--------------------------------------------------------------------------
    |
    | Shopify migró las apps custom al Dev Dashboard (dev.shopify.com) y ya
    | no entrega tokens shpat_ estáticos para apps nuevas. El flujo actual:
    |
    |   Dev Dashboard → app → Versiones → publicar versión con scopes
    |   (read_orders, read_customers, read_products, read_inventory)
    |   → instalar la app en la tienda → copiar Client ID y Secreto del
    |   cliente (Dev Dashboard → la app → Configuración).
    |
    | Con esas credenciales el backend obtiene tokens vía "client credentials
    | grant" (duran 24h y se renuevan solos). SHOPIFY_ACCESS_TOKEN queda
    | solamente como fallback legacy para apps viejas que todavía lo tengan.
    |
    */

    'store' => env('SHOPIFY_STORE_DOMAIN', ''),

    'client_id' => env('SHOPIFY_CLIENT_ID', ''),

    'client_secret' => env('SHOPIFY_CLIENT_SECRET', ''),

    // Deprecado: solo apps custom viejas creadas dentro del admin de la tienda.
    'token' => env('SHOPIFY_ACCESS_TOKEN', ''),

    // Secreto HTTP para verificar la firma (X-Shopify-Hmac-Sha256) de los
    // webhooks en /webhooks/shopify (formato shpss_, se ve en la conf de cada
    // webhook).
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', ''),

    'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),

    /*
    | Los montos llegan con decimales "5999.00" y este dashboard trabaja en
    | enteros. Escala 1 = pesos enteros; 100 = convertir a centavos.
    */
    'price_scale' => (float) env('SHOPIFY_PRICE_SCALE', 1),

    'pagination' => env('SHOPIFY_PAGE_SIZE', 250),

    'enabled' => fn (): bool => config('shopify.store') !== ''
        && (config('shopify.client_secret') !== '' || config('shopify.token') !== ''),
];
