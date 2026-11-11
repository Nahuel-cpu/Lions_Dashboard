import { motion } from 'framer-motion';
import { PackageOpen } from 'lucide-react';
import type { StockAlert } from '@/types/lions';

export default function StockAlerts({ alerts }: { alerts: StockAlert[] }) {
    return (
        <ul className="space-y-2">
            {alerts.length === 0 && (
                <li className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <PackageOpen className="size-4" /> Sin alertas de stock en
                    este momento.
                </li>
            )}
            {alerts.map((alert, index) => (
                <motion.li
                    key={alert.id}
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.04 * index }}
                    className="flex items-center justify-between gap-3 rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2"
                >
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {alert.product}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Talle {alert.size} · {alert.sku} · límite{' '}
                            {alert.threshold}
                        </p>
                    </div>
                    <span
                        className={
                            'shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums ' +
                            (alert.stock === 0
                                ? 'bg-destructive/15 text-rose-500'
                                : 'bg-amber-500/15 text-amber-500')
                        }
                    >
                        {alert.stock}u
                    </span>
                </motion.li>
            ))}
        </ul>
    );
}
