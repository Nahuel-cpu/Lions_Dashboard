<?php

namespace App\Services\Shopify;

use RuntimeException;

/** Shopify respondió THROTTLED: el bucket de la API está vacío. */
final class ShopifyThrottledException extends RuntimeException {}
