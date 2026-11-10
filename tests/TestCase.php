<?php

namespace Tests;

use App\Services\Shopify\Credentials;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // La caché estática de credenciales sobrevive al RefreshDatabase
        // (es memoria del proceso, no de la DB): sin resetearla, las
        // credenciales de un test se filtraban a los tests siguientes.
        Credentials::forget();

        parent::tearDown();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
