<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class MaterialesController
{
    // Edicion de una linea de material por el admin. Solo si aun no esta
    // facturada (lote_id IS NULL), para no alterar datos ya facturados.
    public static function update(int $id): void
    {
        Auth::require('admin');
        $db = Db::conn();
        $st = $db->prepare('SELECT * FROM materiales_linea WHERE id = ?');
        $st->execute([$id]);
        $m = $st->fetch();
        if (!$m) {
            Http::error('Linea no encontrada', 404);
        }
        if ($m['lote_id'] !== null) {
            Http::error('La linea ya esta facturada; reviertela antes de editar', 409);
        }
        $b = Http::body();

        $descripcion = array_key_exists('descripcion', $b)
            ? mb_substr(trim((string) $b['descripcion']), 0, 200) : $m['descripcion'];
        if ($descripcion === '') {
            Http::error('La descripcion no puede estar vacia');
        }
        $fecha = array_key_exists('fecha', $b) && $b['fecha']
            ? date('Y-m-d', strtotime((string) $b['fecha'])) : $m['fecha'];
        $cantidad = array_key_exists('cantidad', $b) && $b['cantidad'] !== ''
            ? (float) $b['cantidad'] : $m['cantidad'];
        $unidad = array_key_exists('unidad', $b)
            ? (trim((string) $b['unidad']) ?: 'ud') : $m['unidad'];
        $precio = array_key_exists('precio_unit', $b)
            ? ($b['precio_unit'] === '' || $b['precio_unit'] === null ? null : (float) $b['precio_unit'])
            : $m['precio_unit'];
        $origen = array_key_exists('origen', $b)
            ? (($b['origen'] === 'cliente') ? 'cliente' : 'empresa') : $m['origen'];

        $up = $db->prepare(
            'UPDATE materiales_linea
             SET fecha=?, descripcion=?, cantidad=?, unidad=?, precio_unit=?, origen=?
             WHERE id=?'
        );
        $up->execute([$fecha, $descripcion, $cantidad, $unidad, $precio, $origen, $id]);
        Http::json(['ok' => true]);
    }

    // Elimina una linea de material (solo si no esta facturada).
    public static function delete(int $id): void
    {
        Auth::require('admin');
        Db::conn()->prepare('DELETE FROM materiales_linea WHERE id = ? AND lote_id IS NULL')->execute([$id]);
        Http::json(['ok' => true]);
    }
}
