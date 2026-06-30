<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class JornadasController
{
    // Correccion manual por el admin (p. ej. cronometro olvidado):
    // permite ajustar inicio/fin/notas. Recalcula la duracion.
    public static function update(int $id): void
    {
        Auth::require('admin');
        $b = Http::body();
        $db = Db::conn();
        $st = $db->prepare('SELECT * FROM jornadas WHERE id = ?');
        $st->execute([$id]);
        $j = $st->fetch();
        if (!$j) {
            Http::error('Jornada no encontrada', 404);
        }
        if ($j['lote_id'] !== null) {
            Http::error('La jornada ya esta facturada; reviertela antes de editar', 409);
        }
        $inicio = isset($b['inicio']) ? date('Y-m-d H:i:s', strtotime((string) $b['inicio'])) : $j['inicio'];
        $fin = array_key_exists('fin', $b)
            ? ($b['fin'] ? date('Y-m-d H:i:s', strtotime((string) $b['fin'])) : null)
            : $j['fin'];
        $dur = $fin ? max(0, (int) round((strtotime($fin) - strtotime($inicio)) / 60)) : null;
        $notas = array_key_exists('notas', $b) ? mb_substr((string) $b['notas'], 0, 255) : $j['notas'];
        $estado = $fin ? 'confirmada' : $j['estado'];

        $up = $db->prepare(
            'UPDATE jornadas SET inicio=?, fin=?, duracion_min=?, notas=?, estado=? WHERE id=?'
        );
        $up->execute([$inicio, $fin, $dur, $notas, $estado, $id]);
        Http::json(['ok' => true, 'duracion_min' => $dur]);
    }

    // Eliminar una jornada (admin).
    public static function delete(int $id): void
    {
        Auth::require('admin');
        Db::conn()->prepare('DELETE FROM jornadas WHERE id = ? AND lote_id IS NULL')->execute([$id]);
        Http::json(['ok' => true]);
    }
}
