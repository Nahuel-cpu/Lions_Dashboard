import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Footprints, Search, ShoppingBag } from 'lucide-react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { productos as productsRoute } from '@/routes';
import type { ProductsProps } from '@/types/lions';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

export default function Products(props: ProductsProps) {
    const { products, categorias, periodo, filters } = props;
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
        orden?: string;
    }) => {
        router.get(
            productsRoute({
                query: {
                    categoria:
                        overrides.categoria !== undefined
                            ? (overrides.categoria ?? undefined)
                            : (filters.categoria ?? undefined),
                    orden: overrides.orden ?? filters.orden,
                    q: debounced ? debounced : undefined,
                    page: 1,
                },
            }).url,
            {},
            {
                only: ['products', 'filters'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Productos · Lions" />

            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <motion.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h1 className="text-xl font-bold tracking-tight">
                            Productos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Ventas de los últimos {periodo} días ·{' '}
                            {formatNumber(products.total)} modelos
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="relative w-64">
                            <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar modelo o SKU"
                                className="pl-8"
                            />
                        </div>
                        <Select
                            value={String(filters.categoria ?? 'todas')}
                            onValueChange={(value) =>
                                navigate({
                                    categoria: value === 'todas' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-[150px]">
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
                            onValueChange={(value) =>
                                navigate({ orden: value })
                            }
                        >
                            <SelectTrigger className="w-[160px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ventas">
                                    Más vendidos
                                </SelectItem>
                                <SelectItem value="revenue">
                                    Más ingresos
                                </SelectItem>
                                <SelectItem value="stock_asc">
                                    Menor stock
                                </SelectItem>
                                <SelectItem value="nombre">Nombre</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </motion.div>

                <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    {products.data.map((product, index) => {
                        const stock = product.variants.reduce(
                            (sum, variant) => sum + variant.stock,
                            0,
                        );
                        const lowSize = product.variants
                            .filter(
                                (variant) =>
                                    variant.stock <=
                                    product.low_stock_threshold,
                            )
                            .map((variant) => variant.size);

                        return (
                            <motion.div
                                key={product.id}
                                initial={{ opacity: 0, y: 14 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.04 * (index % 12) }}
                                className="rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <div className="mb-3 flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">
                                            {product.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {product.category} · {product.sku}
                                        </p>
                                    </div>
                                    <span className="flex size-9 items-center justify-center rounded-lg bg-chart-2/10 text-chart-2">
                                        <Footprints className="size-4.5" />
                                    </span>
                                </div>
                                <div className="grid grid-cols-3 gap-2 text-center">
                                    <div className="rounded-lg bg-muted/50 p-2">
                                        <p className="text-[11px] text-muted-foreground">
                                            Vendidos
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatNumber(
                                                Number(product.units),
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted/50 p-2">
                                        <p className="text-[11px] text-muted-foreground">
                                            Ingresos
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatCurrency(
                                                Number(product.revenue),
                                                true,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted/50 p-2">
                                        <p className="text-[11px] text-muted-foreground">
                                            Stock
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatNumber(stock)}
                                        </p>
                                    </div>
                                </div>
                                {lowSize.length > 0 && (
                                    <p className="mt-2.5 flex items-center gap-1.5 text-xs text-amber-500">
                                        <ShoppingBag className="size-3.5" />
                                        Repone talle {lowSize.join(', ')}
                                    </p>
                                )}
                            </motion.div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

Products.layout = {
    breadcrumbs: [
        { title: 'Panel Lions', href: '' },
        { title: 'Productos', href: '' },
    ],
};
