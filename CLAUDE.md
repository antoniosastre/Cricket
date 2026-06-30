# CLAUDE.md

Guía para trabajar en este repositorio con Claude Code. Léela antes de hacer cambios.

## Qué es

App de **seguimiento de partes de trabajo** (horas + materiales por instalación,
para facturar) de una empresa con varios trabajadores. Dos frentes sobre una misma
API:

- `admin/` — **Portal de administración** (Vue 3 + Vite), escritorio/online.
- `pwa/` — **PWA del trabajador** (Vue 3 + Vite), móvil/**offline-first**.
- `api/` — **API REST** en PHP 8 + MySQL (PDO), **sin framework ni Composer**.

Una sola empresa por despliegue (no multi-tenant). Objetivo de hosting: sencillo
tipo OVH/Ionos (Apache/PHP/MySQL).

## Arquitectura del backend (`api/`)

- Front controller único: `api/public/index.php` (autoloader propio + router) con
  `api/public/.htaccess` reescribiendo todo a `index.php` y preservando
  `Authorization`.
- `api/src/`:
  - `Router.php` — patrones tipo `/trabajos/{id}`; los segmentos numéricos llegan
    como `int` al handler.
  - `Db.php` — PDO singleton; `Config.php` lee `api/config/config.php`.
  - `Jwt.php` — **JWT HS256 implementado a mano** (sin dependencias).
  - `Auth.php` — `Auth::require()` / `Auth::require('admin'|'trabajador')`; corta
    con 401/403.
  - `Http.php` — `body()`, `json()`, `error()`, `csv()`, `cors()`.
  - `Controllers/` — un controlador por recurso (Auth, Dashboard, Trabajos,
    Facturacion, Clientes, Instalaciones, Trabajadores, Catalogo, Jornadas, Sync).
- Migraciones: `api/migrations/001_schema.sql` + `migrate.php` (aplica esquema y,
  si la BD está vacía, inserta datos semilla).

### Modelo de datos (claves)

`clientes → instalaciones` (1:N). `usuarios` (rol admin/trabajador, `pin_hash`,
`tarifa_hora`). `jornadas` (tramos de tiempo). `materiales_catalogo` y
`materiales_linea` (cantidad/unidad, `precio_unit`, `origen` empresa|cliente).
`lotes_facturacion`.

Convenciones de estado:
- **Temporizador encendido** (dashboard): `jornadas.estado='en_curso'` y `fin IS NULL`.
- **Pendiente de facturar**: jornadas `estado='confirmada'` y `lote_id IS NULL`;
  materiales con `lote_id IS NULL`.
- **Facturado/historial**: `lote_id` asignado.
- `jornadas.uuid` y `materiales_linea.uuid` (generados en cliente) → `POST /sync`
  hace **upsert idempotente** por `uuid`. Mantén esta propiedad al tocar `/sync`.

## Frontend (`admin/` y `pwa/`)

- Vue 3 + Vite, **rutas hash** (`createWebHashHistory`) y `base: './'` para servir
  en subcarpetas sin reescrituras.
- Cliente HTTP en `src/api.js` (token + refresh automático). Base de API:
  `import.meta.env.VITE_API_URL` con fallback a `'/api'`.
- PWA offline:
  - `pwa/src/db.js` — IndexedDB con `idb`: cola (`outbox_*`) + caché. **Importante**:
    `cachePut` clona con `JSON.parse(JSON.stringify(...))` porque IndexedDB no puede
    serializar proxies reactivos de Vue. No guardes objetos reactivos directamente.
  - `pwa/src/session.js` — sesión en curso persistida en `localStorage`.
  - `pwa/src/sync.js` — vacía la cola al reconectar / al abrir / cada 30 s.
  - `vite-plugin-pwa` genera service worker + manifest (iconos en `pwa/public/`).
- Limitación v1: registrar trabajo sobre una instalación existente funciona offline;
  **crear instalación nueva requiere conexión** (necesita el id del servidor).

## Comandos

```bash
# Backend
cd api
cp config/config.sample.php config/config.php   # editar credenciales + jwt_secret
php migrations/migrate.php                       # esquema + semilla
php -S 127.0.0.1:8080 -t public

# Frontends (cada uno)
cd admin   # o pwa
npm install
echo "VITE_API_URL=http://127.0.0.1:8080" > .env.local
npm run dev
npm run build
```

Semilla: admin `admin@empresa.com` / `admin1234`; trabajadores con PIN `1234`.

## Convenciones

- PHP: PSR-4 bajo el namespace `App\` (mapeado a `api/src/`). Consultas **siempre**
  con sentencias preparadas (PDO). Contraseñas y PIN con `password_hash()`.
- Comentarios y textos de UI en español (sin tildes en el código PHP por
  simplicidad; con tildes en la UI). Mantén el estilo del código existente.
- No se commitea `api/config/config.php`, `node_modules/`, ni los `dist/`
  (ver `.gitignore`).

## Verificación / CI

- CI (`.github/workflows/ci.yml`) en cada PR/push a `main` y `claude/**`:
  `php -l` de `api/` + `npm ci && npm run build` de ambas SPAs.
- Pruebas manuales hechas hasta ahora: flujo completo por `curl` contra MySQL real
  (incluida idempotencia de `/sync` y CSV con importes) y recorrido en navegador con
  Playwright de ambas SPAs, incluido el ciclo **offline → reconexión** de la PWA.
- No hay tests automatizados todavía; si añades lógica no trivial, considera añadir
  pruebas y, si requieren BD, levantar MySQL en el workflow.

## Git

- Desarrollo en la rama `claude/work-tracking-app-lt0dlu`; PR contra `main`.
- No fuerces el push de la rama de trabajo (history rewrite) sin permiso.
