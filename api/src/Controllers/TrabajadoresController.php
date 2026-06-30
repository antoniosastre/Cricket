<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class TrabajadoresController
{
    // Lista de usuarios. La PWA usa ?rol=trabajador para el selector de login.
    public static function index(): void
    {
        $rolFiltro = $_GET['rol'] ?? null;
        // El listado de trabajadores para el login de la PWA es publico
        // (solo expone id y nombre). El resto requiere admin.
        if ($rolFiltro === 'trabajador' && ($_GET['login'] ?? '') === '1') {
            $rows = Db::conn()->query(
                "SELECT id, nombre FROM usuarios WHERE rol='trabajador' AND activo=1 ORDER BY nombre"
            )->fetchAll();
            Http::json($rows);
        }

        Auth::require('admin');
        $rows = Db::conn()->query(
            'SELECT id, nombre, email, rol, tarifa_hora, activo FROM usuarios ORDER BY rol, nombre'
        )->fetchAll();
        Http::json($rows);
    }

    public static function create(): void
    {
        Auth::require('admin');
        $b = Http::body();
        $nombre = trim((string) ($b['nombre'] ?? ''));
        $email  = trim((string) ($b['email'] ?? ''));
        $rol    = ($b['rol'] ?? 'trabajador') === 'admin' ? 'admin' : 'trabajador';
        if ($nombre === '' || $email === '') {
            Http::error('Nombre y email son obligatorios');
        }
        $pass = isset($b['password']) && $b['password'] !== '' ? password_hash((string) $b['password'], PASSWORD_DEFAULT) : null;
        $pin  = isset($b['pin']) && $b['pin'] !== '' ? password_hash((string) $b['pin'], PASSWORD_DEFAULT) : null;
        $tarifa = isset($b['tarifa_hora']) && $b['tarifa_hora'] !== '' ? (float) $b['tarifa_hora'] : null;

        try {
            $st = Db::conn()->prepare(
                'INSERT INTO usuarios (nombre, email, password_hash, pin_hash, rol, tarifa_hora) VALUES (?,?,?,?,?,?)'
            );
            $st->execute([$nombre, $email, $pass, $pin, $rol, $tarifa]);
        } catch (\PDOException $e) {
            Http::error('El email ya esta en uso', 409);
        }
        Http::json(['id' => (int) Db::conn()->lastInsertId()], 201);
    }

    public static function update(int $id): void
    {
        Auth::require('admin');
        $b = Http::body();
        $fields = ['nombre = ?', 'email = ?', 'rol = ?', 'tarifa_hora = ?', 'activo = ?'];
        $vals = [
            trim((string) ($b['nombre'] ?? '')),
            trim((string) ($b['email'] ?? '')),
            ($b['rol'] ?? 'trabajador') === 'admin' ? 'admin' : 'trabajador',
            isset($b['tarifa_hora']) && $b['tarifa_hora'] !== '' ? (float) $b['tarifa_hora'] : null,
            isset($b['activo']) ? (int) (bool) $b['activo'] : 1,
        ];
        if (isset($b['password']) && $b['password'] !== '') {
            $fields[] = 'password_hash = ?';
            $vals[] = password_hash((string) $b['password'], PASSWORD_DEFAULT);
        }
        if (isset($b['pin']) && $b['pin'] !== '') {
            $fields[] = 'pin_hash = ?';
            $vals[] = password_hash((string) $b['pin'], PASSWORD_DEFAULT);
        }
        $vals[] = $id;
        $st = Db::conn()->prepare('UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $st->execute($vals);
        Http::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        Auth::require('admin');
        // Baja logica para preservar historico de jornadas/materiales.
        Db::conn()->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$id]);
        Http::json(['ok' => true]);
    }
}
