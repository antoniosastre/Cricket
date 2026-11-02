<?php
namespace App;

use PDO;

final class IntentosLogin
{
    // El mismo identificador de cuenta se usa aunque se alterne entre email e id.
    public static function verificar(?array $usuario, string $ident, string $rol, string $secreto): void
    {
        $db = Db::conn();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $cuenta = hash('sha256', $rol . '/' . ($usuario['id'] ?? mb_strtolower($ident)));
        $campo = $rol === 'admin' ? 'password_hash' : 'pin_hash';
        $hash = $usuario[$campo] ?? null;
        $resultado = Migraciones::conBloqueo($db, 'ip/' . $ip, static function () use ($db, $ip, $cuenta, $hash, $secreto) {
            return Migraciones::conBloqueo($db, 'cuenta/' . $cuenta, static function () use ($db, $ip, $cuenta, $hash, $secreto) {
                $db->exec('DELETE FROM intentos_login WHERE fecha < UTC_TIMESTAMP() - INTERVAL 1 DAY');
                $espera = max(self::espera($db, 'cuenta', $cuenta, 5),
                              self::espera($db, 'ip', $ip, max(6, (int) Config::get('limite_login_ip', 60))));
                if ($espera > 0) {
                    return $espera;
                }
                // Coste de bcrypt de esta versión de PHP también para un usuario desconocido.
                $ficticio = sprintf('$2y$%02d$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', PASSWORD_BCRYPT_DEFAULT_COST);
                if (password_verify($secreto, $hash ?: $ficticio) && $hash) {
                    // Se limpia la cuenta, conservando la contabilidad por IP.
                    $db->prepare('UPDATE intentos_login SET cuenta=NULL WHERE cuenta=?')->execute([$cuenta]);
                    return 0;
                }
                $db->prepare('INSERT INTO intentos_login (cuenta,ip,fecha) VALUES (?,?,UTC_TIMESTAMP())')
                   ->execute([$cuenta, $ip]);
                return -1;
            });
        });
        if ($resultado > 0) {
            header('Retry-After: ' . $resultado);
            Http::json(['error' => 'Demasiados intentos. Espera ' . $resultado . ' segundos.', 'espera' => $resultado], 429);
        }
        if ($resultado < 0) {
            Http::error('Credenciales inválidas', 401);
        }
    }

    private static function espera(PDO $db, string $campo, string $valor, int $limite): int
    {
        $st = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), fecha + INTERVAL 15 MINUTE)
                            FROM intentos_login WHERE $campo=? AND fecha > UTC_TIMESTAMP() - INTERVAL 15 MINUTE
                            ORDER BY fecha DESC LIMIT 1 OFFSET " . ($limite - 1));
        $st->execute([$valor]);
        return (int) $st->fetchColumn();
    }
}
