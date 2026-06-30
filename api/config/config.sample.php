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
    // Secreto para firmar los JWT. Cambialo por una cadena larga y aleatoria.
    'jwt_secret' => 'CAMBIA_ESTO_POR_UN_SECRETO_LARGO_Y_ALEATORIO',
    // Duracion de los tokens (segundos).
    'access_ttl'  => 3600,            // 1 hora
    'refresh_ttl' => 60 * 60 * 24 * 60, // 60 dias (sesion larga del trabajador)
    // Origenes permitidos para CORS (las dos SPAs). '*' para desarrollo.
    'cors_origins' => '*',
];
