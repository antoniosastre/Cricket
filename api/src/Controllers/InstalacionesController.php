<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class InstalacionesController
{
    // Listado para el portal (todas) o para la PWA (solo activas).
    // Cualquier usuario autenticado puede listar (la PWA lo necesita).
    public static function index(): void
    {
        Auth::require();
        $soloActivas = ($_GET['estado'] ?? '') === 'activa';
        $sql = 'SELECT i.*, c.nombre AS cliente FROM instalaciones i
                LEFT JOIN clientes c ON c.id = i.cliente_id';
        if ($soloActivas) {
            $sql .= " WHERE i.estado = 'activa'";
        }
        $sql .= ' ORDER BY i.nombre';
        Http::json(Db::conn()->query($sql)->fetchAll());
    }

    // Crear instalacion. Disponible tambien para el trabajador desde la PWA.
    public static function create(): void
    {
        Auth::require();
        $b = Http::body();
        $nombre = trim((string) ($b['nombre'] ?? ''));
        if ($nombre === '') {
            Http::error('El nombre es obligatorio');
        }
        $cliente = isset($b['cliente_id']) && $b['cliente_id'] !== '' ? (int) $b['cliente_id'] : null;
        $st = Db::conn()->prepare(
            'INSERT INTO instalaciones (cliente_id, nombre, direccion, descripcion) VALUES (?,?,?,?)'
        );
        $st->execute([
            $cliente,
            $nombre,
            self::nn($b['direccion'] ?? null),
            self::nn($b['descripcion'] ?? null),
        ]);
        $id = (int) Db::conn()->lastInsertId();
        Http::json(['id' => $id, 'nombre' => $nombre], 201);
    }

    public static function update(int $id): void
    {
        Auth::require('admin');
        $b = Http::body();
        $cliente = isset($b['cliente_id']) && $b['cliente_id'] !== '' ? (int) $b['cliente_id'] : null;
        $st = Db::conn()->prepare(
            'UPDATE instalaciones SET cliente_id=?, nombre=?, direccion=?, descripcion=? WHERE id=?'
        );
        $st->execute([
            $cliente,
            trim((string) ($b['nombre'] ?? '')),
            self::nn($b['direccion'] ?? null),
            self::nn($b['descripcion'] ?? null),
            $id,
        ]);
        Http::json(['ok' => true]);
    }

    // Archivar / reactivar una instalacion.
    public static function setEstado(int $id): void
    {
        Auth::require('admin');
        $b = Http::body();
        $estado = ($b['estado'] ?? '') === 'archivada' ? 'archivada' : 'activa';
        Db::conn()->prepare('UPDATE instalaciones SET estado=? WHERE id=?')->execute([$estado, $id]);
        Http::json(['ok' => true, 'estado' => $estado]);
    }

    private static function nn($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : (string) $v;
    }
}
