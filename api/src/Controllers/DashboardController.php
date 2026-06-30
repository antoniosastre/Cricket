<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class DashboardController
{
    // Estado de cada trabajador: si tiene el temporizador encendido y donde.
    public static function estado(): void
    {
        Auth::require('admin');
        $sql = "SELECT u.id, u.nombre,
                       j.id            AS jornada_id,
                       j.inicio        AS inicio,
                       i.id            AS instalacion_id,
                       i.nombre        AS instalacion
                FROM usuarios u
                LEFT JOIN jornadas j
                       ON j.usuario_id = u.id AND j.estado = 'en_curso' AND j.fin IS NULL
                LEFT JOIN instalaciones i ON i.id = j.instalacion_id
                WHERE u.rol = 'trabajador' AND u.activo = 1
                ORDER BY u.nombre";
        $rows = Db::conn()->query($sql)->fetchAll();
        $out = array_map(static function ($r) {
            return [
                'usuario_id'     => (int) $r['id'],
                'nombre'         => $r['nombre'],
                'activo'         => $r['jornada_id'] !== null,
                'jornada_id'     => $r['jornada_id'] ? (int) $r['jornada_id'] : null,
                'inicio'         => $r['inicio'],
                'instalacion_id' => $r['instalacion_id'] ? (int) $r['instalacion_id'] : null,
                'instalacion'    => $r['instalacion'],
            ];
        }, $rows);
        Http::json($out);
    }
}
