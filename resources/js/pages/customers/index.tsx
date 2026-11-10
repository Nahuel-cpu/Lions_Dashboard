import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Crown, Search, Sparkles, UserPlus, UsersRound } from 'lucide-react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AnimatedNumber from '@/components/lions/animated-number';
import { clientes as customersRoute } from '@/routes';
import type { CustomersProps } from '@/types/lions';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

const TABS: { value: string; label: string; icon: typeof Crown }[] = [
    { value: 'todos', label: 'Todos', icon: UsersRound },
    { value: 'nuevos', label: 'Nuevos (30 días)', icon: UserPlus },
    { value: 'recurrentes', label: 'Recurrentes', icon: Sparkles },
    { value: 'vip', label: 'VIP', icon: Crown },
];

export default function Customers(props: CustomersProps) {
    const { customers, summary, filters } = props;
    const [tab, setTab] = useState(filters.tipo);
    const [search, setSearch] = useState(filters.q ?? '');
    const debounced = useDebouncedValue(search, 350);

    const navigate = (tipo: string, orden: string, q: string) => {
        router.get(
            customersRoute({ query: { tipo, orden, q: q ? q : undefined } }).url,
            {},
            {
                only: ['customers', 'summary', 'filters'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    useEffect(() => {
        if (debounced === (filters.q ?? '')) {
            return;
        }
        navigate(filters.tipo, filters.orden, debounced);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    return (
        <>
            <Head title="Clientes · Lions" />

            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <motion.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex flex-wrap items-center justify-between gap-4"
                >
                    <h1 className="text-xl font-bold tracking-tight">Clientes</h1>
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar nombre, email o ciudad"
                            className="pl-8"
                        />
                    </div>
                </motion.div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Summary label="Nuevos (30 días)" value={summary.nuevos} icon={UserPlus} />
                    <Summary label="Recurrentes" value={summary.recurrentes} icon={Sparkles} />
                    <Summary label="Base total" value={summary.total} icon={UsersRound} />
                    <Summary
                        label="Ticket promedio (30d)"
                        value={summary.ticket_promedio}
                        icon={Crown}
                        format={(n) => formatCurrency(n)}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {TABS.map((item) => (
                        <button
                            key={item.value}
                            onClick={() => {
                                setTab(item.value);
                                navigate(item.value, filters.orden, debounced);
                            }}
                            className={
                                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ' +
                                (tab === item.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-card text-muted-foreground hover:text-foreground')
                            }
                        >
                            <item.icon className="size-3.5" /> {item.label}
                        </button>
                    ))}
                    <Select
                        value={filters.orden}
                        onValueChange={(value) => navigate(tab, value, debounced)}
                    >
                        <SelectTrigger className="w-[170px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="revenue">Más gastos</SelectItem>
                            <SelectItem value="pedidos">Más pedidos</SelectItem>
                            <SelectItem value="reciente">Más recientes</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Ciudad</th>
                                    <th className="px-4 py-3 font-medium">Registrado</th>
                                    <th className="px-4 py-3 text-right font-medium">Pedidos</th>
                                    <th className="px-4 py-3 text-right font-medium">Gasto total</th>
                                    <th className="px-4 py-3 font-medium">Último pedido</th>
                                </tr>
                            </thead>
                            <tbody>
                                {customers.data.map((customer) => (
                                    <tr key={customer.id} className="border-b border-border/50 transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium">{customer.name}</p>
                                            <p className="text-xs text-muted-foreground">{customer.email}</p>
                                        </td>
                                        <td className="px-4 py-2.5 text-muted-foreground">{customer.city}</td>
                                        <td className="px-4 py-2.5 text-muted-foreground">{formatDateTime(customer.created_at)}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">
                                            <span className="font-semibold">{formatNumber(customer.completed_orders)}</span>
                                            {customer.pending_orders > 0 && (
                                                <span className="ml-1 text-xs text-amber-500">+{customer.pending_orders} pend</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{formatCurrency(customer.revenue)}</td>
                                        <td className="px-4 py-2.5 text-muted-foreground">
                                            {customer.last_order ? formatDateTime(customer.last_order) : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </motion.div>

                <p className="text-center text-xs text-muted-foreground">
                    Página {customers.current_page} de {customers.last_page} · {formatNumber(customers.total)} clientes
                </p>
            </div>
        </>
    );
}

function Summary({
    label,
    value,
    icon: Icon,
    format,
}: {
    label: string;
    value: number;
    icon: typeof Crown;
    format?: (n: number) => string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <p className="text-xs text-muted-foreground">{label}</p>
                <span className="rounded-lg bg-muted p-1.5 text-chart-5">
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-1 text-lg font-semibold tabular-nums">
                <AnimatedNumber value={value} format={format} />
            </p>
        </div>
    );
}

Customers.layout = {
    breadcrumbs: [{ title: 'Panel Lions', href: '' }, { title: 'Clientes', href: '' }],
};
