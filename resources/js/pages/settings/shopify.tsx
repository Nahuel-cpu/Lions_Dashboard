import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, CircleAlert, ExternalLink, Plug, RefreshCw, Trash2, Unplug } from 'lucide-react';
import ShopifySettingsController from '@/actions/App/Http/Controllers/Settings/ShopifySettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { destroy, sync, vaciar } from '@/routes/settings/shopify';
import { cn } from '@/lib/utils';

type ShopifySettingsProps = {
    storeDomain: string | null;
    clientId: string | null;
    hasSecret: boolean;
    hasTokenCache: boolean;
    tokenExpiresAt: string | null;
    webhookSecret: string | null;
    lastCheckAt: string | null;
    lastCheckOk: boolean | null;
    lastCheckMessage: string | null;
};

type TestResult = { ok: boolean; message: string };

type PageProps = {
    shopify: ShopifySettingsProps;
    testResult: TestResult | null;
};

export default function ShopifySettings({
    shopify,
    testResult,
}: {
    shopify: ShopifySettingsProps;
    testResult: TestResult | null;
}) {
    const conectada = shopify.storeDomain !== null && shopify.storeDomain !== '' && shopify.clientId !== null && shopify.hasSecret;
    const [eliminando, setEliminando] = useState(false);
    const [importando, setImportando] = useState(false);
    const [vaciando, setVaciando] = useState(false);

    return (
        <>
            <Head title="Shopify · Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Shopify"
                    description="Conectá tu tienda real para sincronizar pedidos, clientes y productos"
                />

                {/* estado actual */}
                <div className="rounded-lg border bg-card p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="flex items-center gap-2 text-sm font-medium">
                                {conectada ? (
                                    <>
                                        <Plug className="size-4 text-emerald-500" />
                                        Tienda conectada: <code>{shopify.storeDomain}</code>
                                    </>
                                ) : (
                                    <>
                                        <Unplug className="size-4 text-muted-foreground" />
                                        Sin tienda conectada
                                    </>
                                )}
                            </p>
                            {shopify.lastCheckAt !== null && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Última verificación: {new Date(shopify.lastCheckAt).toLocaleString('es-AR')}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col items-end gap-2">
                            <QuietBadge estado={shopify.lastCheckOk} />
                            {conectada && shopify.tokenExpiresAt !== null && (
                                <span className="text-[11px] text-muted-foreground">
                                    token vigente hasta {new Date(shopify.tokenExpiresAt).toLocaleString('es-AR')}
                                </span>
                            )}
                        </div>
                    </div>

                    {shopify.lastCheckMessage !== null && shopify.lastCheckMessage !== '' && (
                        <p
                            className={cn(
                                'mt-2 text-xs',
                                shopify.lastCheckOk === true ? 'text-emerald-500' : shopify.lastCheckOk === false ? 'text-rose-500' : 'text-muted-foreground',
                            )}
                        >
                            {shopify.lastCheckMessage}
                        </p>
                    )}
                </div>

                {testResult !== null && (
                    <div
                        className={cn(
                            'flex items-start gap-2 rounded-lg border p-3 text-sm',
                            testResult.ok ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border-rose-500/40 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                        )}
                    >
                        {testResult.ok ? <CheckCircle2 className="mt-0.5 size-4 shrink-0" /> : <CircleAlert className="mt-0.5 size-4 shrink-0" />}
                        <span>{testResult.message}</span>
                    </div>
                )}

                {/* formulario de credenciales */}
                <Form
                    {...ShopifySettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="store_domain">Dominio de la tienda</Label>
                                <Input
                                    id="store_domain"
                                    name="store_domain"
                                    defaultValue={shopify.storeDomain ?? ''}
                                    placeholder="mi-tienda.myshopify.com"
                                    autoComplete="off"
                                    required
                                />
                                <InputError className="mt-2" message={errors.store_domain} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="client_id">Client ID de la app (Dev Dashboard)</Label>
                                <Input
                                    id="client_id"
                                    name="client_id"
                                    defaultValue={shopify.clientId ?? ''}
                                    placeholder="1a853ca58ea9fecb5f72f0fe777cad2e"
                                    autoComplete="off"
                                    required
                                />
                                <InputError className="mt-2" message={errors.client_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="client_secret">Secreto del cliente</Label>
                                <Input
                                    id="client_secret"
                                    name="client_secret"
                                    type="password"
                                    placeholder={shopify.hasSecret ? '••••••••• (guardado — escribilo para reemplazarlo)' : ''}
                                    autoComplete="new-password"
                                    required
                                />
                                <InputError className="mt-2" message={errors.client_secret} />
                                <p className="text-xs text-muted-foreground">
                                    Aparecen en dev.shopify.com → tu app → <b>Configuración</b>. Con estos datos el
                                    dashboard obtiene el token solo, sin copiar nada a mano (renueva cada 24 h).
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="webhook_secret">Secreto del webhook (opcional)</Label>
                                <Input
                                    id="webhook_secret"
                                    name="webhook_secret"
                                    defaultValue={shopify.webhookSecret ?? ''}
                                    placeholder="shpss_..."
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.webhook_secret} />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button disabled={processing}>Guardar credenciales</Button>

                                {!shopify.hasSecret && (
                                    <span className="text-xs text-muted-foreground">
                                        Guardá las credenciales primero: después podés probar la conexión.
                                    </span>
                                )}
                            </div>
                        </>
                    )}
                </Form>

                {/* probar conexión / desconectar */}
                <div className="flex flex-wrap items-center gap-3">
                    <Form
                        {...ShopifySettingsController.test.form()}
                        options={{ preserveScroll: true }}
                        className="inline"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Button variant="outline" disabled={processing || !conectada} className="gap-1.5">
                                    <ExternalLink className="size-3.5" /> Probar conexión
                                </Button>
                                <InputError className="mt-2" message={errors.store_domain} />
                            </>
                        )}
                    </Form>

                    {conectada && (
                        <Button
                            variant="ghost"
                            disabled={eliminando}
                            className="gap-1.5 text-muted-foreground hover:text-foreground"
                            onClick={() => {
                                if (!confirm('¿Desconectar la tienda Shopify? Se borra la credencial guardada (los datos importados se conservan).')) {
                                    return;
                                }
                                router.delete(destroy.url(), {
                                    preserveScroll: true,
                                    onStart: () => setEliminando(true),
                                    onFinish: () => setEliminando(false),
                                });
                            }}
                        >
                            <Unplug className="size-3.5" /> Desconectar
                        </Button>
                    )}
                </div>

                {/* acciones de datos (sin terminal) */}
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-sm font-medium">Reemplazar datos</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Sin terminal: acá están los mismos pasos de <code className="rounded bg-muted px-1">php artisan shopify:import --full</code>.
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            disabled={vaciando}
                            className="gap-1.5"
                            onClick={() => {
                                if (!confirm('¿Vaciar los datos demo? Se borra todo lo que no venga de Shopify (pedidos, clientes, productos fabricados por el demo) y se conserva lo importado de tu tienda.')) {
                                    return;
                                }
                                router.post(vaciar.url(), {
                                    preserveScroll: true,
                                    onStart: () => setVaciando(true),
                                    onFinish: () => setVaciando(false),
                                });
                            }}
                        >
                            <Trash2 className="size-3.5" /> {vaciando ? 'Borrando…' : 'Vaciar datos demo'}
                        </Button>

                        <Button
                            disabled={!conectada || importando}
                            className="gap-1.5"
                            onClick={() => {
                                if (!confirm('Reemplazo total: borra TODO (demo y datos previos) y reimporta productos, clientes y pedidos desde tu tienda Shopify. Puede tardar según el tamaño del catálogo. ¿Continuar?')) {
                                    return;
                                }
                                router.post(sync.url(), {
                                    preserveScroll: true,
                                    onStart: () => setImportando(true),
                                    onFinish: () => setImportando(false),
                                });
                            }}
                        >
                            <RefreshCw className={cn('size-3.5', importando && 'animate-spin')} /> {importando ? 'Importando… puede tardar' : 'Reemplazar todo con Shopify'}
                        </Button>

                        {!conectada && (
                            <span className="text-xs text-muted-foreground">Guardá las credenciales para habilitar el reemplazo total.</span>
                        )}
                    </div>
                </div>

                {/* pasos previos */}
                <div className="rounded-lg border bg-muted/40 p-4 text-sm">
                    <p className="font-medium">Antes de guardar, en el Dev Dashboard (dev.shopify.com):</p>
                    <ol className="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground">
                        <li>
                            En tu app → <b>Versiones</b>: creá una versión con los scopes{' '}
                            <code className="rounded bg-muted px-1">read_orders</code>,{' '}
                            <code className="rounded bg-muted px-1">read_customers</code>,{' '}
                            <code className="rounded bg-muted px-1">read_products</code>,{' '}
                            <code className="rounded bg-muted px-1">read_inventory</code> y publicala.
                        </li>
                        <li>
                            Instalá la app en tu tienda (botón <b>Instalar</b> cuando la publicás, o en:{' '}
                            <b>Dev stores</b> → tu tienda).
                        </li>
                        <li>
                            En la app → <b>Configuración</b>: copiá el <b>Client ID</b> y el <b>Secreto del cliente</b>{' '}
                            y pegalos acá.
                        </li>
                    </ol>
                </div>

                {/* pasos siguientes */}
                <div className="rounded-lg border bg-muted/40 p-4 text-sm">
                    <p className="font-medium">Después de conectar la tienda:</p>
                    <ol className="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground">
                        <li>
                            Importá el catálogo y los pedidos: <code className="rounded bg-muted px-1">php artisan shopify:import --full</code>{' '}
                            (borra los datos demo antes de importar).
                        </li>
                        <li>
                            En Shopify → Settings → Notifications → Create webhook: topic <code className="rounded bg-muted px-1">Order update</code>{' '}
                            contra <code className="rounded bg-muted px-1">{'{URL del dashboard}'}/webhooks/shopify</code>,
                            con el secreto de arriba (formato shpss_).
                        </li>
                        <li>Los cambios de estado de pedidos llegarán en vivo al feed de actividad.</li>
                    </ol>
                </div>
            </div>
        </>
    );
}

/** Badge dev al último resultado del check guardado. */
function QuietBadge({ estado }: { estado: boolean | null }) {
    if (estado === true) {
        return <Badge variant="outline" className="border-emerald-500/40 text-emerald-500">verificada</Badge>;
    }
    if (estado === false) {
        return <Badge variant="outline" className="border-rose-500/40 text-rose-500">con error</Badge>;
    }

    return <Badge variant="outline" className="text-muted-foreground">sin verificar</Badge>;
}

ShopifySettings.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: '',
        },
        {
            title: 'Shopify',
            href: '',
        },
    ],
};
