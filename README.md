# Partes de trabajo

Aplicación para el seguimiento centralizado de partes de trabajo de empresas que
prestan servicios con un grupo de trabajadores (p. ej. una instaladora eléctrica
con varios electricistas). Registra las **horas** dedicadas en cada instalación y
los **materiales** empleados —distinguiendo si los aporta la empresa o el cliente—
para su posterior facturación.

Consta de dos frentes sobre una misma API:

- **Portal de administración** (`/admin`): escritorio, online. Dashboard del estado
  de los trabajadores en tiempo real, gestión y facturación (marcar líneas como
  facturadas + exportar a CSV).
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
  migrations/ 001_schema.sql + migrate.php (esquema + semilla)
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
- **Facturado / historial**: `lote_id` asignado.
- El `uuid` por registro hace que `POST /sync` sea idempotente (upsert): reenviar
  desde la cola offline no duplica datos.

## Puesta en marcha (desarrollo)

Requisitos: PHP 8 con `pdo_mysql`, MySQL/MariaDB y Node 18+.

### 1) Backend

```bash
cd api
cp config/config.sample.php config/config.php   # edita credenciales y jwt_secret
php migrations/migrate.php                       # crea tablas + datos de ejemplo
php -S 127.0.0.1:8080 -t public                  # API en http://127.0.0.1:8080
```

Datos semilla: admin `admin@empresa.com` / `admin1234`; trabajadores con PIN `1234`.

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

## Despliegue en hosting sencillo (OVH / Ionos)

1. **Base de datos**: crea una BD MySQL y un usuario. Importa el esquema con
   `php migrations/migrate.php` (o ejecutando `migrations/001_schema.sql` desde
   phpMyAdmin y creando luego el usuario admin).
2. **API**: sube la carpeta `api/` y haz que el dominio/subdominio de la API
   apunte a `api/public/`. Copia `config/config.php` con las credenciales reales y
   un `jwt_secret` largo y aleatorio. El `.htaccess` ya reescribe todo a
   `index.php` y preserva la cabecera `Authorization`.
3. **Frontends**: compila cada SPA y sube el contenido de `dist/`:

   ```bash
   cd admin && VITE_API_URL=https://tu-dominio/api npm run build   # -> sube admin/dist a /admin
   cd pwa   && VITE_API_URL=https://tu-dominio/api npm run build   # -> sube pwa/dist a /pwa
   ```

   Ambas usan rutas *hash* (`#/...`) y `base: './'`, así que funcionan en cualquier
   subcarpeta sin configurar reescrituras adicionales.
4. **HTTPS** obligatorio para la PWA.

## Funcionamiento offline (PWA)

- La captura de horas y materiales se guarda en `localStorage` (sesión en curso) y
  en una **cola en IndexedDB** (`outbox`).
- `POST /sync` se reintenta al recuperar conexión, al abrir la app y cada 30 s.
  Como el upsert es por `uuid`, reintentar es seguro.
- **Limitación de v1**: seleccionar y registrar trabajo sobre una instalación
  existente funciona sin conexión; **crear una instalación nueva requiere conexión**
  (necesita el id que devuelve el servidor).

## Seguridad

- Contraseñas y PIN con `password_hash()`; todas las consultas con sentencias
  preparadas (PDO).
- Autenticación JWT con `access` corto y `refresh` largo (sesión prolongada del
  trabajador). Cambia `jwt_secret` en producción.
