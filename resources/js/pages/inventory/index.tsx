import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import {
    BadgeCheck,
    ArrowDownWideNarrow,
    PackageSearch,
    TriangleAlert,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AnimatedNumber from '@/components/lions/animated-number';
import { inventario as inventoryRoute } from '@/routes';
import type { InventoryProps } from '@/types/lions';
import { cn } from '@/lib/utils';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

export default function Inventory(props: InventoryProps) {
    const { variants, alertas, resumen, categorias, filters } = props;
    const [search, setSearch] = useState(filters.q ?? '');
    const debounced = useDebouncedValue(search, 350);

    useEffect(() => {
        if (debounced === (filters.q ?? '')) {
            return;
        }
        navigate({});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const navigate = (overrides: {
        categoria?: string | null;
        solo_low?: string | null;
        orden?: string;
    }) => {
        const soloLow =
            overrides.solo_low !== undefined
                ? overrides.solo_low
                : filters.solo_low
                  ? '1'
                  : undefined;

        router.get(
            inventoryRoute({
                query: {
                    categoria:
                        overrides.categoria !== undefined
                            ? (overrides.categoria ?? undefined)
                            : (filters.categoria ?? undefined),
                    solo_low: soloLow,
                    orden: overrides.orden ?? filters.orden,
                    q: debounced ? debounced : undefined,
                },
            }).url,
            {},
            {
                only: ['variants', 'alertas', 'resumen', 'filters'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const restock = (variantId: number, cantidad: number) => {
        router.patch(
            `/inventario/${variantId}/restock`,
            { cantidad },
            {
                only: ['variants', 'alertas', 'resumen'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Inventario · Lions" />

            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <motion.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex flex-wrap items-center justify-between gap-4"
                >
                    <h1 className="text-xl font-bold tracking-tight">
                        Inventario
                    </h1>
                    <div className="relative w-full max-w-xs">
                        <PackageSearch className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar modelo o SKU"
                            className="pl-8"
                        />
                    </div>
                </motion.div>

                <div className="grid grid-cols-3 gap-3">
                    <Summary
                        label="Unidades totales"
                        value={resumen.total_stock}
                    />
                    <Summary
                        label="Valor del stock"
                        value={resumen.valor}
                        format={(n) => formatCurrency(n, true)}
                    />
                    <Summary
                        label="Variantes en stock bajo"
                        value={resumen.sku_bajo}
                        accent="text-amber-500"
                    />
                </div>

                {alertas.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-sm">
                        <TriangleAlert className="size-4 shrink-0 text-amber-500" />
                        <p className="text-xs text-amber-600 dark:text-amber-400">
                            Variantes críticas:{' '}
                            {alertas
                                .map(
                                    (alert) =>
                                        `${alert.product} (${alert.size})`,
                                )
                                .slice(0, 4)
                                .join(' · ')}
                            {alertas.length > 4 &&
                                ` +${alertas.length - 4} más`}
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant={filters.solo_low ? 'default' : 'outline'}
                        size="sm"
                        className="h-8 gap-1.5 text-xs"
                        onClick={() =>
                            navigate({
                                solo_low: filters.solo_low ? null : '1',
                            })
                        }
                    >
                        <TriangleAlert className="size-3.5" />
                        Solo stock bajo
                    </Button>

                    <Select
                        value={String(filters.categoria ?? 'todas')}
                        onValueChange={(value) =>
                            navigate({
                                categoria: value === 'todas' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="h-8 w-[150px] text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todas">Todas</SelectItem>
                            {categorias.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.orden}
                        onValueChange={(value) => navigate({ orden: value })}
                    >
                        <SelectTrigger className="h-8 w-[170px] text-xs">
                            <ArrowDownWideNarrow className="size-3.5" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="stock_asc">
                                Menor stock
                            </SelectItem>
                            <SelectItem value="stock_desc">
                                Mayor stock
                            </SelectItem>
                            <SelectItem value="nombre">Nombre</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    className="overflow-hidden rounded-xl border bg-card shadow-sm"
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Producto
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Talle
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Stock
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {variants.data.map((variant) => {
                                    const low =
                                        variant.stock <=
                                        variant.low_stock_threshold;

                                    return (
                                        <tr
                                            key={variant.id}
                                            className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-2.5">
                                                <p className="font-medium">
                                                    {variant.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {variant.category} ·{' '}
                                                    {variant.sku}
                                                </p>
                                            </td>
                                            <td className="px-4 py-2.5 text-muted-foreground tabular-nums">
                                                EU {variant.size}
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-semibold tabular-nums">
                                                {variant.stock}
                                            </td>
                                            <td className="px-4 py-2.5 text-right">
                                                <span
                                                    className={cn(
                                                        'rounded-md border px-2 py-0.5 text-xs font-medium',
                                                        low
                                                            ? 'border-amber-500/30 bg-amber-500/10 text-amber-500'
                                                            : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
                                                    )}
                                                >
                                                    {low ? 'reponer' : 'ok'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2.5 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 gap-1.5 text-xs"
                                                    onClick={() =>
                                                        restock(variant.id, 50)
                                                    }
                                                >
                                                    <BadgeCheck className="size-3.5" />{' '}
                                                    +50
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </motion.div>

                <p className="text-center text-xs text-muted-foreground">
                    Página {variants.current_page} de {variants.last_page} ·{' '}
                    {formatNumber(variants.total)} variantes
                </p>
            </div>
        </>
    );
}

function Summary({
    label,
    value,
    format,
    accent = '',
}: {
    label: string;
    value: number;
    format?: (n: number) => string;
    accent?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'mt-1 text-lg font-semibold tabular-nums',
                    accent,
                )}
            >
                <AnimatedNumber value={value} format={format} />
            </p>
        </div>
    );
}

Inventory.layout = {
    breadcrumbs: [
        { title: 'Panel Lions', href: '' },
        { title: 'Inventario', href: '' },
    ],
};
