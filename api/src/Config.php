<?php
namespace App;

// Carga y expone la configuracion de config/config.php.
final class Config
{
    private static ?array $data = null;

    public static function load(): void
    {
        if (self::$data !== null) {
            return;
        }
        $path = __DIR__ . '/../config/config.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Falta la configuración privada');
        }
        self::$data = require $path;
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        return self::$data[$key] ?? $default;
    }
}
