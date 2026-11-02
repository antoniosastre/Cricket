<?php
namespace App;

// Implementacion minima de JWT (HS256) sin dependencias externas,
// para poder desplegar en hosting sencillo sin Composer.
final class Jwt
{
    // El valor de config.sample.php es publico (esta en el repositorio): con el,
    // cualquiera podria firmar un token de admin.
    private const SECRETO_DE_EJEMPLO = 'CAMBIA_ESTO_POR_UN_SECRETO_LARGO_Y_ALEATORIO';

    // Falla cerrado: sin un secreto propio no se firma ni se acepta ningun token.
    private static function secret(): string
    {
        $secret = (string) Config::get('jwt_secret', '');
        if (strlen($secret) < 32 || $secret === self::SECRETO_DE_EJEMPLO) {
            throw new \RuntimeException(
                'jwt_secret sin configurar: pon en config.php una cadena aleatoria de al menos 32 caracteres'
            );
        }
        return $secret;
    }

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $txt): string
    {
        $pad = strlen($txt) % 4;
        if ($pad) {
            $txt .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($txt, '-_', '+/'));
    }

    public static function sign(array $payload, int $ttl): string
    {
        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttl,
        ]);
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signing = implode('.', $segments);
        $sig = hash_hmac('sha256', $signing, self::secret(), true);
        $segments[] = self::b64url($sig);
        return implode('.', $segments);
    }

    // Devuelve el payload si el token es valido, o null en caso contrario.
    public static function verify(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $expected = self::b64url(
            hash_hmac('sha256', "$h.$p", self::secret(), true)
        );
        if (!hash_equals($expected, $s)) {
            return null;
        }
        $payload = json_decode(self::b64urlDecode($p), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return null;
        }
        return $payload;
    }
}
