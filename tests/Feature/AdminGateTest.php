<?php

use App\Models\User;

it('usuario autenticado sin rol no accede al dashboard (403)', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertForbidden();
});

it('usuario autenticado sin rol no accede a settings shopify (403)', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/settings/shopify')
        ->assertForbidden();
});

it('usuario sin rol no puede borrar demo ni sincronizar (403)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/settings/shopify/vaciar-demo')->assertForbidden();
    $this->post('/settings/shopify/sincronizar')->assertForbidden();
});

it('admin accede a settings shopify y al dashboard (200)', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get('/settings/shopify')->assertOk();
    $this->get('/dashboard')->assertOk();
});

it('registro público deshabilitado: la pantalla y el endpoint no existen', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
