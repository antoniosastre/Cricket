<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class TrabajosController
{
    // Listado de instalaciones con elementos pendientes de facturar:
    // total de horas pendientes y numero de materiales pendientes.
    public static function index(): void
    {
        Auth::require('admin');
        $sql = "SELECT i.id, i.nombre, i.estado,
                       c.nombre AS cliente,
                       COALESCE(h.minutos, 0) AS minutos,
                       COALESCE(h.n_horas, 0) AS n_horas,
                       COALESCE(m.n_mat, 0)   AS n_mat
                FROM instalaciones i
                LEFT JOIN clientes c ON c.id = i.cliente_id
                LEFT JOIN (
                    SELECT instalacion_id,
                           SUM(duracion_min) AS minutos,
                           COUNT(*)          AS n_horas
                    FROM jornadas
                    WHERE estado = 'confirmada' AND lote_id IS NULL
                    GROUP BY instalacion_id
                ) h ON h.instalacion_id = i.id
                LEFT JOIN (
                    SELECT instalacion_id, COUNT(*) AS n_mat
                    FROM materiales_linea
                    WHERE lote_id IS NULL
                    GROUP BY instalacion_id
                ) m ON m.instalacion_id = i.id
                WHERE i.estado = 'activa'
                  AND (COALESCE(h.n_horas,0) > 0 OR COALESCE(m.n_mat,0) > 0)
                ORDER BY i.nombre";
        $rows = Db::conn()->query($sql)->fetchAll();
        $out = array_map(static function ($r) {
            return [
                'id'           => (int) $r['id'],
                'nombre'       => $r['nombre'],
                'cliente'      => $r['cliente'],
                'total_horas'  => round(((int) $r['minutos']) / 60, 2),
                'total_mat'    => (int) $r['n_mat'],
            ];
        }, $rows);
        Http::json($out);
    }

    // Detalle de una instalacion: bloque de horas y bloque de materiales
    // (empresa primero, luego cliente). Solo lineas pendientes de facturar.
    public static function show(int $id): void
    {
        Auth::require('admin');
        $db = Db::conn();

        $jh = $db->prepare(
            "SELECT j.id, j.inicio, j.fin, j.duracion_min, u.nombre AS trabajador
             FROM jornadas j JOIN usuarios u ON u.id = j.usuario_id
             WHERE j.instalacion_id = ? AND j.estado = 'confirmada' AND j.lote_id IS NULL
             ORDER BY j.inicio"
        );
        $jh->execute([$id]);
        $horas = array_map(static function ($r) {
            return [
                'id'         => (int) $r['id'],
                'fecha'      => $r['inicio'],
                'fin'        => $r['fin'],
                'horas'      => round(((int) $r['duracion_min']) / 60, 2),
                'trabajador' => $r['trabajador'],
            ];
        }, $jh->fetchAll());

        $jm = $db->prepare(
            "SELECT m.id, m.fecha, m.descripcion, m.cantidad, m.unidad,
                    m.precio_unit, m.origen, u.nombre AS trabajador
             FROM materiales_linea m JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.instalacion_id = ? AND m.lote_id IS NULL
             ORDER BY FIELD(m.origen,'empresa','cliente'), m.fecha"
        );
        $jm->execute([$id]);
        $materiales = array_map(static function ($r) {
            return [
                'id'          => (int) $r['id'],
                'fecha'       => $r['fecha'],
                'descripcion' => $r['descripcion'],
                'cantidad'    => (float) $r['cantidad'],
                'unidad'      => $r['unidad'],
                'precio_unit' => $r['precio_unit'] !== null ? (float) $r['precio_unit'] : null,
                'origen'      => $r['origen'],
                'trabajador'  => $r['trabajador'],
            ];
        }, $jm->fetchAll());

        Http::json(['horas' => $horas, 'materiales' => $materiales]);
    }
}
