import { motion } from 'framer-motion';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import type { CategoryShare, TopProduct, TopCity } from '@/types/lions';
import { formatCurrency, formatNumber } from '@/lib/format';
import AnimatedNumber from '@/components/lions/animated-number';

const COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

export function TopProducts({ top }: { top: TopProduct[] }) {
    const maxUnits = Math.max(1, ...top.map((t) => t.units));

    return (
        <ul className="space-y-3">
            {top.map((product, index) => (
                <motion.li
                    key={product.product}
                    initial={{ opacity: 0, x: -16 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.05 * index }}
                    className="space-y-1"
                >
                    <div className="flex items-baseline justify-between gap-2">
                        <p className="truncate text-sm font-medium">
                            {index + 1}. {product.product}
                            <span className="ml-2 text-xs text-muted-foreground">{product.category}</span>
                        </p>
                        <p className="shrink-0 text-sm tabular-nums text-muted-foreground">
                            {formatNumber(product.units)} pares
                        </p>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                        <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${Math.round((product.units / maxUnits) * 100)}%` }}
                            transition={{ delay: 0.1 + index * 0.06, duration: 0.8, ease: 'easeOut' }}
                            className="h-full rounded-full bg-chart-1/80"
                        />
                    </div>
                    <p className="text-xs tabular-nums text-muted-foreground">{formatCurrency(product.revenue)}</p>
                </motion.li>
            ))}
        </ul>
    );
}

export function CategoryDonut({ share }: { share: CategoryShare[] }) {
    const total = share.reduce((sum, s) => sum + s.revenue, 0);

    return (
        <div className="relative">
            <ResponsiveContainer width="100%" height={220}>
                <PieChart>
                    <Pie
                        data={share}
                        dataKey="revenue"
                        nameKey="category"
                        innerRadius={62}
                        outerRadius={88}
                        paddingAngle={3}
                        strokeWidth={0}
                        animationDuration={800}
                    >
                        {share.map((entry, index) => (
                            <Cell key={entry.category} fill={COLORS[index % COLORS.length]} />
                        ))}
                    </Pie>
                    <Tooltip
                        formatter={(value: unknown, name: unknown) => [
                            formatCurrency(Number(value) ?? 0),
                            String(name),
                        ]}
                        contentStyle={{
                            backgroundColor: 'var(--popover)',
                            border: '1px solid var(--border)',
                            borderRadius: 8,
                            color: 'var(--popover-foreground)',
                        }}
                    />
                    <Legend
                        layout="vertical"
                        align="right"
                        verticalAlign="middle"
                        iconType="circle"
                        iconSize={8}
                        formatter={(value) => (
                            <span className="text-xs text-muted-foreground">{value}</span>
                        )}
                    />
                </PieChart>
            </ResponsiveContainer>
            <div className="pointer-events-none absolute inset-y-0 left-[28%] flex items-center">
                <div className="translate-x-[-20%] text-center">
                    <p className="-translate-x-1 text-xs text-muted-foreground">Total</p>
                    <p className="text-sm font-semibold tabular-nums">
                        <AnimatedNumber value={total} format={(v) => formatCurrency(v, true)} />
                    </p>
                </div>
            </div>
        </div>
    );
}

export function TopCities({ cities }: { cities: TopCity[] }) {
    const max = Math.max(1, ...cities.map((c) => c.revenue));

    return (
        <ul className="space-y-2.5">
            {cities.map((city, index) => (
                <li key={city.city} className="flex items-center gap-3">
                    <span className="w-28 shrink-0 truncate text-sm text-muted-foreground">{city.city}</span>
                    <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                        <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${Math.round((city.revenue / max) * 100)}%` }}
                            transition={{ delay: 0.05 * index, duration: 0.6, ease: 'easeOut' }}
                            className="h-full rounded-full bg-chart-2/80"
                        />
                    </div>
                    <span className="w-24 shrink-0 text-right text-xs tabular-nums">{formatCurrency(city.revenue, true)}</span>
                </li>
            ))}
        </ul>
    );
}
