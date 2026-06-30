<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

// Sincronizacion offline-first de la PWA. Upsert idempotente por uuid:
// reenviar el mismo registro no crea duplicados. Se usa tanto para el
// heartbeat del temporizador (online) como para vaciar la cola offline.
final class SyncController
{
    public static function sync(): void
    {
        $p = Auth::require('trabajador');
        $usuarioId = (int) $p['sub'];
        $b = Http::body();
        $jornadas   = (array) ($b['jornadas'] ?? []);
        $materiales = (array) ($b['materiales'] ?? []);

        $db = Db::conn();
        $db->beginTransaction();
        try {
            foreach ($jornadas as $j) {
                self::upsertJornada($db, $usuarioId, $j);
            }
            foreach ($materiales as $m) {
                self::upsertMaterial($db, $usuarioId, $m);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Http::error('Error de sincronizacion: ' . $e->getMessage(), 500);
        }

        Http::json(['ok' => true, 'jornadas' => count($jornadas), 'materiales' => count($materiales)]);
    }

    private static function upsertJornada(\PDO $db, int $usuarioId, array $j): void
    {
        $uuid = (string) ($j['uuid'] ?? '');
        $inst = (int) ($j['instalacion_id'] ?? 0);
        $inicio = self::dt($j['inicio'] ?? null);
        if ($uuid === '' || $inst === 0 || $inicio === null) {
            return; // datos incompletos: se ignora
        }
        $fin = self::dt($j['fin'] ?? null);
        $estado = in_array($j['estado'] ?? '', ['en_curso', 'confirmada', 'descartada'], true)
            ? $j['estado'] : 'en_curso';
        $dur = $fin !== null
            ? max(0, (int) round((strtotime($fin) - strtotime($inicio)) / 60))
            : null;
        $notas = isset($j['notas']) ? mb_substr((string) $j['notas'], 0, 255) : null;

        $sql = 'INSERT INTO jornadas (uuid, instalacion_id, usuario_id, inicio, fin, duracion_min, notas, estado)
                VALUES (:uuid, :inst, :usuario, :inicio, :fin, :dur, :notas, :estado)
                ON DUPLICATE KEY UPDATE
                    instalacion_id = VALUES(instalacion_id),
                    inicio = VALUES(inicio),
                    fin = VALUES(fin),
                    duracion_min = VALUES(duracion_min),
                    notas = VALUES(notas),
                    estado = VALUES(estado)';
        $st = $db->prepare($sql);
        $st->execute([
            ':uuid' => $uuid, ':inst' => $inst, ':usuario' => $usuarioId,
            ':inicio' => $inicio, ':fin' => $fin, ':dur' => $dur,
            ':notas' => $notas, ':estado' => $estado,
        ]);
    }

    private static function upsertMaterial(\PDO $db, int $usuarioId, array $m): void
    {
        $uuid = (string) ($m['uuid'] ?? '');
        $inst = (int) ($m['instalacion_id'] ?? 0);
        $desc = trim((string) ($m['descripcion'] ?? ''));
        if ($uuid === '' || $inst === 0 || $desc === '') {
            return;
        }
        $fecha = self::date($m['fecha'] ?? null) ?? date('Y-m-d');
        $catalogoId = isset($m['catalogo_id']) && $m['catalogo_id'] !== '' ? (int) $m['catalogo_id'] : null;
        $cantidad = isset($m['cantidad']) && $m['cantidad'] !== '' ? (float) $m['cantidad'] : 1.0;
        $unidad = trim((string) ($m['unidad'] ?? 'ud')) ?: 'ud';
        $precio = isset($m['precio_unit']) && $m['precio_unit'] !== '' ? (float) $m['precio_unit'] : null;
        $origen = ($m['origen'] ?? 'empresa') === 'cliente' ? 'cliente' : 'empresa';

        $sql = 'INSERT INTO materiales_linea
                    (uuid, instalacion_id, usuario_id, fecha, catalogo_id, descripcion, cantidad, unidad, precio_unit, origen)
                VALUES (:uuid, :inst, :usuario, :fecha, :cat, :desc, :cant, :unidad, :precio, :origen)
                ON DUPLICATE KEY UPDATE
                    descripcion = VALUES(descripcion),
                    cantidad = VALUES(cantidad),
                    unidad = VALUES(unidad),
                    precio_unit = VALUES(precio_unit),
                    origen = VALUES(origen),
                    fecha = VALUES(fecha)';
        $st = $db->prepare($sql);
        $st->execute([
            ':uuid' => $uuid, ':inst' => $inst, ':usuario' => $usuarioId,
            ':fecha' => $fecha, ':cat' => $catalogoId, ':desc' => mb_substr($desc, 0, 200),
            ':cant' => $cantidad, ':unidad' => $unidad, ':precio' => $precio, ':origen' => $origen,
        ]);
    }

    private static function dt($v): ?string
    {
        if (!$v) {
            return null;
        }
        $ts = strtotime((string) $v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private static function date($v): ?string
    {
        if (!$v) {
            return null;
        }
        $ts = strtotime((string) $v);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
