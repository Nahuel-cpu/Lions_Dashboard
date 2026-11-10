<?php

/*
 * Sin credenciales en .env los comandos deben salir limpios con mensaje
 * claro, sin excepciones ni intentos de red.
 */

it('shopify:ping avisa que faltan credenciales', function () {
    config(['shopify.store' => '', 'shopify.token' => '']);

    $this->artisan('shopify:ping')
        ->expectsOutputToContain('Shopify no está configurado')
        ->assertExitCode(1);
});

it('shopify:import no intenta nada sin credenciales', function () {
    config(['shopify.store' => '', 'shopify.token' => '']);

    $this->artisan('shopify:import --full')
        ->expectsOutputToContain('shopify:ping')
        ->assertExitCode(1);
});
