import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { AreaChart, Footprints, LogIn, Moon } from 'lucide-react';
import { login } from '@/routes';

function appTitle(): string {
    return import.meta.env.VITE_APP_NAME || 'Lions_Dashboard';
}

export default function Welcome() {
    return (
        <>
            <Head title="Lions · Zapatos" />
            <div className="flex min-h-screen flex-1 flex-col bg-background">
                {/* Hero */}
                <main className="relative flex flex-1 items-center overflow-hidden">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-32 right-0 size-[500px] rounded-full bg-gradient-to-br from-amber-500/20 to-transparent blur-3xl"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute bottom-0 -left-40 size-[420px] rounded-full bg-gradient-to-tr from-chart-2/10 to-transparent blur-3xl"
                    />

                    <div className="mx-auto w-full max-w-5xl px-6 py-16">
                        <motion.div
                            initial={{ opacity: 0, y: 24 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, ease: 'easeOut' }}
                            className="flex flex-col gap-6"
                        >
                            <span className="flex items-center gap-2 self-start rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-500">
                                <Footprints className="size-3.5" /> Zapatos Lions · Tienda oficial
                            </span>
                            <h1 className="max-w-2xl text-5xl font-black tracking-tight md:text-6xl">
                                Tus pasos,{' '}
                                <span className="bg-gradient-to-r from-amber-400 to-amber-600 bg-clip-text text-transparent">
                                    nuestra pasión.
                                </span>
                            </h1>
                            <p className="max-w-xl text-lg text-muted-foreground">
                                Bienvenido al {appTitle()}: ventas, pedidos, inventario y clientes
                                de la zapatería en un solo panel, con datos en tiempo real.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href={login()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                                >
                                    <LogIn className="size-4" /> Entrar al panel
                                </Link>
                                <span className="inline-flex items-center gap-2 rounded-lg border px-5 py-2.5 text-sm text-muted-foreground">
                                    <AreaChart className="size-4" /> Demo con datos simulados
                                </span>
                            </div>
                        </motion.div>

                        <motion.ul
                            initial="hidden"
                            animate="show"
                            variants={{ show: { transition: { staggerChildren: 0.12, delayChildren: 0.3 } } }}
                            className="mt-16 grid gap-4 md:grid-cols-3"
                        >
                            {[
                                { title: 'Ventas en vivo', desc: 'Pedidos que aparecen al instante por WebSocket.', icon: Moon },
                                { title: 'Toda la tienda', desc: 'Ingresos, KPIs, stock y clientes en SQL.', icon: AreaChart },
                                { title: 'Multi-tarea', desc: 'Filtros y acciones sin bloquear tu flujo.', icon: Footprints },
                            ].map((item) => (
                                <motion.li
                                    key={item.title}
                                    variants={{ hidden: { opacity: 0, y: 18 }, show: { opacity: 1, y: 0 } }}
                                    className="rounded-xl border bg-card p-5 shadow-sm"
                                >
                                    <item.icon className="mb-3 size-5 text-amber-500" />
                                    <p className="font-semibold">{item.title}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{item.desc}</p>
                                </motion.li>
                            ))}
                        </motion.ul>
                    </div>
                </main>

                <footer className="border-t py-4 text-center text-xs text-muted-foreground">
                    Lions_Dashboard · demo ficticia construida con Laravel + React
                </footer>
            </div>
        </>
    );
}
