# Informe de auditoría de seguridad — Lions Dashboard

- **Guía de referencia:** "27 controles de seguridad antes de publicar tu app" (ByBrayan.ai, ed. 2026)
- **Aplicación:** Lions Dashboard (Laravel 12 + React/Inertia + SQLite, Shopify Admin API read-only)
- **Fase 1 (auditoría):** 2026-11-10 · **Commit base:** `4ef7669`
- **Fase 2 (correcciones):** 2026-11-10 · ejecutada tras "APROBADO" del plan
- **Entorno auditado:** desarrollo local (`http://localhost:8000`, APP_ENV=local)

## Resumen

| Hallazgos | Corregidos y probados | Abiertos | Riesgos aceptados |
|---|---|---|---|
| 26 revisados | 4 (F1+F2, F3, P2a, P2b) | 1 (prod, pendiente del deploy) | 0 |

## Fase 2 — correcciones aplicadas y verificadas

### 1. Roles + registro cerrado (controles 03 y 09 — hallazgos críticos/alto)

**Archivos modificados:**
- `database/migrations/2026_11_10_100000_add_is_admin_to_users_table.php` (nuevo): columna `is_admin` (default false)
- `app/Models/User.php`: fillable + cast boolean `is_admin`
- `app/Providers/AppServiceProvider.php`: `Gate::define('admin')`
- `routes/web.php`: middleware `can:admin` en todo el panel
- `routes/settings.php`: middleware `can:admin` sólo en Settings → Shopify
- `config/fortify.php`: `Features::registration()` deshabilitado
- `resources/js/pages/auth/login.tsx`: sin link "Sign up"
- `resources/js/pages/auth/register.tsx`: **eliminada** (registro cerrado)
- `database/seeders/DatabaseSeeder.php`: `admin@lions.com` con `is_admin = true`
- Tests: `tests/Feature/AdminGateTest.php` (nuevo, 5 pruebas), factories/tests actualizados a `admin()`

**Pruebas ejecutadas:**
- ✅ Negativa: usuario autenticado sin rol → `GET /dashboard` 403, `GET /settings/shopify` 403, `POST vaciar-demo`/`sincronizar` 403
- ✅ Negativa: `GET /register` y `POST /register` → 404 (registro deshabilitado)
- ✅ Positiva: admin → `/settings/shopify` 200 y `/dashboard` 200
- ✅ Suite completa: 88 tests (86 pass + 2 skips automáticos de RegistrationTest)

### 2. Cabeceras de seguridad (control 14 — medio)

**Archivos modificados:**
- `app/Http/Middleware/SecurityHeaders.php` (nuevo): `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, CSP con `frame-ancestors 'none'`/`base-uri 'self'`/`form-action 'self'`, HSTS sólo sobre HTTPS
- `bootstrap/app.php`: middleware registrado en el grupo web
- `tests/Feature/SecurityHeadersTest.php` (nuevo, 4 pruebas)

**Nota de diseño:** la CSP es **estricta sin `public/hot`** (producción/build) y **permisiva con él** (Vite dev server: assets en otro origen/puerto con inline scripts de HMR). Detectado y corregido en verificación en navegador: la CSP estricta bloqueaba el panel en modo dev.

**Pruebas ejecutadas:**
- ✅ `nosniff` + `Referrer-Policy` presentes
- ✅ CSP con `frame-ancestors 'none'` (anti-clickjacking), `base-uri 'self'`, `form-action 'self'`
- ✅ HSTS ausente en HTTP local, presente simulando HTTPS
- ✅ Navegador real (Edge headless): dashboard renderizado, Settings 200, **cero violaciones CSP**

### 3. Config de producción documentada (controles 04, 15, 20 — medio)

- `.env.example`: bloque de seguridad para deploy (`SESSION_SECURE_COOKIE=true` con HTTPS, `APP_DEBUG=false`, TrustProxies detrás de proxy). Verificación real: pendiente del deploy (control 04/15/20 siguen NO VERIFICADO dinámicamente hasta publicar con HTTPS).

### 4. Cosméticos (P3 — bajo)

- `lang/es/validation.php`: eliminado mensaje `access_token.starts_with` con texto obsoleto del flujo `shpat_` (código muerto: el controller ya no valida tokens)
- `app/Console/Commands/ShopifyPing.php`: instrucciones actualizadas al flujo Dev Dashboard (client credentials)
- Mojibake: re-escaneado el proyecto completo — ya estaba limpio (sólo `→`/`✓` legítimos)

## Tabla de la Fase 1 (ordenada por riesgo)

| # | Control | Estado F1 | Estado F2 | Evidencia |
|---|---|---|---|---|
| 1 | 03 — Aislamiento entre usuarios | FALLA | **CORREGIDO Y VERIFICADO** | Gate `admin` + registro cerrado; 403 a no-admin (test + suite) |
| 2 | 09 — Permisos de administrador | FALLA | **CORREGIDO Y VERIFICADO** | `can:admin` en settings/shopify; prueba negativa 403 |
| 3 | 14 — Cabeceras de seguridad | FALLA | **CORREGIDO Y VERIFICADO** | SecurityHeaders middleware; 4 tests + verificación en navegador |
| 4 | 15 — Cookies de sesión | FALLA (prod) | **DOCUMENTADO** | `.env.example` con `SESSION_SECURE_COOKIE=true` para deploy |
| 5 | 04 — HTTPS obligatorio | NO VERIFICADO | Pendiente del deploy | Sin dominio público aún |
| 6 | 20 — Errores de producción | PENDIENTE | **DOCUMENTADO** | `APP_DEBUG=false` en el checklist de deploy |
| 7 | 05 — Hash de contraseñas | OK con justificación | igual | bcrypt (Argon2id no disponible en el PHP local, verificado) |
| 8–26 | Resto de controles | OK/NO APLICA | sin cambios | Ver detalle en la sección del informe original (commit 4ef7669) |

## Credenciales expuestas, pendientes y límites

- **Credenciales por revocar/rotar:** ninguna (nada fue publicado; secretos sólo locales: client secret Shopify en `.env` y en SQLite cifrado con cast `encrypted`)
- **Pendientes antes de publicar:** deploy con HTTPS + `SESSION_SECURE_COOKIE=true` + `APP_DEBUG=false` + TrustProxies si va detrás de proxy
- **Pruebas no realizadas y motivo:** reutilización de enlace de recuperación y webhook sin firma (requieren entorno de pruebas dinámicas; el webhook valida HMAC `hash_equals` en código, `ShopifyWebhookController.php:31`)
- **Riesgos aceptados conscientemente:** ninguno

## Criterio de cierre

Hallazgos 1–4 cerrados con corrección aplicada y probada. Los controles 04/15/20 dependen del entorno de producción y quedan como checklist de deploy.
