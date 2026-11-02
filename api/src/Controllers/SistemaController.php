<?php
namespace App\Controllers;

use App\Config;
use App\Db;
use App\Http;
use App\Migraciones;

final class SistemaController
{
    private static function autorizar(): void
    {
        $clave = (string) Config::get('clave_despliegue', '');
        if (strlen($clave) < 32 || !hash_equals($clave, $_SERVER['HTTP_X_CLAVE_DESPLIEGUE'] ?? '')) {
            Http::error('Clave de despliegue no válida', 403);
        }
    }

    public static function migrar(): void
    {
        self::autorizar();
        Http::json(['aplicadas' => Migraciones::aplicar(Db::conn())]);
    }

    private static function necesitaAdmin(): bool
    {
        return !Db::conn()->query("SELECT 1 FROM usuarios WHERE rol='admin' AND activo=1 LIMIT 1")->fetchColumn();
    }

    public static function estado(): void
    {
        Http::json(['necesita_admin' => self::necesitaAdmin()]);
    }

    public static function adminInicial(): void
    {
        self::autorizar();
        $db = Db::conn();
        $creado = Migraciones::conBloqueo($db, 'admin-inicial', static function () use ($db) {
            if (!self::necesitaAdmin()) {
                return false;
            }
            $b = Http::body();
            $nombre = trim((string) ($b['nombre'] ?? ''));
            $email = trim((string) ($b['email'] ?? ''));
            $pass = (string) ($b['password'] ?? '');
            if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 10) {
                Http::error('Se requieren nombre, email válido y contraseña de al menos 10 caracteres');
            }
            try {
                $db->prepare("INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,'admin')")
                   ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            } catch (\PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    Http::error('El email ya está en uso', 409);
                }
                throw $e;
            }
            return true;
        });
        if (!$creado) {
            Http::error('Ya existe un administrador activo', 409);
        }
        Http::json(['ok' => true], 201);
    }

    public static function health(): void
    {
        $archivo = __DIR__ . '/../../VERSION';
        $version = is_file($archivo) ? trim(file_get_contents($archivo)) : 'desarrollo';
        try {
            Db::conn()->query('SELECT 1');
            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
        }
        header('Cache-Control: no-store');
        Http::json(['ok' => $ok, 'version' => $version], $ok ? 200 : 503);
    }
}
