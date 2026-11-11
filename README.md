# 🦁 Lions_Dashboard

Panel de analítica para la tienda ficticia de calzado **Lions**, construido con
Laravel 13 + Inertia.js (React 19), Tailwind CSS 4, Framer Motion, Recharts,
Leaflet/OpenStreetMap y Laravel Reverb (WebSockets reales). Incluye integración
**read-only con Shopify** (productos, clientes y pedidos reales vía Admin API).

## Novedades de esta versión

- **Docker** — stack completo con `docker compose up -d` (ver más abajo).
- **Auditoría de seguridad aplicada** — 26 controles revisados según la guía
  "27 controles de seguridad antes de publicar" (ByBrayan.ai): roles y registro
  cerrado, cabeceras de seguridad (CSP, nosniff, Referrer-Policy), rate limiting
  en login, verificación HMAC de webhooks. Detalles abajo.
- **Limpieza de código** — comentarios `//` explicativos eliminados de todo el
  código PHP propio (app, config, rutas, migraciones, tests) con preservación
  de docblocks `/** */` (los usa PHPStan para los tipos); comentarios JSX
  eliminados del frontend. Fix de 3 llamadas `router.post` con callbacks en la
  posición equivocada (`shopify.tsx`) y tipado real del payload de WebSocket
  (`operations-bridge.tsx`).
- **CI en `master`** — el workflow de tests ahora también corre en pushes a
  `master` (antes solo en `main`, y el proyecto usa `master`).

## Características

- **Tarjetas KPI animadas** — contadores que suben progresivamente con Framer Motion
  (ingresos, beneficios, ticket promedio, pedidos, clientes).
- **Gráficos interactivos** — serie de ventas por **día / semana / mes**, por categoría
  y por período (7/30/90/365 días). Las barras de pedidos y la torta de categorías
  se recalculan **solo** (partial reloads de Inertia, sin recargar la página).
- **Actividad en tiempo real** — feed en vivo sobre el canal privado `operaciones`
  vía Reverb: nuevos pedidos, ventas confirmadas, reposiciones y stock bajo.
- **Mapa de distribución de ventas** — burbujas por ciudad (react-leaflet + CARTO tiles
  claro/oscuro según el modo).
- **Inventario y alertas de stock** — variantes por talle, umbral configurable,
  reposición con `+50` y aviso en vivo si sigue bajo.
- **Clientes nuevos y recurrentes** (30 días) + tab VIP y filtros combinables.
- **Modo oscuro** incluido (selector de apariencia integrado del starter kit).
- **Procesos asíncronos**: todos los filtros, paginado, búsqueda con debounce y
  acciones (confirmar pedido, reponer stock) usan `router` de Inertia con
  `preserveState/preserveScroll` y recarga parcial — nunca se bloquea el flujo.
- **Shopify read-only** — conexión desde Settings → Shopify con client
  credentials del Dev Dashboard (sin tokens `shpat_`); importador/backfill de
  catálogo, clientes y pedidos, y webhooks con verificación HMAC que sincronizan
  el estado de pedidos en vivo.

## Páginas

| Ruta                | Descripción                                                   |
| ------------------- | ------------------------------------------------------------- |
| `/`                 | Landing de la tienda Lions                                    |
| `/dashboard`        | Panel principal (KPIs, gráficos, mapa, feed en vivo, alertas) |
| `/pedidos`          | Tabla con estados, confirmación en línea y búsqueda           |
| `/productos`        | Vendidos/ingresos/stock de los últimos 90 días                |
| `/clientes`         | Nuevos, recurrentes, VIP y ticket promedio                    |
| `/inventario`       | Variantes por talle, solo stock bajo, reposición rápida       |
| `/settings/shopify` | Conexión a la tienda Shopify real (solo administradores)      |

## Datos definidos (seeders)

- 19 modelos en 5 categorías (Zapatillas, Botas, Sandalias, Mocasines, Oxfords)
  con variantes 36–45.
- 640 clientes en 14 ciudades (con coordenadas).
- ~2.300 pedidos de los últimos 150 días (pendientes/completados/cancelados),
  ítems y actividad.

## Puesta en marcha con Docker (recomendada)

Requisitos: Docker Desktop (engine + compose). No hace falta PHP ni Node en el host.

```bash
cp .env.docker.example .env.docker   # (Windows: Copy-Item)
docker compose up -d
```

- App: **http://localhost:8088** — Reverb (WebSocket): **ws://localhost:8089**
- Login demo: **admin@lions.com / lions1234**
- La base SQLite y el `storage/` viven en volúmenes (`db-data`, `storage-data`):
  los datos sobreviven a reinicios y rebuilds.
- `SEED_DEMO=true` en `.env.docker` siembra los datos demo **solo si la base está
  vacía** (primer arranque).
- Para conectar tu tienda Shopify real, agregá `SHOPIFY_STORE_DOMAIN`,
  `SHOPIFY_CLIENT_ID` y `SHOPIFY_CLIENT_SECRET` al `.env.docker`.

Qué hace cada contenedor:

| Servicio | Imagen                                                             | Puerto host | Función                                          |
| -------- | ------------------------------------------------------------------ | ----------- | ------------------------------------------------ |
| `app`    | php:8.4-fpm-alpine (build propio con Node para los assets de Vite) | —           | php-fpm; corre migraciones y seed en el arranque |
| `nginx`  | nginx:alpine                                                       | **8088**    | front controller (root `public/`)                |
| `reverb` | misma imagen del app                                               | **8089**    | servidor WebSocket                               |

## Puesta en marcha sin Docker (desarrollo)

```bash
composer install
php artisan migrate:fresh --seed   # crea el esquema + datos ficticios
npm install
```

Usuarios de acceso:

- **admin@lions.com / lions1234** (único rol: administrador)

En cuatro terminales (o `composer dev` para tres de ellas):

```bash
php artisan serve                    # http://localhost:8000
npm run dev                          # assets con HMR
php artisan reverb:start             # WebSocket :8080
php artisan lions:simular            # actividad en tiempo real simulada
```

## Procesos automáticos

- **CI (GitHub Actions, `.github/workflows/tests.yml`)** — en cada push a
  `main`/`master` y en cada PR: `composer setup` (instala dependencias, migra)
  y `composer ci:check`, que ejecuta formato/lint del frontend (`npm run check`),
  chequeo de tipos (`tsc --noEmit`) y la suite de tests (Pest, 88 pruebas).
- **Dependabot** — actualizaciones automáticas de dependencias (configurado en
  el repo; los PRs corren por la misma CI).
- **Entrypoint de Docker** (`docker/entrypoint.sh`) — en cada arranque del
  contenedor `app`: recrea directorios ignorados, ajusta permisos (`www-data`),
  genera `APP_KEY` si falta, corre migraciones (`migrate --force`) y siembra el
  demo si la base no tiene usuarios. El contenedor `reverb` no toca la base.
- **Generación de código** — Wayfinder genera `resources/js/actions` y
  `resources/js/routes` desde los controllers (`php artisan wayfinder:generate`,
  automático durante `npm run dev`/`build`). Son archivos generados: no editarlos.
- **Simulación en vivo** — `lions:simular --intervalo=N` genera pedidos,
  confirmaciones, reposiciones y clientes nuevos cada N segundos (ver abajo).
- **Webhooks de Shopify** — `orders/create|update|cancelled` llegan verificados
  por HMAC-SHA256 y se encolan; el job resincroniza el pedido vía GraphQL.

## Seguridad (auditoría aplicada)

Revisión completa en `SECURITY_AUDIT.md` (26 controles según la guía de 27
puntos de ByBrayan.ai). Estado actual:

| Control                                                                                                                                                             | Estado                                                       |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| Roles: solo `is_admin` ve el panel y Settings→Shopify (`Gate::define('admin')` + `can:admin`)                                                                       | ✅ probado (5 tests)                                         |
| Registro cerrado: `Features::registration()` deshabilitado, `/register` 404, sin link "Sign up"                                                                     | ✅ probado                                                   |
| Cabeceras: `X-Content-Type-Options: nosniff`, `Referrer-Policy`, CSP con `frame-ancestors 'none'` / `base-uri 'self'` / `form-action 'self'`, HSTS solo sobre HTTPS | ✅ probado (4 tests)                                         |
| Rate limiting login: 5 intentos/min por usuario+IP; 2FA limitado a 5/min                                                                                            | ✅                                                           |
| Webhooks Shopify: firma HMAC-SHA256 sobre el body crudo, rechaza sin secret                                                                                         | ✅ probado                                                   |
| Sesiones: `http_only`, `SameSite=Lax`, driver `database`, id/token regenerados en login (Fortify)                                                                   | ✅                                                           |
| Passwords: mín. 12 con mayúsc/minúsc/números/símbolos y chequeo de filtraciones en producción                                                                       | ✅                                                           |
| Mass assignment: `is_admin` protegido (registro cerrado; el campo no es editable por formularios)                                                                   | ✅                                                           |
| Secrets: `.env` y SQLite nunca en git ni en la imagen Docker (`.dockerignore`); placeholders documentados en `.env.example` / `.env.docker.example`                 | ✅                                                           |
| Producción Docker: `display_errors=Off`, `log_errors=On`, opcache, errores genéricos (`APP_DEBUG=false`), `fastcgi_hide_header X-Powered-By`                        | ✅                                                           |
| HTTPS + `SESSION_SECURE_COOKIE=true` + TrustProxies                                                                                                                 | ⏳ pendiente del deploy real (documentado en `.env.example`) |

Pendiente único para producción: publicar detrás de HTTPS con
`SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false` y TrustProxies configurado
(controles 04/15/20 de la guía — no verificables dinámicamente sin un deploy).

## Simulación en vivo

`lions:simular --intervalo=7` crea cada 7 segundos (aleatorio):

1. **Nuevo pedido** real (inserta Order + OrderItem, descuenta stock, puede emitir _stock bajo_).
2. **Confirmación** de un pedido pendiente → pasa a `completado`.
3. **Reposición** de stock de la variante más baja.
4. **Cliente nuevo**.

Cada suceso inserta una fila en `activities` y emite un evento broadcast
(`order.created`, `order.status`, `stock.low`) que el dashboard recibe por
`useEcho` (@laravel/echo-react) y suma al feed **sin recargar nada**.

## Arquitectura

- `app/Services/AnalyticsService.php` — todos los agregados (SQL + `strftime`,
  gaps rellenados, filtro por categoría con `EXISTS`).
- `app/Services/Shopify/*` — cliente Admin API read-only, proveedor de access
  token con caché e importador/backfill.
- `app/Jobs/SyncShopifyOrderFromWebhook.php` — resincronización de pedidos.
- `app/Http/Controllers/*` — páginas Inertia + acciones `conferir/restock`.
- `app/Events/*` — broadcast al canal privado `operaciones` (auth en `routes/channels.php`).
- `resources/js/components/lions/*` — KPI animado, gráficos, mapa, feed, filtros.
- `resources/js/pages/*` — dashboard, pedidos, productos, clientes, inventario, settings.
- `Dockerfile` + `compose.yaml` + `docker/` — stack php-fpm + nginx + reverb.

## Controles

- **Período** 7/30/90/365 días · **Granularidad** día/semana/mes · **Categoría**.
- Búsqueda con debounce (350 ms) en pedidos, productos, clientes e inventario.
- Confirmar pedidos pendientes y reponer stock `+50` sin abandonar la página.

## Verificación local completa

```bash
npm run check          # formato + lint + tipos del frontend
npm run types:check    # solo TypeScript
vendor/bin/pint --test # estilo PHP
php artisan test       # suite Pest (88 pruebas)
```

> Nota: si PHPStan falla en el entorno local por la extensión "turbo"
> (dll de Herd Lite), puede correrse con PHP real en Docker:
> `docker run --rm -v "${PWD}:/app" -w /app php:8.4-cli-alpine php vendor/phpstan/phpstan/phpstan analyse --no-progress`
