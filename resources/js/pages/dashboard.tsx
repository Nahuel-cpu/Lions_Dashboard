import { Head } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    BadgeDollarSign,
    Box,
    CheckCircle2,
    CreditCard,
    ShoppingCart,
    Timer,
    TrendingUp,
    UserPlus,
} from 'lucide-react';
import KpiCard from '@/components/lions/kpi-card';
import FilterBar from '@/components/lions/filter-bar';
import ActivityFeed from '@/components/lions/activity-feed';
import OperationsBridge from '@/components/lions/operations-bridge';
import StockAlerts from '@/components/lions/stock-alerts';
import SalesMap from '@/components/lions/sales-map';
import { OrdersBarChart, SalesAreaChart } from '@/components/lions/sales-chart';
import { CategoryDonut, TopCities, TopProducts } from '@/components/lions/panels';
import AnimatedNumber from '@/components/lions/animated-number';
import { dashboard } from '@/routes';
import type { DashboardProps } from '@/types/lions';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

function Panel({
    title,
    action,
    className,
    delay = 0,
    children,
}: {
    title: string;
    action?: React.ReactNode;
    className?: string;
    delay?: number;
    children: React.ReactNode;
}) {
    return (
        <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay, duration: 0.4, ease: 'easeOut' }}
            className={`rounded-xl border bg-card p-5 shadow-sm ${className ?? ''}`}
        >
            <header className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-semibold">{title}</h2>
                {action}
            </header>
            {children}
        </motion.section>
    );
}

export default function Dashboard(props: DashboardProps) {
    const { kpis, serie, top, mapa, alertas, actividad, categorias, categoriaShare, topCiudades, filters, updatedAt } =
        props;

    return (
        <>
            <Head title="Panel Lions" />
            <OperationsBridge />

            <div className="group flex flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <motion.div
                    initial={{ opacity: 0, x: -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    className="flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h1 className="text-xl font-bold tracking-tight">Panel de operaciones</h1>
                    <p className="text-sm text-muted-foreground">
                        Actualización en vivo · Zapatos Lions
                    </p>
                    </div>
                    <div className="flex shrink-0 flex-1 justify-end min-w-[480px]">
                        <FilterBar filters={filters} categorias={categorias} updatedAt={updatedAt} />
                    </div>
                </motion.div>

                {/* KPIs */}
                <div className="grid grid-cols-2 gap-4 xl:grid-cols-6">
                    <KpiCard
                        index={0}
                        label="Ingresos totales"
                        value={kpis.revenue}
                        format={(n) => formatCurrency(n, true)}
                        icon={BadgeDollarSign}
                        accent="text-chart-1"
                        trend={kpis.revenue_delta >= 0 ? 'up' : 'down'}
                        trendLabel={`${formatPercent(kpis.revenue_delta)} segunda mitad vs. primera`}
                    />
                    <KpiCard
                        index={1}
                        label="Beneficios"
                        value={kpis.profit}
                        format={(n) => formatCurrency(n, true)}
                        icon={TrendingUp}
                        accent="text-emerald-500"
                        trendLabel={`Margen ${kpis.margin}%`}
                    />
                    <KpiCard
                        index={2}
                        label="Ticket promedio"
                        value={kpis.avg_ticket}
                        format={(n) => formatCurrency(n)}
                        icon={CreditCard}
                        accent="text-chart-3"
                    />
                    <KpiCard
                        index={3}
                        label="Pedidos completados"
                        value={kpis.completed}
                        format={(n) => formatNumber(n)}
                        icon={CheckCircle2}
                        accent="text-chart-2"
                    />
                    <KpiCard
                        index={4}
                        label="Pedidos pendientes"
                        value={kpis.pending}
                        format={(n) => formatNumber(n)}
                        icon={Timer}
                        accent="text-amber-500"
                    />
                    <KpiCard
                        index={5}
                        label="Clientes nuevos"
                        value={kpis.new_customers}
                        format={(n) => formatNumber(n)}
                        icon={UserPlus}
                        accent="text-chart-5"
                        trendLabel={`${kpis.returning_customers} recurrentes`}
                    />
                </div>

                {/* Serie temporal + torta */}
                <div className="grid gap-4 xl:grid-cols-3">
                    <Panel
                        title="Ventas del período"
                        action={<span className="text-xs text-muted-foreground">{formatCurrency(kpis.revenue, true)} en completados</span>}
                        className="xl:col-span-2"
                        delay={0.15}
                    >
                        <SalesAreaChart data={serie} granularidad={filters.granularidad} />
                    </Panel>
                    <Panel title="Ingreso por categoría" delay={0.2}>
                        <CategoryDonut share={categoriaShare} />
                    </Panel>
                </div>

                {/* Mapa + top productos + alertas */}
                <div className="grid gap-4 xl:grid-cols-3">
                    <Panel
                        title="Distribución de ventas"
                        className="xl:col-span-2"
                        delay={0.25}
                    >
                        <SalesMap points={mapa} />
                        <div className="mt-4">
                            <TopCities cities={topCiudades} />
                        </div>
                    </Panel>

                    <div className="flex flex-col gap-4">
                        <Panel title="Productos más vendidos" delay={0.3}>
                            <TopProducts top={top} />
                        </Panel>
                        <Panel
                            title="Alertas de stock"
                            delay={0.35}
                            action={<span className="text-xs text-muted-foreground">{alertas.length} variantes</span>}
                        >
                            <StockAlerts alerts={alertas} />
                        </Panel>
                    </div>
                </div>

                {/* Actividad real + pedidos por bucket */}
                <div className="grid gap-4 xl:grid-cols-3">
                    <Panel title="Actividad en tiempo real" delay={0.4}
                        action={
                            <span className="flex items-center gap-1.5 text-xs text-emerald-500">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                                </span>
                                EN VIVO
                            </span>
                        }
                    >
                        <div className="max-h-[360px] overflow-y-auto pr-1">
                            <ActivityFeed initial={actividad} />
                        </div>
                    </Panel>

                    <Panel title="Pedidos por período" className="xl:col-span-2" delay={0.45}>
                        <OrdersBarChart data={serie} granularidad={filters.granularidad} />
                        <div className="mt-3 flex flex-wrap gap-6 border-t border-border/60 pt-3 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1.5">
                                <ShoppingCart className="size-3.5" /> {formatNumber(kpis.orders)} completados · ticket {formatCurrency(kpis.avg_ticket)}
                            </span>
                            <span className="flex items-center gap-1.5">
                                <Box className="size-3.5" /> {kpis.cancelled} cancelados en el período
                            </span>
                        </div>
                    </Panel>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Panel Lions',
            href: dashboard(),
        },
    ],
};
