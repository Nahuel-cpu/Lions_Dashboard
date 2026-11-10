import { motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';
import AnimatedNumber from '@/components/lions/animated-number';

type Trend = 'up' | 'down' | 'off';

export default function KpiCard({
    label,
    value,
    format,
    icon: Icon,
    accent = 'text-chart-1',
    trend,
    trendLabel,
    index = 0,
}: {
    label: string;
    value: number;
    format?: (n: number) => string;
    icon: LucideIcon;
    accent?: string;
    trend?: Trend;
    trendLabel?: string;
    index?: number;
}) {
    const trendColor =
        trend === 'up'
            ? 'text-emerald-500'
            : trend === 'down'
                ? 'text-rose-500'
                : 'text-muted-foreground';

    return (
        <motion.div
            initial={{ opacity: 0, y: 24, scale: 0.97 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{
                delay: 0.06 * index,
                type: 'spring',
                stiffness: 260,
                damping: 24,
            }}
            whileHover={{ y: -3 }}
            className="relative overflow-hidden rounded-xl border bg-card p-5 shadow-sm transition-colors"
        >
            <div
                aria-hidden
                className={`pointer-events-none absolute -top-10 -right-8 size-28 rounded-full bg-gradient-to-br from-chart-1/10 to-transparent blur-2xl`}
            />
            <div className="flex items-start justify-between">
                <p className="text-sm font-medium text-muted-foreground">{label}</p>
                <span
                    className={`rounded-lg bg-muted p-2 ${accent}`}
                >
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-2 text-2xl font-semibold tabular-nums tracking-tight">
                <AnimatedNumber value={value} format={format} />
            </p>
            {trendLabel && (
                <p className={`mt-1 text-xs font-medium ${trendColor}`}>
                    {trendLabel}
                </p>
            )}
        </motion.div>
    );
}
