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








## Más adelante

- Crear instalaciones sin conexión: hoy necesitan red porque esperan el id del servidor. La salida sería un `uuid` generado en el cliente y su paso por `/sync`.
- Revocar sesiones al cambiar el PIN o la contraseña: un `token_version` en `usuarios`.
- Notas de las jornadas: existen en la base y en la API, pero ninguna pantalla las escribe ni las enseña.
- El Dashboard duplica a un trabajador que tiene dos jornadas abiertas.
