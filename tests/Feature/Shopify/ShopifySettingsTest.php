<?php

use App\Models\ShopifySetting;
use App\Models\User;
use App\Services\Shopify\Credentials;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Credentials::forget();
    config(['shopify.client_id' => '', 'shopify.client_secret' => '', 'shopify.token' => '']);
});

afterEach(function () {
    Credentials::forget();
});

it('muestra la página sin tienda conectada', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/settings/shopify')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/shopify')
            ->has('shopify')
            ->where('shopify.hasSecret', false));
});

it('guarda credenciales del dev dashboard y no devuelve secretos al frontend', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->post('/settings/shopify', [
            'store_domain' => 'MI-TIENDA.myshopify.com',
            'client_id' => '1a853ca58ea9fecb5f72f0fe777cad2e',
            'client_secret' => 'secretito123',
            'webhook_secret' => 'shpss_xyz',
        ])
        ->assertRedirect();

    $row = ShopifySetting::query()->firstOrFail();
    expect($row->store_domain)->toBe('mi-tienda.myshopify.com')
        ->and($row->client_id)->toBe('1a853ca58ea9fecb5f72f0fe777cad2e')

        ->and($row->getRawOriginal('client_secret'))->not->toBe('secretito123')
        ->and($row->webhook_secret)->toBe('shpss_xyz');

    $this->actingAs($user)
        ->get('/settings/shopify')
        ->assertInertia(fn ($page) => $page
            ->where('shopify.hasSecret', true)
            ->missing('shopify.client_secret')
            ->missing('shopify.access_token'));
});

it('valida campos faltantes sin guardar nada', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->post('/settings/shopify', [
            'store_domain' => 'tienda.myshopify.com',
        ])
        ->assertSessionHasErrors(['client_id', 'client_secret']);

    expect(ShopifySetting::query()->count())->toBe(0);
});

it('prueba la conexión con client credentials y cachea el token', function () {
    Http::fake([
        '*oauth/access_token' => Http::response([
            'access_token' => 'tok_de_acceso_123',
            'scope' => 'read_orders,read_products',
            'expires_in' => 86399,
        ]),
        '*graphql.json' => Http::response([
            'data' => ['shop' => ['name' => 'Mi Tienda Real', 'myshopifyDomain' => 'tienda.myshopify.com', 'primaryDomain' => ['url' => 'https://tienda.myshopify.com']]],
            'extensions' => ['cost' => ['throttleStatus' => ['currentlyAvailable' => 95]]],
        ]),
    ]);

    $user = User::factory()->admin()->create();
    $this->actingAs($user)
        ->post('/settings/shopify', [
            'store_domain' => 'tienda.myshopify.com',
            'client_id' => 'cli123',
            'client_secret' => 'sec456',
        ]);

    $this->actingAs($user)->post('/settings/shopify/probar')->assertRedirect();

    $row = ShopifySetting::query()->firstOrFail();
    expect($row->last_check_ok)->toBeTrue()
        ->and($row->last_check_message)->toContain('Mi Tienda Real')

        ->and($row->access_token)->toBe('tok_de_acceso_123')
        ->and($row->getRawOriginal('access_token'))->not->toBe('tok_de_acceso_123')
        ->and($row->token_expires_at)->not->toBeNull();

    Http::assertSent(fn ($req) => str_contains((string) $req->url(), 'oauth/access_token')
        && $req['grant_type'] === 'client_credentials'
        && $req['client_id'] === 'cli123'
        && $req['client_secret'] === 'sec456');
});

it('renueva el token cuando el cacheado está vencido', function () {
    Http::fake([
        '*oauth/access_token' => Http::response(['access_token' => 'tok_nuevo', 'expires_in' => 86399]),
        '*graphql.json' => Http::response([
            'data' => ['shop' => ['name' => 'T']],
            'extensions' => ['cost' => ['throttleStatus' => ['currentlyAvailable' => 95]]],
        ]),
    ]);

    ShopifySetting::create([
        'store_domain' => 'tienda.myshopify.com',
        'client_id' => 'cli123',
        'client_secret' => 'sec456',
        'access_token' => 'tok_viejo',
        'token_expires_at' => now()->subHour(),
    ]);
    Credentials::forget();

    $user = User::factory()->admin()->create();
    $this->actingAs($user)->post('/settings/shopify/probar')->assertRedirect();

    expect(ShopifySetting::query()->firstOrFail()->access_token)->toBe('tok_nuevo');
});

it('reutiliza el token vigente sin pedir otro', function () {
    Http::fake([
        '*oauth/access_token' => Http::response(['access_token' => 'tok_rechazado_si_se_pidiera', 'expires_in' => 86399]),
        '*graphql.json' => Http::response([
            'data' => ['shop' => ['name' => 'T']],
            'extensions' => ['cost' => ['throttleStatus' => ['currentlyAvailable' => 95]]],
        ]),
    ]);

    $row = ShopifySetting::create([
        'store_domain' => 'tienda.myshopify.com',
        'client_id' => 'cli123',
        'client_secret' => 'sec456',
        'access_token' => 'tok_cacheado',
        'token_expires_at' => now()->addHours(20),
    ]);
    $row->save();
    Credentials::forget();

    $user = User::factory()->admin()->create();
    $this->actingAs($user)->post('/settings/shopify/probar')->assertRedirect();

    $row->refresh();
    expect($row->access_token)->toBe('tok_cacheado')
        ->and(ShopifySetting::query()->firstOrFail()->access_token)->toBe('tok_cacheado');
});

it('propaga el error cuando reparten credenciales que no corresponden', function () {
    Http::fake([
        '*oauth/access_token' => Http::response([
            'error' => 'oauth_error',
            'error_description' => 'Oauth error shop_not_permitted: Client credentials cannot be performed on this shop.',
        ], 400),
    ]);

    $user = User::factory()->admin()->create();
    $this->actingAs($user)
        ->post('/settings/shopify', [
            'store_domain' => 'tienda-ajena.myshopify.com',
            'client_id' => 'cli123',
            'client_secret' => 'sec456',
        ]);

    $this->actingAs($user)->post('/settings/shopify/probar')->assertRedirect();

    $row = ShopifySetting::query()->firstOrFail();
    expect($row->last_check_ok)->toBeFalse()
        ->and($row->last_check_message)->toContain('shop_not_permitted');
});

it('registra el fallo de conexión de la API sin tirar error 500', function () {
    Http::fake([
        '*oauth/access_token' => Http::response(['access_token' => 'tok_x', 'expires_in' => 86399]),
        '*graphql.json' => Http::response([
            'data' => null,
            'errors' => [['message' => 'invalid token', 'extensions' => ['code' => 'ACCESS_DENIED']]],
        ]),
    ]);

    $user = User::factory()->admin()->create();
    $this->actingAs($user)
        ->post('/settings/shopify', [
            'store_domain' => 'tienda.myshopify.com',
            'client_id' => 'cli123',
            'client_secret' => 'sec456',
        ]);

    $this->actingAs($user)->post('/settings/shopify/probar')->assertRedirect();

    $row = ShopifySetting::query()->firstOrFail();
    expect($row->last_check_ok)->toBeFalse()
        ->and($row->last_check_message)->not->toBe('');
});

it('desconecta y borra la credencial', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user)->post('/settings/shopify', [
        'store_domain' => 'tienda.myshopify.com',
        'client_id' => 'cli123',
        'client_secret' => 'sec456',
    ]);

    $this->actingAs($user)->delete('/settings/shopify')->assertRedirect();

    expect(ShopifySetting::query()->count())->toBe(0);
    expect(Credentials::configured())->toBeFalse();
});
