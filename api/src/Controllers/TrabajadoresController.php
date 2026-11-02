<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;
use App\Edicion;

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
        $actor = Auth::require('admin');
        $b = Http::body();
        self::cambiar($id, $b, (int) $actor['sub']);
    }

    public static function delete(int $id): void
    {
        $actor = Auth::require('admin');
        self::cambiar($id, ['activo' => 0], (int) $actor['sub']);
    }

    private static function cambiar(int $id, array $b, int $actor): void
    {
        $campos = Edicion::campos($b, ['nombre','email','rol','tarifa_hora','activo'], ['nombre','email','rol','activo']);
        if (isset($campos['rol']) && !in_array($campos['rol'], ['trabajador','admin'], true)) {
            Http::error('Rol no válido');
        }
        if (isset($campos['email']) && !filter_var($campos['email'], FILTER_VALIDATE_EMAIL)) {
            Http::error('Email no válido');
        }
        foreach (['password' => 'password_hash', 'pin' => 'pin_hash'] as $clave => $campo) {
            if (isset($b[$clave]) && $b[$clave] !== '') {
                $campos[$campo] = password_hash((string) $b[$clave], PASSWORD_DEFAULT);
            }
        }
        $db = Db::conn();
        $db->beginTransaction();
        try {
            // Orden estable también cuando dos administradores intentan darse de baja entre sí.
            $admins = $db->query("SELECT id FROM usuarios WHERE rol='admin' AND activo=1 ORDER BY id FOR UPDATE")
                        ->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array($actor, $admins)) {
                Http::error('La cuenta administradora ya no está activa', 403);
            }
            $pierdeAcceso = (isset($campos['activo']) && !$campos['activo']) || ($campos['rol'] ?? 'admin') !== 'admin';
            if ($pierdeAcceso && ($id === $actor || (count($admins) === 1 && in_array($id, $admins)))) {
                Http::error('No se puede retirar este acceso de administración', 409);
            }
            $st = $db->prepare('SELECT rol, password_hash, pin_hash FROM usuarios WHERE id=? FOR UPDATE');
            $st->execute([$id]);
            $actual = $st->fetch();
            if (!$actual) {
                Http::error('Trabajador no encontrado', 404);
            }
            if (isset($campos['rol']) && $campos['rol'] !== $actual['rol']) {
                $credencial = $campos['rol'] === 'admin' ? 'password_hash' : 'pin_hash';
                if (empty($campos[$credencial]) && empty($actual[$credencial])) {
                    Http::error('Falta la credencial para el nuevo rol');
                }
            }
            Edicion::guardar($db, 'usuarios', $id, $campos);
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            if (($e->errorInfo[1] ?? 0) === 1062) {
                Http::error('El email ya está en uso', 409);
            }
            throw $e;
        }
        Http::json(['ok' => true]);
    }
}
