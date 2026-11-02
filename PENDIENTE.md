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
