<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sonda de errores: renderiza TODAS las páginas del panel con sesión
 * autenticada y reporta el estado HTTP de cada una.
 */
class PagesProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function withAdmin(): User
    {
        $user = User::factory()->create([
            'name' => 'Admin Probe',
            'email' => 'probe@lions.com',
            'password' => bcrypt('lions1234'),
            'is_admin' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_dashboard(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/dashboard');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/dashboard'));
    }

    public function test_pedidos(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/pedidos');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/pedidos'));
    }

    public function test_pedidos_marcados(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/pedidos?estado=pendiente&q=Buenos');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/pedidos?estado=pendiente&q=Buenos'));
    }

    public function test_productos(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/productos');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/productos'));
    }

    public function test_clientes(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/clientes');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/clientes'));
    }

    public function test_clientes_recientes(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/clientes?tipo=recurrentes&orden=pedidos');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/clientes?tipo=recurrentes'));
    }

    public function test_inventario(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/inventario');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/inventario'));
    }

    public function test_inventario_filtrado(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/inventario?solo_low=1&orden=stock_desc');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/inventario?solo_low=1'));
    }

    public function test_settings_profile(): void
    {
        $this->actingAs($this->withAdmin());
        $response = $this->get('/settings/profile');
        $this->assertSame(200, $response->status(), $this->failureDescription($response, '/settings/profile'));
    }

    private function failureDescription($response, string $url): string
    {
        $exception = $response->exception ?? null;

        return 'HTTP '.$response->status().
            (strlen($url) === 0 ? '' : ' en '.$url).
            ($exception ? ' → '.get_class($exception).' #'.$exception->getCode().': '.$exception->getMessage() : '');
    }
}
