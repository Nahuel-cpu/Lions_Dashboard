/** $ 12.345.678 */
export const formatCurrency = (value: number, compact = false): string =>
    new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0,
        notation:
            compact && Math.abs(value) >= 1_000_000 ? 'compact' : 'standard',
    }).format(value);

/** 12.345.678 */
export const formatNumber = (value: number, compact = false): string =>
    new Intl.NumberFormat('es-AR', {
        maximumFractionDigits: 0,
        notation:
            compact && Math.abs(value) >= 100_000 ? 'compact' : 'standard',
    }).format(value);

/** +12,3% */
export const formatPercent = (value: number, digits = 1): string =>
    `${value > 0 ? '+' : ''}${value.toLocaleString('es-AR', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    })}%`;

/** '2026-11-07' → '7 nov' / 'nov 26' según la granularidad */
export const formatBucket = (bucket: string, granularidad: string): string => {
    if (granularidad === 'mes') {
        const [year, month] = bucket.split('-');
        const date = new Date(Number(year), Number(month) - 1, 1);
        return `${date.toLocaleDateString('es-AR', { month: 'short' })} ${year.slice(2)}`;
    }

    const date = new Date(`${bucket}T00:00:00`);
    return date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });
};

/** ISO → '7 nov 14:35' */
export const formatDateTime = (iso: string): string => {
    const date = new Date(iso);
    const dateStr = date.toLocaleDateString('es-AR', {
        day: '2-digit',
        month: 'short',
    });
    const timeStr = date.toLocaleTimeString('es-AR', {
        hour: '2-digit',
        minute: '2-digit',
    });
    return `${dateStr} ${timeStr}`;
};
