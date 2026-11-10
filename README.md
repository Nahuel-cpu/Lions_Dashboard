# 🦁 Lions_Dashboard

Panel de analítica para la tienda ficticia de calzado **Lions**, construido con
Laravel 13 + Inertia.js (React 19), Tailwind CSS 4, Framer Motion, Recharts,
Leaflet/OpenStreetMap y Laravel Reverb (WebSockets reales).

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

## Páginas

| Ruta | Descripción |
| --- | --- |
| `/` | Landing de la tienda Lions |
| `/dashboard` | Panel principal (KPIs, gráficos, mapa, feed en vivo, alertas) |
| `/pedidos` | Tabla con estados, confirmación en línea y búsqueda |
| `/productos` | Vendidos/ingresos/stock de los últimos 90 días |
| `/clientes` | Nuevos, recurrentes, VIP y ticket promedio |
| `/inventario` | Variantes por talle, solo stock bajo, reposición rápida |

## Datos definidos (seeders)

- 19 modelos en 5 categorías (Zapatillas, Botas, Sandalias, Mocasines, Oxfords)
  con variantes 36–45.
- 640 clientes en 14 ciudades (con coordenadas).
- ~2.300 pedidos de los últimos 150 días (pendientes/completados/cancelados),
  ítems y actividad.

## Puesta en marcha

```bash
composer install
php artisan migrate:fresh --seed   # crea el esquema + datos ficticios
npm install
```

Usuarios de acceso:

- **admin@lions.com / lions1234**

En cuatro terminales (o `composer dev` para tres de ellas):

```bash
php artisan serve                    # http://localhost:8000
npm run dev                          # assets con HMR
php artisan reverb:start             # WebSocket :8080
php artisan lions:simular            # actividad en tiempo real simulada
```

## Simulación en vivo

`lions:simular --intervalo=7` crea cada 7 segundos (aleatorio):

1. **Nuevo pedido** real (inserta Order + OrderItem, descuenta stock, puede emitir *stock bajo*).
2. **Confirmación** de un pedido pendiente → pasa a `completado`.
3. **Reposición** de stock de la variante más baja.
4. **Cliente nuevo**.

Cada suceso inserta una fila en `activities` y emite un evento broadcast
(`order.created`, `order.status`, `stock.low`) que el dashboard recibe por
`useEcho` (@laravel/echo-react) y suma al feed **sin recargar nada**.

## Arquitectura

- `app/Services/AnalyticsService.php` — todos los agregados (SQL + `strftime`,
  gaps rellenados, filtro por categoría con `EXISTS`).
- `app/Http/Controllers/*` — páginas Inertia + acciones `conferir/restock`.
- `app/Events/*` — broadcast al canal privado `operaciones` (auth en `routes/channels.php`).
- `resources/js/components/lions/*` — KPI animado, gráficos, mapa, feed, filtros.
- `resources/js/pages/*` — dashboard, pedidos, productos, clientes, inventario.

## Controles

- **Período** 7/30/90/365 días · **Granularidad** día/semana/mes · **Categoría**.
- Búsqueda con debounce (350 ms) en pedidos, productos, clientes e inventario.
- Confirmar pedidos pendientes y reponer stock `+50` sin abandonar la página.
