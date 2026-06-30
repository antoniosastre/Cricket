<?php
namespace App;

// Middleware de autenticacion: valida el JWT del header Authorization
// y comprueba el rol requerido.
final class Auth
{
    // Devuelve el payload del token o corta la peticion con 401.
    public static function require(?string $rol = null): array
    {
        $header = self::bearer();
        $payload = $header ? Jwt::verify($header) : null;
        if (!$payload) {
            Http::error('No autorizado', 401);
        }
        if ($rol !== null && ($payload['rol'] ?? null) !== $rol) {
            Http::error('Permisos insuficientes', 403);
        }
        return $payload;
    }

    private static function bearer(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!$h && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $h = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/Bearer\s+(.+)/i', $h, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
