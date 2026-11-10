<?php

use Illuminate\Support\Facades\Route;

/*
 * Control 14 de la guía 27 puntos: cabeceras de seguridad presentes en
 * cualquier respuesta web. HSTS sólo cuando la request es HTTPS.
 * La CSP es estricta sin public/hot (producción/build) y permisiva con
 * él (Vite dev server activo).
 */

it('agrega nosniff y referrer-policy a las respuestas', function () {
    $this->get('/login')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('envia CSP con frame-ancestors none y base-uri self', function () {
    $csp = $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("form-action 'self'");
});

it('CSP estricta sin hot file y permisiva con dev server', function () {
    $csp = $this->get('/login')->headers->get('Content-Security-Policy');

    if (is_file(public_path('hot'))) {
        // Dev: permisiva (assets del dev server, HMR inline)
        expect($csp)->toContain("'unsafe-inline'");
    } else {
        // Producción: 'self' exclusivo en default-src
        expect($csp)->toContain("default-src 'self'")
            ->and($csp)->not->toContain('default-src *');
    }
});

it('HSTS solo con HTTPS', function () {
    // HTTP local: sin HSTS
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    // HTTPS (simulado por el tester): HSTS de 1 año
    Route::get('/_https-probe', fn () => 'ok')->middleware('web');
    $this->get('https://localhost/_https-probe')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
