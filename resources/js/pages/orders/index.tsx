import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { BadgeCheck, CheckCircle2, Clock, Printer, Search, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { pedidos as ordersRoute } from '@/routes';
import pedidosRoutes from '@/routes/pedidos';
import type { OrdersProps, OrderStatus } from '@/types/lions';
import { cn } from '@/lib/utils';
import { formatCurrency, formatDateTime } from '@/lib/format';

const TABS: { value: string; label: string; icon: typeof Clock }[] = [
    { value: 'todos', label: 'Todos', icon: Printer },
    { value: 'pendiente', label: 'Pendientes', icon: Clock },
    { value: 'completado', label: 'Completados', icon: CheckCircle2 },
    { value: 'cancelado', label: 'Cancelados', icon: XCircle },
];

const STATUS_STYLES: Record<OrderStatus, string> = {
    pendiente: 'bg-amber-500/15 text-amber-500 border-amber-500/30',
    completado: 'bg-emerald-500/15 text-emerald-500 border-emerald-500/30',
    cancelado: 'bg-rose-500/15 text-rose-500 border-rose-500/30',
};

export default function Orders(props: OrdersProps) {
    const { orders, counts, todayRevenue, filters } = props;
    const [search, setSearch] = useState(filters.q ?? '');
    const debounced = useDebouncedValue(search, 350);

    useEffect(() => {
        if (debounced === (filters.q ?? '')) {
            return;
        }
        navigateFilter(debounced ? debounced : undefined);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const navigateFilter = (q: string | undefined) => {
        router.get(
            ordersRoute({ query: { estado: filters.estado, q: q ? q : undefined } }).url,
            {},
            {
                only: ['orders', 'counts', 'todayRevenue', 'filters'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const confirm = (id: number) => {
        const target = pedidosRoutes.confirmar({ order: id });
        router.post(
            target.url,
            {},
            { only: ['orders', 'counts', 'todayRevenue', 'filters'], preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Pedidos · Lions" />

            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <motion.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h1 className="text-xl font-bold tracking-tight">Pedidos</h1>
                        <p className="text-sm text-muted-foreground">
                            Cobranza del día: <span className="font-semibold text-foreground">{formatCurrency(todayRevenue)}</span>
                        </p>
                    </div>
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar cliente, ciudad o #id"
                            className="pl-8"
                        />
                    </div>
                </motion.div>

                <div className="flex flex-wrap items-center gap-2">
                    {TABS.map((tab) => (
                        <button
                            key={tab.value}
                            onClick={() =>
                                router.get(
                                    ordersRoute({ query: { estado: tab.value, q: search || undefined } }).url,
                                    {},
                                    {
                                        only: ['orders', 'counts', 'todayRevenue', 'filters'],
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                )
                            }
                            className={cn(
                                'inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors',
                                filters.estado === tab.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-card text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <tab.icon className="size-3.5" />
                            {tab.label}
                            <span className={cn('rounded-full px-1.5 tabular-nums', filters.estado === tab.value && 'bg-background/20')}>
                                {counts[tab.value as keyof typeof counts] ?? 0}
                            </span>
                        </button>
                    ))}
                </div>

                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    className="overflow-hidden rounded-xl border bg-card shadow-sm"
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">#</th>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Ciudad</th>
                                    <th className="px-4 py-3 font-medium">Fecha</th>
                                    <th className="px-4 py-3 text-right font-medium">Total</th>
                                    <th className="px-4 py-3 font-medium">Estado</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((order) => (
                                    <tr key={order.id} className="border-b border-border/50 transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-2.5 font-mono text-xs text-muted-foreground">#{order.id}</td>
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium">{order.customer.name}</p>
                                            <p className="text-xs text-muted-foreground">{order.customer.email}</p>
                                        </td>
                                        <td className="px-4 py-2.5 text-muted-foreground">{order.customer.city}</td>
                                        <td className="px-4 py-2.5 text-muted-foreground">{formatDateTime(order.ordered_at)}</td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{formatCurrency(order.total)}</td>
                                        <td className="px-4 py-2.5">
                                            <Badge variant="outline" className={cn('font-medium capitalize', STATUS_STYLES[order.status])}>
                                                {order.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            {order.status === 'pendiente' && !order.shopify_id && (
                                                <Button size="sm" variant="outline" className="h-7 gap-1.5 text-xs" onClick={() => confirm(order.id)}>
                                                    <BadgeCheck className="size-3.5" /> Confirmar
                                                </Button>
                                            )}
                                            {order.status === 'completado' && (
                                                <span className="text-xs text-emerald-500">✓ cobrado</span>
                                            )}
                                            {order.status === 'pendiente' && order.shopify_id !== null && order.shopify_id !== '' && (
                                                <span className="text-xs text-muted-foreground">gestión en Shopify</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </motion.div>

                <Pagination
                    meta={orders}
                    funcNavigate={(page) =>
                        router.get(
                            ordersRoute({ query: { estado: filters.estado, q: search || undefined, page } }).url,
                            {},
                            { only: ['orders', 'counts'], preserveState: true },
                        )
                    }
                />
            </div>
        </>
    );
}

function Pagination({ meta, funcNavigate }: { meta: { current_page: number; last_page: number; total: number }; funcNavigate: (page: number) => void }) {
    if (meta.last_page <= 1) {
        return <p className="text-center text-xs text-muted-foreground">{meta.total} pedidos</p>;
    }

    return (
        <div className="flex items-center justify-center gap-2">
            {meta.current_page > 1 && (
                <Button variant="outline" size="sm" onClick={() => funcNavigate(meta.current_page - 1)}>
                    ← Anterior
                </Button>
            )}
            <span className="text-xs text-muted-foreground">
                Página {meta.current_page} de {meta.last_page} · {meta.total} pedidos
            </span>
            {meta.current_page < meta.last_page && (
                <Button variant="outline" size="sm" onClick={() => funcNavigate(meta.current_page + 1)}>
                    Siguiente →
                </Button>
            )}
        </div>
    );
}

Orders.layout = {
    breadcrumbs: [{ title: 'Panel Lions', href: '' }, { title: 'Pedidos', href: '' }],
};
