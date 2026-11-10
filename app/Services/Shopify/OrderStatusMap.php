<?php

namespace App\Services\Shopify;

/**
 * Traduce el estado fluido de Shopify (financialStatus +
 * fulfillmentStatus + cancelado) a los tres estados locales del
 * dashboard: pendiente / completado / cancelado.
 *
 * Regla (documentada y ajustable):
 *  - pedido cancelado en Shopify               → cancelado
 *  - PAID + FULFILLED                          → completado
 *  - REFUNDED / VOIDED                         → cancelado
 *  - cualquier otra combinación                → pendiente
 */
final class OrderStatusMap
{
    /** @return 'pendiente'|'completado'|'cancelado' */
    public static function toLocal(?string $financial, ?string $fulfillment, ?string $cancelledAt): string
    {
        if ($cancelledAt !== null && $cancelledAt !== '') {
            return 'cancelado';
        }

        return match (strtoupper((string) $financial)) {
            'PAID' => strtoupper((string) $fulfillment) === 'FULFILLED' ? 'completado' : 'pendiente',
            'REFUNDED', 'VOIDED' => 'cancelado',
            default => 'pendiente',
        };
    }
}
