<?php
// Copia este archivo a config.php y rellena los valores reales.
// config.php NO se versiona (ver .gitignore).

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'partes_trabajo',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // Secreto para firmar los JWT: una cadena aleatoria de al menos 32 caracteres
    // (p. ej. `openssl rand -base64 48`). Con este valor de ejemplo, o con uno mas
    // corto, la API se niega a emitir y a aceptar tokens (responde 500).
    'jwt_secret' => 'CAMBIA_ESTO_POR_UN_SECRETO_LARGO_Y_ALEATORIO',
    // Duracion de los tokens (segundos).
    'access_ttl'  => 3600,            // 1 hora
    'refresh_ttl' => 60 * 60 * 24 * 60, // 60 dias (sesion larga del trabajador)
    // En producción se usa el mismo origen. En desarrollo, indicar los orígenes locales.
    'clave_despliegue' => '', // Secreto independiente de 32 caracteres o más.
    'cors_origins' => [],
];
