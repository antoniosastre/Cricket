<?php
namespace App;

use PDO;
use RuntimeException;

final class Migraciones
{
    // GET_LOCK es por servidor: incluir la base evita bloquear otros alojamientos.
    public static function conBloqueo(PDO $db, string $operacion, callable $accion)
    {
        $nombre = substr(hash('sha256', $db->query('SELECT DATABASE()')->fetchColumn() . '/' . $operacion), 0, 64);
        $tomar = $db->prepare('SELECT GET_LOCK(?, 15)');
        $tomar->execute([$nombre]);
        if ((int) $tomar->fetchColumn() !== 1) {
            throw new RuntimeException('No se pudo obtener el bloqueo');
        }
        try {
            return $accion();
        } finally {
            $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$nombre]);
        }
    }

    public static function aplicar(PDO $db): array
    {
        return self::conBloqueo($db, 'migraciones', static function () use ($db) {
            $db->exec('CREATE TABLE IF NOT EXISTS migraciones (version VARCHAR(190) PRIMARY KEY,
                       aplicada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
            $hechas = $db->query('SELECT version FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
            $guardar = $db->prepare('INSERT INTO migraciones (version) VALUES (?)');
            $nuevas = [];
            foreach (glob(__DIR__ . '/../migrations/[0-9][0-9][0-9]_*.sql') as $archivo) {
                $version = basename($archivo);
                if (in_array($version, $hechas, true)) {
                    continue;
                }
                // Cada sentencia termina en punto y coma al final de su línea.
                $sql = file_get_contents($archivo);
                if ($sql === false) {
                    throw new RuntimeException('No se pudo leer ' . $version);
                }
                foreach (preg_split('/;\h*(?:\R|$)/', $sql) as $sentencia) {
                    if (trim($sentencia) !== '') {
                        $db->exec($sentencia);
                    }
                }
                $guardar->execute([$version]);
                $nuevas[] = $version;
            }
            return $nuevas;
        });
    }
}
