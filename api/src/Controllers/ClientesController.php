<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Edicion;
use App\Http;

final class ClientesController
{
    public static function index(): void
    {
        Auth::require('admin');
        $rows = Db::conn()->query('SELECT * FROM clientes ORDER BY nombre')->fetchAll();
        Http::json($rows);
    }

    public static function create(): void
    {
        Auth::require('admin');
        $b = Http::body();
        $nombre = trim((string) ($b['nombre'] ?? ''));
        if ($nombre === '') {
            Http::error('El nombre es obligatorio');
        }
        $st = Db::conn()->prepare(
            'INSERT INTO clientes (nombre, nif, direccion, telefono, email) VALUES (?,?,?,?,?)'
        );
        $st->execute([
            $nombre,
            self::nn($b['nif'] ?? null),
            self::nn($b['direccion'] ?? null),
            self::nn($b['telefono'] ?? null),
            self::nn($b['email'] ?? null),
        ]);
        Http::json(['id' => (int) Db::conn()->lastInsertId()], 201);
    }

    public static function update(int $id): void
    {
        Auth::require('admin');
        $campos = Edicion::campos(Http::body(), ['nombre', 'nif', 'direccion', 'telefono', 'email'], ['nombre']);
        Edicion::guardar(Db::conn(), 'clientes', $id, $campos);
        Http::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        Auth::require('admin');
        $db = Db::conn();
        $db->beginTransaction();
        $st = $db->prepare('SELECT id FROM clientes WHERE id=? FOR UPDATE');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            $db->rollBack();
            Http::error('Cliente no encontrado', 404);
        }
        $st = $db->prepare('SELECT 1 FROM instalaciones WHERE cliente_id=? LIMIT 1');
        $st->execute([$id]);
        if ($st->fetchColumn()) {
            $db->rollBack();
            Http::error('El cliente tiene instalaciones asociadas', 409);
        }
        $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$id]);
        $db->commit();
        Http::json(['ok' => true]);
    }

    private static function nn($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : (string) $v;
    }
}
