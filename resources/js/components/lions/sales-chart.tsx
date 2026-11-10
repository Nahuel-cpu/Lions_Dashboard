import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis, Bar, BarChart } from 'recharts';
import type { SeriePoint } from '@/types/lions';
import { formatBucket, formatCurrency, formatNumber } from '@/lib/format';

export function SalesAreaChart({
    data,
    granularidad,
}: {
    data: SeriePoint[];
    granularidad: string;
}) {
    const chartData = data.map((point) => ({
        ...point,
        label: formatBucket(point.bucket, granularidad),
    }));

    return (
        <ResponsiveContainer width="100%" height={280}>
            <AreaChart data={chartData} margin={{ top: 12, right: 8, left: 4, bottom: 0 }}>
                <defs>
                    <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.45} />
                        <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0.02} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    minTickGap={28}
                    tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    width={64}
                    tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    tickFormatter={(v: number) => formatCurrency(v, true)}
                />
                <Tooltip
                    formatter={((value: unknown, name: unknown) =>
                        name === 'revenue' ? formatCurrency(Number(value) ?? 0) : formatNumber(Number(value) ?? 0)
                    )}
                    labelFormatter={(label) => `Período: ${String(label)}`}
                    contentStyle={{
                        backgroundColor: 'var(--popover)',
                        border: '1px solid var(--border)',
                        borderRadius: 8,
                        color: 'var(--popover-foreground)',
                    }}
                />
                <Area
                    type="monotone"
                    dataKey="revenue"
                    stroke="var(--chart-1)"
                    strokeWidth={2.3}
                    fill="url(#revenueFill)"
                    animationDuration={700}
                />
                <Area
                    type="monotone"
                    dataKey="orders"
                    stroke="var(--chart-2)"
                    strokeWidth={1.4}
                    fillOpacity={0}
                    animationDuration={900}
                />
            </AreaChart>
        </ResponsiveContainer>
    );
}

export function OrdersBarChart({ data, granularidad }: { data: SeriePoint[]; granularidad: string }) {
    const chartData = data.map((point) => ({
        ...point,
        label: formatBucket(point.bucket, granularidad),
    }));

    return (
        <ResponsiveContainer width="100%" height={220}>
            <BarChart data={chartData} margin={{ top: 8, right: 8, left: 4, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    minTickGap={32}
                    tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    width={16}
                    tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                />
                <Tooltip
                    formatter={(value: unknown) => formatNumber(Number(value), false)}
                    contentStyle={{
                        backgroundColor: 'var(--popover)',
                        border: '1px solid var(--border)',
                        borderRadius: 8,
                        color: 'var(--popover-foreground)',
                    }}
                />
                <Bar dataKey="orders" radius={[3, 3, 0, 0]} fill="var(--chart-2)" animationDuration={700} />
            </BarChart>
        </ResponsiveContainer>
    );
}
