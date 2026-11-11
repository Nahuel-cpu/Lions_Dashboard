import {
    MapContainer,
    CircleMarker,
    TileLayer,
    Tooltip as LeafletTooltip,
} from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { motion } from 'framer-motion';
import type { MapPoint } from '@/types/lions';
import { formatCurrency } from '@/lib/format';
import { useAppearance } from '@/hooks/use-appearance';

export default function SalesMap({ points }: { points: MapPoint[] }) {
    const max = Math.max(1, ...points.map((p) => p.revenue));
    const { resolvedAppearance } = useAppearance();

    const visible = points.filter((p) => p.lat !== 0 || p.lng !== 0);
    const sinUbicacion = points.length - visible.length;

    const tilesUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    const dark = resolvedAppearance === 'dark';

    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.5 }}
            className="relative h-[380px] overflow-hidden rounded-xl border"
        >
            <MapContainer
                center={[-36.5, -61.5]}
                zoom={4.4}
                minZoom={3.5}
                zoomControl={false}
                scrollWheelZoom={false}
                className="h-full w-full"
                style={{ background: 'var(--card)' }}
            >
                <TileLayer
                    key={tilesUrl}
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url={tilesUrl}
                    className={dark ? 'map-tiles-dark' : undefined}
                />
                {visible.map((point) => {
                    const ratio = point.revenue / max;
                    return (
                        <CircleMarker
                            key={point.city}
                            center={[point.lat, point.lng]}
                            radius={14 + ratio * 26}
                            pathOptions={{
                                color: 'var(--chart-1)',
                                weight: 1.4,
                                fillColor: 'var(--chart-1)',
                                fillOpacity: 0.28 + ratio * 0.32,
                            }}
                            eventHandlers={{
                                mouseover: (event) =>
                                    event.target.setStyle({
                                        fillOpacity: 0.75,
                                    }),
                                mouseout: (event) =>
                                    event.target.setStyle({
                                        fillOpacity: 0.28 + ratio * 0.32,
                                    }),
                            }}
                        >
                            <LeafletTooltip
                                direction="top"
                                offset={[0, -8]}
                                opacity={1}
                            >
                                <b>{point.city}</b>
                                <br />
                                {point.orders} pedidos completados
                                <br />
                                {formatCurrency(point.revenue)}
                            </LeafletTooltip>
                        </CircleMarker>
                    );
                })}
            </MapContainer>
            {sinUbicacion > 0 && (
                <span className="absolute top-3 right-3 rounded-md border bg-background/80 px-2 py-1 text-[11px] text-muted-foreground backdrop-blur">
                    {sinUbicacion} ciudad{sinUbicacion === 1 ? '' : 'es'} sin
                    ubicación en el mapa
                </span>
            )}
            <span
                aria-hidden
                className="pointer-events-none absolute inset-0 rounded-xl ring-1 ring-black/5 ring-inset dark:ring-white/10"
            />
        </motion.div>
    );
}
