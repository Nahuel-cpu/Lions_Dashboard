import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { DashboardFilters } from '@/types/lions';
import { cn } from '@/lib/utils';
import { formatDateTime } from '@/lib/format';

const PERIODS: { value: string; label: string }[] = [
    { value: '7', label: '7 días' },
    { value: '30', label: '30 días' },
    { value: '90', label: '90 días' },
    { value: '365', label: '12 meses' },
];

const GRANULARIDAD: { value: string; label: string }[] = [
    { value: 'dia', label: 'Por día' },
    { value: 'semana', label: 'Por semana' },
    { value: 'mes', label: 'Por mes' },
];

export default function FilterBar({
    filters,
    categorias,
    updatedAt,
}: {
    filters: DashboardFilters;
    categorias: { id: number; name: string }[];
    updatedAt: string;
}) {
    const [period, setPeriod] = useState(String(filters.period));
    const [granularidad, setGranularidad] = useState<string>(
        filters.granularidad,
    );
    const [categoria, setCategoria] = useState(
        filters.categoria ? String(filters.categoria) : 'todas',
    );
    const [pending, setPending] = useState(false);

    useEffect(() => {
        setPeriod(String(filters.period));
        setGranularidad(filters.granularidad);
        setCategoria(filters.categoria ? String(filters.categoria) : 'todas');
    }, [filters]);

    const push = (
        next: {
            period?: string;
            granularidad?: string;
            categoria?: string;
        } = {},
    ) => {
        const gran = next.granularidad ?? granularidad;
        let target = next.period ?? period;
        if (gran === 'mes' && target === '7') {
            target = '30';
        }
        const cat = next.categoria ?? categoria;

        setPeriod(target);
        setGranularidad(gran);
        setCategoria(cat);

        router.get(
            dashboard({
                query: {
                    period: target,
                    granularidad: gran,
                    categoria: cat === 'todas' ? undefined : cat,
                },
            }).url,
            {},
            {
                only: [
                    'kpis',
                    'serie',
                    'top',
                    'mapa',
                    'categoriaShare',
                    'topCiudades',
                    'alertas',
                    'filters',
                    'updatedAt',
                ],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setPending(true),
                onFinish: () => setPending(false),
            },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-3">
            <InlineTabs
                options={PERIODS}
                value={period}
                onChange={(value) => push({ period: value })}
                disabledValues={granularidad === 'mes' ? ['7'] : []}
            />
            <Select
                value={granularidad}
                onValueChange={(value) => push({ granularidad: value })}
            >
                <SelectTrigger size="sm" className="w-[140px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {GRANULARIDAD.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={categoria}
                onValueChange={(value) => push({ categoria: value })}
            >
                <SelectTrigger size="sm" className="w-[150px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="todas">Todas las categorías</SelectItem>
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
            <div className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                {pending && (
                    <span className="animate-pulse text-chart-1">
                        Actualizando…
                    </span>
                )}
                {!pending && (
                    <span>
                        Actualizado {formatDateTime(updatedAt).slice(-5)}
                    </span>
                )}
            </div>
        </div>
    );
}

function InlineTabs({
    options,
    value,
    onChange,
    disabledValues = [],
}: {
    options: { value: string; label: string }[];
    value: string;
    onChange: (value: string) => void;
    disabledValues?: string[];
}) {
    return (
        <div className="inline-flex rounded-lg border bg-card p-0.5">
            {options.map((option) => {
                const disabled = disabledValues.includes(option.value);
                return (
                    <button
                        key={option.value}
                        onClick={() => onChange(option.value)}
                        disabled={disabled}
                        title={
                            disabled
                                ? 'No disponible con la granularidad "Por mes"'
                                : undefined
                        }
                        className={cn(
                            'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                            value === option.value
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                            disabled &&
                                'cursor-not-allowed opacity-40 hover:text-muted-foreground',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
