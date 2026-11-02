# Pendiente

Lo que queda por hacer tras la puesta al día de noviembre de 2026, en el orden acordado. Cada bloque va en su propio PR, en español, con sus pruebas en `api/tests/`, y los fusiona Antonio. Al cerrar un bloque se quita de aquí.

Ya está hecho: los arreglos de seguridad y de `/sync` y las pruebas de la API en el CI.

## Decisiones tomadas

- **Aún no está desplegada**: no hay datos que migrar ni sesiones que conservar.
- **Alojamiento**: un hosting compartido básico de OVH, en una subcarpeta (`/control`) del dominio del cliente. La raíz del dominio será **otra web**.
- **Despliegue**: al fusionar en `main`, GitHub Actions compila y sube por **SFTP**. El Git nativo de OVH no sirve, porque ocupa el directorio entero del sitio.
- **Zona horaria**: `Europe/Madrid` en todas partes.
- **Tarifa**: se congela al facturar.
- **Materiales aportados por el cliente**: si se valoran o no en el CSV, se estudia más adelante. No cambiar su tratamiento actual.
- **Aplicación independiente**: sin login ni integración con Atenea.


## 2. Límite de intentos de login y PIN de 6 cifras

- Hoy el PIN tiene 4 cifras, `login-pin` no limita intentos y el listado de trabajadores para el login es público. El login de admin tampoco limita intentos.
- Migración con la tabla `intentos_login` (clave de cuenta, IP, fecha).
  - Por cuenta: 5 fallos en 15 min → 429 con el tiempo de espera.
  - Por IP (`REMOTE_ADDR`; no fiarse de `X-Forwarded-For`): un límite más alto y configurable, porque en un hosting compartido conviene comprobar que `REMOTE_ADDR` es el del cliente.
  - Un login correcto limpia los fallos de esa cuenta; las filas de más de un día se borran.
- PIN de 6 cifras o más y contraseña de admin de 10 caracteres o más, al crear y al cambiar. La semilla de desarrollo pasa a un PIN de 6 cifras.
- Un `password_verify` ficticio cuando el usuario no existe, para que el tiempo de respuesta no lo delate.

## 3. Zona horaria `Europe/Madrid`

- `timezone` en `config.php`, aplicada con `date_default_timezone_set` al cargar la configuración. En la sesión de MySQL, `SET time_zone` con el desfase actual (`+01:00`/`+02:00`), porque el hosting puede no tener cargadas las tablas de zonas.
- PWA: la fecha de los materiales en fecha local, no `toISOString().slice(0, 10)`.
- Dashboard: `elapsed()` sin añadir `Z`.
- El editor de Trabajos y el CSV ya trabajan en hora local.
- Pruebas: una jornada en UTC de invierno y otra de verano se guardan y se muestran en hora peninsular.

## 4. Cola offline de la PWA por usuario

- Cada registro de la cola lleva el `usuario_id`. `flush()` sube solo los del usuario con sesión y el resto espera a que vuelva a entrar su dueño. Hoy, en un móvil compartido, lo pendiente de un trabajador se sube a nombre del siguiente.
- Con un 401 tras fallar el refresh, volver al login conservando la cola. Hoy la cola falla en silencio para siempre.
- «Salir» con una instalación en curso: no se permite hasta confirmarla o descartarla. Con registros sin subir, se avisa de que se quedan en el móvil y se subirán cuando vuelva a entrar. El texto actual dice lo contrario de lo que pasa.
- Enseñar en la cabecera cuándo falla la subida.
- Pruebas: Vitest con `fake-indexeddb` para `db.js` y `sync.js`, y un paso `npm test` en el CI.

## 5. Jornadas abiertas y pantallas de edición del portal

- **Jornadas que se quedan «en curso» para siempre** (trabajador que sale o pierde el móvil):
  - el Dashboard enseña «Trabajando» indefinidamente y esas horas nunca salen como pendientes;
  - hace falta una lista de jornadas sin confirmar, con Cerrar (fin; se confirma con el `PATCH` que ya existe) y Descartar;
  - **una corrección del admin gana a un reenvío posterior del móvil**: una columna `editada_admin` que `SyncController::protegida` también respete.
- **Rutas `PATCH` sin pantalla**, en Administración:
  - **trabajadores**: nombre, email, rol, tarifa, PIN, contraseña y reactivar;
  - **clientes**: todos los campos, incluido el email;
  - **instalaciones**: nombre, cliente, dirección y descripción;
  - **catálogo**: descripción, unidad y precio.
- **`TrabajadoresController::update`**:
  - que solo cambie lo que llega (hoy pisa con valores por defecto: sin `rol` lo deja en trabajador, sin `nombre` lo deja vacío);
  - 409 con un email repetido (hoy, 500);
  - que nadie pueda darse de baja ni quitarse el rol de admin a sí mismo, ni dejar la aplicación sin admins activos.
- Los `update` de clientes, instalaciones y catálogo, igual: parciales y con el nombre obligatorio.
- **Borrar un cliente con instalaciones → 409.** Hoy se borra de verdad y los CSV antiguos pierden su nombre.
- El router con `{id}` numérico: `/trabajos/abc` → 404 en vez de 500. Ajustar la prueba de `errores.test.mjs`.
- Tildes en los textos del portal («Administración», «Contraseña», «Añadir», «Catálogo»).

## 6. Facturación

- **Tarifa congelada**:
  - columna `jornadas.tarifa_hora`, rellenada al facturar con la del trabajador;
  - revertir la vacía, para que vuelva a tomar la vigente;
  - el CSV y el archivado usan la congelada.
- **Importes en el portal**:
  - € por línea en Trabajos;
  - totales separados de mano de obra, materiales de la empresa y materiales del cliente, sin decidir cómo se valoran estos últimos;
  - el total en € de cada lote en Archivado.
- **CSV a salvo de fórmulas**: los textos que empiecen por `=`, `+`, `-`, `@`, tabulador o retorno de carro llevan delante un `'`. Solo en las columnas de texto: los números no se tocan.
- Facturar sin ninguna línea pendiente → 409, sin crear un lote vacío.
- Revertir o exportar un lote que no existe → 404.

## 7. Dependencias

- `npm audit`: 6 avisos altos en `admin/` y 9 en `pwa/`. Casi todos se arreglan dentro de los rangos actuales (`npm audit fix`, solo el lockfile). Vite pide la 8, con `@vitejs/plugin-vue` 6 y `vite-plugin-pwa` compatible. `vue-router` 5 solo si hace falta.
- Node del CI y del despliegue a 22 o superior: Vite 8 lo exige.
- `npm audit --audit-level=high` en el CI para las dos SPA.
- Comprobar en un navegador que el service worker se registra y que el ciclo sin conexión → reconexión sigue funcionando.

## Más adelante

- Crear instalaciones sin conexión: hoy necesitan red porque esperan el id del servidor. La salida sería un `uuid` generado en el cliente y su paso por `/sync`.
- Revocar sesiones al cambiar el PIN o la contraseña: un `token_version` en `usuarios`.
- Notas de las jornadas: existen en la base y en la API, pero ninguna pantalla las escribe ni las enseña.
- El Dashboard duplica a un trabajador que tiene dos jornadas abiertas.
