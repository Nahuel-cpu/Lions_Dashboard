<?php

use App\Services\Shopify\OrderStatusMap;

/*
 * Traducción de estados Shopify → locales usada por la sincronización.
 */
it('mapea estados de Shopify a estados del dashboard', function (array $shopify, string $local) {
    expect(OrderStatusMap::toLocal($shopify[0], $shopify[1], $shopify[2]))->toBe($local);
})->with([
    'pagado y entregado' => [['PAID', 'FULFILLED', null], 'completado'],
    'pagado sin entregar' => [['PAID', 'UNFULFILLED', null], 'pendiente'],
    'pagado con entrega parcial' => [['PAID', 'PARTIALLY_FULFILLED', null], 'pendiente'],
    'no pagado' => [['PENDING', null, null], 'pendiente'],
    'autorizado' => [['AUTHORIZED', 'UNFULFILLED', null], 'pendiente'],
    'cancelado en Shopify' => [['PENDING', null, '2026-11-09T10:00:00Z'], 'cancelado'],
    'cancelado aunque pagado' => [['PAID', 'FULFILLED', '2026-11-09T10:00:00Z'], 'cancelado'],
    'reembolsado' => [['REFUNDED', 'FULFILLED', null], 'cancelado'],
    'voided' => [['VOIDED', null, null], 'cancelado'],
    'nulos' => [[null, null, null], 'pendiente'],
    'unknown' => [['SOME_NEW_STATUS', 'WHATEVER', ''], 'pendiente'],
]);
