<?php

use App\Jobs\SyncShopifyOrderFromWebhook;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * El endpoint de webhooks debe rechazar firmas inválidas y encolar el
 * procesamiento cuando la firma HMAC-SHA256 de Shopify es correcta.
 *
 * Shopify firma el body EN BRUTO: hay que mandar los bytes exactos del
 * JSON, sin re-encodear (por eso POST con array no sirve en este test).
 */
function shopifyWebhookPost(string $payload, ?string $firma, string $topic = 'orders/update'): TestResponse
{
    /** @var TestCase $test */
    $test = test();

    $server = [
        'CONTENT_TYPE' => 'application/json',
    ];

    if ($firma !== null) {
        $server['HTTP_X_SHOPIFY_HMAC_SHA256'] = $firma;
    }

    if ($topic !== '') {
        $server['HTTP_X_SHOPIFY_TOPIC'] = $topic;
    }

    return $test->call('POST', '/webhooks/shopify', [], [], [], $server, $payload);
}

beforeEach(function () {
    config([
        'shopify.webhook_secret' => 'secreto-de-test',
        'shopify.store' => 'tienda.myshopify.com',
        'shopify.token' => 'shpat_test',
    ]);
});

it('rechaza webhooks con firma inválida', function () {
    Queue::fake();

    $payload = json_encode(['id' => 1, 'admin_graphql_api_id' => 'gid://shopify/Order/998']);
    $firma = base64_encode(hash_hmac('sha256', $payload, 'otro-secreto', true));

    shopifyWebhookPost($payload, $firma)
        ->assertStatus(401);

    Queue::assertNothingPushed();
});

it('encola el sync cuando la firma HMAC es válida', function () {
    Queue::fake();

    $payload = json_encode(['id' => 1234, 'admin_graphql_api_id' => 'gid://shopify/Order/1234']);
    $firma = base64_encode(hash_hmac('sha256', $payload, 'secreto-de-test', true));

    shopifyWebhookPost($payload, $firma)
        ->assertStatus(202);

    Queue::assertPushed(SyncShopifyOrderFromWebhook::class, fn ($job) => $job->gid === 'gid://shopify/Order/1234');
});

it('acepta topics desconocidos sin encolar nada', function () {
    Queue::fake();

    $payload = json_encode(['id' => 567]);
    $firma = base64_encode(hash_hmac('sha256', $payload, 'secreto-de-test', true));

    expect(shopifyWebhookPost($payload, $firma, 'products/update')->status())->toBe(Response::HTTP_ACCEPTED);

    Queue::assertNothingPushed();
});

it('requiere el header de firma', function () {
    Queue::fake();

    $payload = json_encode(['id' => 1, 'admin_graphql_api_id' => 'gid://shopify/Order/2']);

    shopifyWebhookPost($payload, null)->assertStatus(401);

    Queue::assertNothingPushed();
});

it('falla 503 cuando el secret no está configurado', function () {
    Queue::fake();
    config(['shopify.webhook_secret' => '']);

    $payload = json_encode(['id' => 1]);

    shopifyWebhookPost($payload, 'x')->assertStatus(503);

    Queue::assertNothingPushed();
});
