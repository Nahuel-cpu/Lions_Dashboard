<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad (control 14 de la guía 27 puntos):
 *
 * - X-Content-Type-Options: nosniff — evita MIME sniffing.
 * - Referrer-Policy: strict-origin-when-cross-origin.
 * - frame-ancestors 'none' — no permitimos que nos enmarquen (anti clickjacking).
 * - CSP estricta en producción: default 'self'; imágenes propias + data: +
 *   tiles de OpenStreetMap; estilos inline (React los necesita); websocket
 *   para Reverb.
 *   En desarrollo (public/hot = Vite dev server) la CSP se relaja con el
 *   origen real del dev server: el hot file contiene la URL (puede ser
 *   [::1] y un puerto distinto de 5173). Sin esto, la CSP rompe el panel
 *   mientras se desarrolla.
 * - HSTS sólo cuando la request ya es HTTPS (el deploy con SSL lo activa
 *   solo, sin tocar nada).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', $this->csp());

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * CSP estricta en producción; en dev (hot file = Vite dev server) se
     * envía una CSP permisiva: los assets vienen de otro origen y puerto
     * con inline scripts de HMR, y la CSP estricta rompería el panel
     * mientras se desarrolla. El control de seguridad aplica al deploy.
     */
    private function csp(): string
    {
        if (is_file(public_path('hot'))) {
            return implode('; ', [
                "default-src * 'unsafe-inline' 'unsafe-eval' data: blob:",
                "base-uri 'self'",
                'form-action \'self\'',
                "frame-ancestors 'none'",
                'img-src * data: blob:',
                "style-src * 'unsafe-inline'",
                'font-src * data:',
                'connect-src * ws: wss:',
            ]);
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            'form-action \'self\'',
            "frame-ancestors 'none'",
            "img-src 'self' data: https://tile.openstreetmap.org",
            'script-src \'self\'',
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            'connect-src \'self\' ws: wss:',
        ]);
    }
}
