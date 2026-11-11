<?php

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['shopify.client_id' => '', 'shopify.client_secret' => '', 'shopify.token' => '']);
});

it('vaciar demo borra solo lo sin shopify_id y deja lo real', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $demoCustomer = Customer::create(['name' => 'Demo', 'email' => 'demo@x.com', 'city' => 'BA', 'region' => 'BA', 'lat' => 0, 'lng' => 0]);
    Order::create(['customer_id' => $demoCustomer->id, 'status' => 'completado', 'total' => 1000, 'ordered_at' => now()]);

    $realCustomer = Customer::create(['name' => 'Real', 'email' => 'real@x.com', 'city' => 'BA', 'region' => 'BA', 'lat' => 0, 'lng' => 0, 'shopify_id' => 'gid://shopify/Customer/2']);
    Order::create(['customer_id' => $realCustomer->id, 'status' => 'completado', 'total' => 5000, 'ordered_at' => now(), 'shopify_id' => 'gid://shopify/Order/1']);
    Activity::create(['type' => 'venta', 'title' => 't', 'description' => 'd', 'performed_at' => now()]);

    $this->post('/settings/shopify/vaciar-demo')->assertRedirect();

    expect(Order::query()->whereNull('shopify_id')->count())->toBe(0)
        ->and(Order::query()->whereNotNull('shopify_id')->count())->toBe(1)
        ->and(Customer::query()->whereNull('shopify_id')->count())->toBe(0)
        ->and(Customer::query()->whereNotNull('shopify_id')->count())->toBe(1)
        ->and(Activity::query()->count())->toBe(0);
});

it('sincronizar sin credenciales da error amigable', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = $this->post('/settings/shopify/sincronizar')->assertRedirect();

    $toast = $response->getSession()->get('inertia.flash_data')['toast'] ?? null;

    expect($toast['type'] ?? null)->toBe('error')
        ->and($toast['message'] ?? '')->toContain('Shopify');
});

it('sincronizar corre wipe + import completo contra shopify fake', function () {
    Http::fake([
        '*oauth/access_token' => Http::response(['access_token' => 'tok_38', 'expires_in' => 86399]),
        '*graphql.json' => Http::sequence()
            ->push(['data' => ['products' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false]]]])
            ->push(['data' => ['customers' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false]]]])
            ->push(['data' => ['orders' => ['edges' => [], 'pageInfo' => ['hasNextPage' => false]]]]),
    ]);

    $user = User::factory()->admin()->create();
    $this->actingAs($user)
        ->post('/settings/shopify', ['store_domain' => 't.myshopify.com', 'client_id' => 'cli', 'client_secret' => 'sec']);

    $demoCustomer = Customer::create(['name' => 'Demo', 'email' => 'demo@x.com', 'city' => 'BA', 'region' => 'BA', 'lat' => 0, 'lng' => 0]);

    $response = $this->post(route('settings.shopify.sync'))->assertRedirect();

    $toast = $response->getSession()->get('inertia.flash_data')['toast'] ?? null;

    expect(Customer::query()->find($demoCustomer->id))->toBeNull()
        ->and($toast['type'] ?? null)->toBe('success')
        ->and($toast['message'] ?? '')->toContain('Importación completa')
        ->and(Customer::query()->where('email', 'demo@x.com')->exists())->toBeFalse();
});
