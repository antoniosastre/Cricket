<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class CatalogoController
{
    // Cualquier usuario autenticado puede leer el catalogo (lo usa la PWA).
    public static function index(): void
    {
        Auth::require();
        $rows = Db::conn()->query(
            'SELECT id, descripcion, unidad, precio FROM materiales_catalogo WHERE activo = 1 ORDER BY descripcion'
        )->fetchAll();
        Http::json($rows);
    }

    public static function create(): void
    {
        Auth::require('admin');
        $b = Http::body();
        $desc = trim((string) ($b['descripcion'] ?? ''));
        if ($desc === '') {
            Http::error('La descripcion es obligatoria');
        }
        $st = Db::conn()->prepare(
            'INSERT INTO materiales_catalogo (descripcion, unidad, precio) VALUES (?,?,?)'
        );
        $st->execute([
            $desc,
            trim((string) ($b['unidad'] ?? 'ud')) ?: 'ud',
            isset($b['precio']) && $b['precio'] !== '' ? (float) $b['precio'] : null,
        ]);
        Http::json(['id' => (int) Db::conn()->lastInsertId()], 201);
    }

    public static function update(int $id): void
    {
        Auth::require('admin');
        $b = Http::body();
        $st = Db::conn()->prepare(
            'UPDATE materiales_catalogo SET descripcion=?, unidad=?, precio=?, activo=? WHERE id=?'
        );
        $st->execute([
            trim((string) ($b['descripcion'] ?? '')),
            trim((string) ($b['unidad'] ?? 'ud')) ?: 'ud',
            isset($b['precio']) && $b['precio'] !== '' ? (float) $b['precio'] : null,
            isset($b['activo']) ? (int) (bool) $b['activo'] : 1,
            $id,
        ]);
        Http::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        Auth::require('admin');
        Db::conn()->prepare('UPDATE materiales_catalogo SET activo = 0 WHERE id = ?')->execute([$id]);
        Http::json(['ok' => true]);
    }
}
