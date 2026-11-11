<?php

return [

    'store' => env('SHOPIFY_STORE_DOMAIN', ''),

    'client_id' => env('SHOPIFY_CLIENT_ID', ''),

    'client_secret' => env('SHOPIFY_CLIENT_SECRET', ''),

    'token' => env('SHOPIFY_ACCESS_TOKEN', ''),

    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', ''),

    'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),

    'price_scale' => (float) env('SHOPIFY_PRICE_SCALE', 1),

    'pagination' => env('SHOPIFY_PAGE_SIZE', 250),

    'enabled' => fn (): bool => config('shopify.store') !== ''
        && (config('shopify.client_secret') !== '' || config('shopify.token') !== ''),
];
