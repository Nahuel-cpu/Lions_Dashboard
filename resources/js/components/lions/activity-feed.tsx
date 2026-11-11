import { useEffect, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Bell, Moon, Bike, ShoppingCart, Sun, UserRound } from 'lucide-react';
import type { ActivityItem } from '@/types/lions';
import { cn } from '@/lib/utils';
import { formatDateTime } from '@/lib/format';

const ICONS: Record<string, typeof Bell> = {
    pedido: ShoppingCart,
    venta: Moon,
    stock: Bike,
    cliente: UserRound,
    envio: Bell,
    baja: Sun,
};

const COLORS: Record<string, string> = {
    pedido: 'text-chart-2 bg-chart-2/10',
    venta: 'text-emerald-500 bg-emerald-500/10',
    stock: 'text-chart-4 bg-chart-4/10',
    cliente: 'text-chart-5 bg-chart-5/10',
    envio: 'text-chart-3 bg-chart-3/10',
    baja: 'text-rose-500 bg-rose-500/10',
};

export default function ActivityFeed({ initial }: { initial: ActivityItem[] }) {
    const [items, setItems] = useState<ActivityItem[]>(initial);
    const [pulse, setPulse] = useState(0);

    useEffect(() => {
        setItems(initial);
    }, [initial]);

    useEffect(() => {
        const handler = (event: Event) => {
            const detail = (event as CustomEvent<ActivityItem>).detail;
            setItems((current) => [detail, ...current].slice(0, 20));
            setPulse((p) => p + 1);
        };
        window.addEventListener('lions:actividad', handler);

        return () => {
            window.removeEventListener('lions:actividad', handler);
        };
    }, []);

    return (
        <div className="relative h-full">
            <AnimatePresence initial={false}>
                {items.map((item, index) => {
                    const Icon = ICONS[item.type] ?? Bell;
                    const color = COLORS[item.type] ?? COLORS.envio;

                    return (
                        <motion.div
                            key={item.id}
                            layout
                            initial={{ opacity: 0, x: 24, height: 0 }}
                            animate={{ opacity: 1, x: 0, height: 'auto' }}
                            exit={{ opacity: 0, x: -24, height: 0 }}
                            transition={{
                                type: 'spring',
                                stiffness: 320,
                                damping: 28,
                            }}
                            className={cn(
                                'flex gap-3 overflow-hidden py-2.5',
                                index > 0 && 'border-t border-border/60',
                            )}
                            style={
                                index > 5
                                    ? { opacity: 1 - (index - 5) * 0.15 }
                                    : undefined
                            }
                        >
                            <span
                                className={cn(
                                    'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg',
                                    color,
                                )}
                            >
                                <Icon className="size-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {item.title}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {item.description}
                                </p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground/70">
                                    {formatDateTime(item.at)}
                                </p>
                            </div>
                        </motion.div>
                    );
                })}
            </AnimatePresence>

            <span hidden>{pulse}</span>
        </div>
    );
}

export function pushActivity(item: ActivityItem): void {
    window.dispatchEvent(new CustomEvent('lions:actividad', { detail: item }));
}
