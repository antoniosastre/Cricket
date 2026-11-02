<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class JornadasController
{
    public static function abiertas(): void
    {
        Auth::require('admin');
        Http::json(Db::conn()->query("SELECT j.id, j.inicio, j.fin, u.nombre AS trabajador, i.nombre AS instalacion
                    FROM jornadas j JOIN usuarios u ON u.id=j.usuario_id JOIN instalaciones i ON i.id=j.instalacion_id
                    WHERE j.estado='en_curso' AND j.lote_id IS NULL ORDER BY j.inicio")->fetchAll());
    }

    // Correccion manual por el admin (p. ej. cronometro olvidado):
    // permite ajustar inicio/fin/notas. Recalcula la duracion.
    public static function update(int $id): void
    {
        Auth::require('admin');
        $b = Http::body();
        $db = Db::conn();
        $db->beginTransaction();
        $st = $db->prepare('SELECT * FROM jornadas WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $j = $st->fetch();
        if (!$j) {
            Http::error('Jornada no encontrada', 404);
        }
        if ($j['lote_id'] !== null) {
            Http::error('La jornada ya esta facturada; reviertela antes de editar', 409);
        }
        $inicioTs = strtotime((string) ($b['inicio'] ?? $j['inicio']));
        $finValor = array_key_exists('fin', $b) ? $b['fin'] : $j['fin'];
        $finTs = $finValor === null || $finValor === '' ? null : strtotime((string) $finValor);
        if ($inicioTs === false || $finTs === false || ($finTs !== null && $finTs < $inicioTs)) {
            Http::error('Revisa el inicio y el fin de la jornada');
        }
        $inicio = date('Y-m-d H:i:s', $inicioTs);
        $fin = $finTs === null ? null : date('Y-m-d H:i:s', $finTs);
        $dur = $finTs === null ? null : (int) round(($finTs - $inicioTs) / 60);
        $notas = array_key_exists('notas', $b) ? mb_substr((string) $b['notas'], 0, 255) : $j['notas'];
        $estado = $fin ? 'confirmada' : 'en_curso';

        $up = $db->prepare(
            'UPDATE jornadas SET inicio=?, fin=?, duracion_min=?, notas=?, estado=?, editada_admin=1 WHERE id=?'
        );
        $up->execute([$inicio, $fin, $dur, $notas, $estado, $id]);
        $db->commit();
        Http::json(['ok' => true, 'duracion_min' => $dur]);
    }

    // Eliminar una jornada (admin).
    public static function delete(int $id): void
    {
        Auth::require('admin');
        $st = Db::conn()->prepare("UPDATE jornadas SET estado='descartada', editada_admin=1 WHERE id=? AND lote_id IS NULL");
        $st->execute([$id]);
        if (!$st->rowCount()) {
            $fila = Db::conn()->prepare('SELECT lote_id FROM jornadas WHERE id=?');
            $fila->execute([$id]);
            $j = $fila->fetch();
            if (!$j) Http::error('Jornada no encontrada', 404);
            if ($j['lote_id'] !== null) Http::error('La jornada ya está facturada', 409);
        }
        Http::json(['ok' => true]);
    }
}
