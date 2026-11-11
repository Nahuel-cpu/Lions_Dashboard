<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopifySetting;
use App\Services\Shopify\Credentials;
use App\Services\Shopify\DemoWipe;
use App\Services\Shopify\Importer;
use App\Services\Shopify\ShopifyConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Settings → Shopify: guarda credenciales de la tienda, muestra el estado
 * y permite probar la conexión contra la Admin API real.
 */
class ShopifySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $row = ShopifySetting::current();

        return Inertia::render('settings/shopify', [
            'shopify' => [
                'storeDomain' => $row?->store_domain,
                'clientId' => $row?->client_id,
                'hasSecret' => $row !== null && $row->client_secret !== null && $row->client_secret !== '',
                'hasTokenCache' => $row !== null && $row->access_token !== null && $row->access_token !== '',
                'tokenExpiresAt' => $row?->token_expires_at?->toIso8601String(),
                'webhookSecret' => $row?->webhook_secret,
                'lastCheckAt' => $row?->last_check_at?->toIso8601String(),
                'lastCheckOk' => $row?->last_check_ok,
                'lastCheckMessage' => $row?->last_check_message,
            ],
            'testResult' => session('shopify_test'),
        ]);
    }

    /**
     * Guarda (o actualiza) las credenciales de la tienda.
     * Cambiar el client_id/secreto invalida el token cacheado (se estima
     * emitido para otras credenciales): se borra y se pedirá uno nuevo.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9\-\.]*\.[a-z]{2,}$/i'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'client_id.required' => 'Escribí el Client ID de la app del Dev Dashboard.',
            'client_secret.required' => 'Escribí el Secreto del cliente de la app del Dev Dashboard.',
        ], [
            'store_domain' => 'dominio de la tienda',
            'client_id' => 'Client ID',
            'client_secret' => 'secreto del cliente',
            'webhook_secret' => 'secreto de webhooks',
        ]);

        $domain = $this->normalizeDomain($validated['store_domain']);

        $row = ShopifySetting::query()->firstOrNew();

        $credentialsChanged = $row->client_id !== $validated['client_id']
            || $row->store_domain !== $domain;

        $row->store_domain = $domain;
        $row->client_id = mb_strtolower(trim($validated['client_id']));
        $row->client_secret = $validated['client_secret'];

        if ($credentialsChanged) {
            $row->access_token = null;
            $row->token_expires_at = null;
        }

        $row->webhook_secret = $validated['webhook_secret'] ?? '';
        $row->save();

        Credentials::forget();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Credenciales de Shopify guardadas.']);

        return to_route('settings.shopify');
    }

    /**
     * Verifica la conexión contra el endpoint real y registra resultado.
     */
    public function test(): RedirectResponse
    {
        $at = now();

        try {
            $data = (new ShopifyConnector)->shopInfo();
            $name = (string) ($data['shop']['name'] ?? '?');
            $message = 'Conexión OK: tienda "'.$name.'"';
            $ok = true;
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Falló la conexión: '.$e->getMessage();
            $ok = false;
        }

        $row = ShopifySetting::query()->firstOrNew();
        $row->last_check_at = $at;
        $row->last_check_ok = $ok;
        $row->last_check_message = Str::limit($message, 400);
        $row->save();

        Credentials::forget();

        Inertia::flash('shopify_test', ['ok' => $ok, 'message' => $message]);

        return to_route('settings.shopify');
    }

    /** Desconecta: borra credenciales e historial de checks. */
    public function destroy(): RedirectResponse
    {
        ShopifySetting::query()->delete();
        Credentials::forget();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tienda Shopify desconectada.']);

        return to_route('settings.shopify');
    }

    /**
     * IMPORTAR AHORA: reemplazo total (wipe + import completo) — el mismo
     * trabajo de `shopify:import --full` pero desde la UI. Sincrónico:
     * puede tardar el tiempo que tarde el catálogo de la tienda real.
     */
    public function sync(): RedirectResponse
    {
        set_time_limit(0);

        try {
            $importer = new Importer(new ShopifyConnector);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('settings.shopify');
        }

        DemoWipe::everything();

        try {
            $pages = $importer->fullImport()['pages'];
        } catch (Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'La importación falló: '.$e->getMessage().' — Podés reintentar, es idempotente.',
            ]);

            return to_route('settings.shopify');
        }

        $resumen = Product::query()->whereNotNull('shopify_id')->count().' productos · '
            .Customer::query()->whereNotNull('shopify_id')->count().' clientes · '
            .Order::query()->whereNotNull('shopify_id')->count().' pedidos';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Importación completa ('.$pages['productos'].'+'.$pages['clientes'].'+'.$pages['pedidos'].' páginas): '.$resumen,
        ]);

        return to_route('settings.shopify');
    }

    /**
     * VACIAR DEMO: borra sólo el contenido local sin origen Shopify y deja
     * lo importado de la tienda real.
     */
    public function wipeDemo(): RedirectResponse
    {
        $counts = DemoWipe::demoOnly();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos demo borrados: '.(DemoWipe::humanize($counts) ?? 'nada que borrar').'.']);

        return to_route('settings.shopify');
    }

    private function normalizeDomain(string $raw): string
    {
        $domain = trim($raw);

        return strtolower((string) preg_replace('!^https?://!', '', $domain));
    }
}
