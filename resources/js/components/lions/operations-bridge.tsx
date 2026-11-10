import { useEcho } from '@laravel/echo-react';
import type { ActivityItem } from '@/types/lions';
import { pushActivity } from '@/components/lions/activity-feed';

type AnyPayload = Record<string, unknown>;

/**
 * Suscripción WebSocket al canal privado 'operaciones' (Laravel Reverb).
 * Todos los eventos de la simulación en vivo llegan por acá.
 */
export default function OperationsBridge() {
    useEcho<AnyPayload>('operaciones', ['.order.created', '.order.status', '.stock.low'], (payload) => {
        const data = payload as AnyPayload;
        const at = String(data.at ?? '');
        let item: ActivityItem;

        if ('customerName' in data) {
            item = {
                id: -Date.now(),
                type: 'pedido',
                title: 'Nuevo pedido',
                description: `Pedido #${data.orderCode} · ${data.items} pares · ${data.customerName} (${data.city})`,
                at,
            };
        } else if ('from' in data) {
            item = {
                id: -Date.now() - 1,
                type: data.to === 'completado' ? 'venta' : 'envio',
                title: data.to === 'completado' ? 'Venta confirmada' : 'Estado actualizado',
                description: `Pedido #${data.orderCode}: ${data.from} → ${data.to}`,
                at,
            };
        } else {
            item = {
                id: -Date.now() - 2,
                type: 'baja',
                title: 'Stock bajo',
                description: `${data.productName} talle ${data.size}: quedan ${data.stock}`,
                at,
            };
        }

        pushActivity(item);
    });

    return null;
}
