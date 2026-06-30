<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
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
        $b = Http::body();
        $st = Db::conn()->prepare(
            'UPDATE clientes SET nombre=?, nif=?, direccion=?, telefono=?, email=? WHERE id=?'
        );
        $st->execute([
            trim((string) ($b['nombre'] ?? '')),
            self::nn($b['nif'] ?? null),
            self::nn($b['direccion'] ?? null),
            self::nn($b['telefono'] ?? null),
            self::nn($b['email'] ?? null),
            $id,
        ]);
        Http::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        Auth::require('admin');
        Db::conn()->prepare('DELETE FROM clientes WHERE id=?')->execute([$id]);
        Http::json(['ok' => true]);
    }

    private static function nn($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : (string) $v;
    }
}
