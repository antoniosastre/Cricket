# Partes de trabajo

[![CI](https://github.com/antoniosastre/Cricket/actions/workflows/ci.yml/badge.svg)](https://github.com/antoniosastre/Cricket/actions/workflows/ci.yml)

Aplicación para el seguimiento centralizado de partes de trabajo de empresas que
prestan servicios con un grupo de trabajadores (p. ej. una instaladora eléctrica
con varios electricistas). Registra las **horas** dedicadas en cada instalación y
los **materiales** empleados —distinguiendo si los aporta la empresa o el cliente—
para su posterior facturación.

Consta de dos frentes sobre una misma API:

- **Portal de administración** (`/admin`): escritorio, online. Dashboard del estado
  de los trabajadores en tiempo real, cierre o descarte de jornadas sin confirmar, edición de usuarios, clientes, instalaciones y catálogo, **edición/borrado de líneas** de
  horas y materiales pendientes, y facturación (marcar líneas como facturadas +
  exportar a CSV).
- **PWA del trabajador** (`/pwa`): móvil, **offline-first**. Selector de instalación,
  temporizador de horas y captura de materiales en obra, con sincronización diferida.

## Stack

- **Backend**: PHP 8 + MySQL (PDO), sin framework. Router propio y JWT (HS256)
  implementado a mano: **no requiere Composer**, ideal para hosting sencillo
  (OVH, Ionos, Apache/Nginx + PHP + MySQL).
- **Frontend**: dos SPAs **Vue 3 + Vite**. La PWA usa `vite-plugin-pwa`
  (service worker + manifest) e **IndexedDB** (`idb`) para la cola offline.

## Estructura

```
api/          Backend PHP (API REST)
  public/     Front controller (index.php) + .htaccess  -> raiz publica de la API
  src/        Config, Db, Jwt, Auth, Router, Http y Controllers/
  config/     config.sample.php (copiar a config.php)
  migrations/ NNN_*.sql + migrate.php (esquema versionado)
admin/        SPA Vue 3 del portal de administracion
pwa/          SPA Vue 3 (PWA) de los trabajadores
```

## Modelo de datos

`clientes` → `instalaciones` (1:N). `usuarios` (admin/trabajador, con `pin_hash` y
`tarifa_hora`). `jornadas` (tramos de tiempo, con `uuid` para deduplicar en la
sincronización, `estado` en_curso/confirmada/descartada y `lote_id`).
`materiales_catalogo` (frecuentes, con precio) y `materiales_linea` (con `uuid`,
cantidad/unidad, `precio_unit` y `origen` empresa/cliente). `lotes_facturacion`
agrupa las líneas facturadas para el historial y la exportación.

- **Pendiente de facturar**: `estado='confirmada'` y `lote_id IS NULL`.
- **Facturado / historial**: `lote_id` asignado. La tarifa se congela en la jornada al facturar y se libera al revertir. Los lotes conservan sus importes cuando cambia la tarifa del trabajador.
- El `uuid` por registro hace que `POST /sync` sea idempotente (upsert): reenviar
  desde la cola offline no duplica datos. Un reenvío no modifica una línea ya
  facturada ni la de otro trabajador: se ignora.

## Puesta en marcha (desarrollo)

Requisitos: PHP 8 con `pdo_mysql`, MySQL/MariaDB y Node 18+.

### 1) Backend

```bash
cd api
cp config/config.sample.php config/config.php   # edita credenciales y jwt_secret
php migrations/migrate.php --demo                # tablas y datos de ejemplo, solo desarrollo
php -S 127.0.0.1:8080 -t public public/index.php # API en http://127.0.0.1:8080
```

Datos semilla: admin `admin@empresa.com` / `admin-demo-2026`; trabajadores con PIN `123456`.

Para los servidores locales del portal y del móvil, configura `cors_origins` con `http://127.0.0.1:5173`, `http://127.0.0.1:5174` o los orígenes exactos que utilices. En producción se usa `[]`.

### 2) Portal admin

```bash
cd admin
npm install
echo "VITE_API_URL=http://127.0.0.1:8080" > .env.local
npm run dev        # http://127.0.0.1:5173
```

### 3) PWA trabajador

```bash
cd pwa
npm install
echo "VITE_API_URL=http://127.0.0.1:8080" > .env.local
npm run dev        # http://127.0.0.1:5174
```

> La PWA y su service worker requieren **HTTPS** (o `localhost`) para instalarse.

## Despliegue en producción

La web del cliente conserva la raíz del dominio. Cricket publica la portada, el portal, la aplicación móvil y su API en `/control/`. La configuración y las migraciones quedan fuera de la raíz web. La [guía de puesta en marcha](deploy/ovh/README.md) detalla las variables, los secretos, la creación del primer administrador y la verificación de la versión servida.

## Funcionamiento offline (PWA)

- La captura de horas y materiales se guarda en `localStorage` (sesión en curso) y
  en una **cola en IndexedDB** (`outbox`).
- Cada registro y cada instalación en curso pertenecen a su trabajador. Tras caducar la sesión, el trabajo queda en el móvil hasta que vuelva a identificarse su dueño.
- `POST /sync` se reintenta al recuperar conexión, al abrir la app y cada 30 s.
  Como el upsert es por `uuid`, reintentar es seguro.
- **Limitación de v1**: seleccionar y registrar trabajo sobre una instalación
  existente funciona sin conexión; **crear una instalación nueva requiere conexión**
  (necesita el id que devuelve el servidor).

## Integración continua (CI)

`.github/workflows/ci.yml` se ejecuta en cada *pull request* y en cada *push* a
`main`:

- **php-lint**: comprueba la sintaxis de todos los `*.php` de `api/` (`php -l`).
- **api**: pruebas de la API de extremo a extremo (`api/tests/`), contra un
  servidor PHP y una base reales: PHP 8.2 con MariaDB 10.11 y PHP 8.4 con MySQL 8.0.
  En local, con la API en marcha y la semilla cargada:

  ```bash
  CRICKET_API=http://127.0.0.1:8080 node --test api/tests/*.test.mjs
  ```

- **build (admin)** y **build (pwa)**: `npm ci` + `npm run build` de cada SPA.

## Seguridad

- Contraseñas y PIN con `password_hash()`; todas las consultas con sentencias
  preparadas (PDO).
- Autenticación JWT con `access` corto y `refresh` largo (sesión prolongada del
  trabajador). Cambia `jwt_secret` en producción.
