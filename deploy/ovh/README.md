# Publicar Cricket en /control

El dominio sirve su web habitual desde `www/`. Cricket publica exclusivamente en `www/control/`. El intérprete lee el código y la configuración desde `cricket/api/`, fuera de la raíz pública. Los ejemplos de rutas son relativos al directorio inicial de la cuenta de transferencia.

## Preparación de Antonio

1. En [OVHcloud](https://www.ovhcloud.com/es-es/), activa el certificado del dominio y comprueba que el navegador lo acepta sin avisos. El certificado genérico del clúster no basta. Selecciona PHP (PHP: Hypertext Preprocessor) 8.2 o posterior en el área de cliente o en la configuración del alojamiento. Habilita `pdo_mysql` y `mbstring`.
2. Crea una base de datos y su usuario. Comprueba que el plan permite la transferencia de archivos por SFTP (SSH File Transfer Protocol), el acceso a un directorio privado fuera de `www` y bloqueos de base de datos con `GET_LOCK`.
3. Copia `api/config/config.sample.php` como `cricket/api/config/config.php` en el alojamiento. Rellena los datos de la base y dos secretos independientes, `jwt_secret` y `clave_despliegue`. Genera cada uno por separado con `openssl rand -hex 32`. Usa `cors_origins => []` y `timezone => 'Europe/Madrid'`. La cuenta del servidor web necesita permiso de lectura.
4. En la configuración de [GitHub Actions](https://github.com/features/actions), crea los secretos `OVH_SFTP_PASSWORD` y `CLAVE_DESPLIEGUE`. Este último debe coincidir con `clave_despliegue` en el archivo privado. Crea las variables siguientes.

| Variable | Valor |
| --- | --- |
| `OVH_SFTP_HOST` | Nombre del servidor de archivos |
| `OVH_SFTP_USUARIO` | Usuario de transferencia |
| `OVH_DIR_WEB` | `www/control` |
| `OVH_DIR_CODIGO` | `cricket/api` |
| `URL_APP` | Dirección HTTPS (Hypertext Transfer Protocol Secure) del dominio, terminada en `/control` |
| `OVH_SFTP_KNOWN_HOSTS` | Opcional. Línea de clave pública del servidor, contrastada con el proveedor. Cuando se omite, el ejecutor acepta la clave en la primera conexión de esa ejecución. |

Ambas rutas deben partir del mismo directorio y ser exclusivas de Cricket. El proceso limpia los archivos obsoletos de esos dos destinos. Conserva el directorio privado `config/`.

## Primera publicación

Al entrar un cambio en `main`, el flujo compila los dos frontales, empaqueta, sube los recursos, publica las páginas y el programa de uso sin conexión y limpia los archivos antiguos. Invoca la migración con la clave y espera a que `/control/api/healthz` devuelva el identificador completo del commit. El trabajo se omite si falta alguna variable obligatoria. Los secretos ausentes dan un error de configuración antes de la subida.

Abre `/control/admin/` y usa **Primera puesta en marcha**. Introduce tu nombre, email, contraseña y clave de despliegue. Tras crear el primer administrador, esta operación se cierra. El acceso móvil está en `/control/pwa/`. Comprueba también la portada del dominio.

## Reintento y diagnóstico

Se puede volver a ejecutar el mismo flujo. Las migraciones aplicadas constan en la tabla `migraciones`. Las posteriores se ejecutan en orden bajo un bloqueo exclusivo. Los datos de demostración solo se cargan en desarrollo con `php api/migrations/migrate.php --demo`.

Una respuesta 403 al migrar indica una clave ausente, demasiado corta o distinta de la configuración privada. Una respuesta 503 en `healthz` indica que el servidor no ha podido consultar la base. El historial del flujo identifica qué commit se intentaba publicar. Para volver a una versión anterior, revisa la compatibilidad de su esquema y recupera una copia de la base si corresponde.
