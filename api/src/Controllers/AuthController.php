<?php
namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Db;
use App\Http;
use App\Jwt;

final class AuthController
{
    // Login de administrador: email + contrasena.
    public static function login(): void
    {
        $b = Http::body();
        $email = trim((string) ($b['email'] ?? ''));
        $pass  = (string) ($b['password'] ?? '');
        if ($email === '' || $pass === '') {
            Http::error('Email y contrasena requeridos');
        }
        $st = Db::conn()->prepare(
            'SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1'
        );
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || !$u['password_hash'] || !password_verify($pass, $u['password_hash'])) {
            Http::error('Credenciales invalidas', 401);
        }
        self::issue($u);
    }

    // Login de trabajador: usuario (id o email) + PIN. Sesion larga.
    public static function loginPin(): void
    {
        $b = Http::body();
        $ident = trim((string) ($b['usuario'] ?? $b['email'] ?? ''));
        $pin   = (string) ($b['pin'] ?? '');
        if ($ident === '' || $pin === '') {
            Http::error('Usuario y PIN requeridos');
        }
        $st = Db::conn()->prepare(
            'SELECT * FROM usuarios WHERE (email = ? OR id = ?) AND activo = 1 LIMIT 1'
        );
        $st->execute([$ident, is_numeric($ident) ? (int) $ident : 0]);
        $u = $st->fetch();
        if (!$u || !$u['pin_hash'] || !password_verify($pin, $u['pin_hash'])) {
            Http::error('Credenciales invalidas', 401);
        }
        self::issue($u);
    }

    // Renueva el access token a partir de un refresh token valido.
    public static function refresh(): void
    {
        $b = Http::body();
        $token = (string) ($b['refresh'] ?? '');
        $payload = $token ? Jwt::verify($token) : null;
        if (!$payload || ($payload['typ'] ?? '') !== 'refresh') {
            Http::error('Refresh token invalido', 401);
        }
        $st = Db::conn()->prepare('SELECT * FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1');
        $st->execute([(int) $payload['sub']]);
        $u = $st->fetch();
        if (!$u) {
            Http::error('Usuario no encontrado', 401);
        }
        self::issue($u);
    }

    // Datos del usuario autenticado.
    public static function me(): void
    {
        $p = Auth::require();
        $st = Db::conn()->prepare('SELECT id, nombre, email, rol, tarifa_hora FROM usuarios WHERE id = ?');
        $st->execute([(int) $p['sub']]);
        Http::json($st->fetch() ?: []);
    }

    private static function issue(array $u): void
    {
        $claims = ['sub' => (int) $u['id'], 'rol' => $u['rol'], 'nombre' => $u['nombre']];
        $access  = Jwt::sign($claims, (int) Config::get('access_ttl', 3600));
        $refresh = Jwt::sign(array_merge($claims, ['typ' => 'refresh']), (int) Config::get('refresh_ttl', 5184000));
        Http::json([
            'access'  => $access,
            'refresh' => $refresh,
            'user'    => [
                'id'          => (int) $u['id'],
                'nombre'      => $u['nombre'],
                'email'       => $u['email'],
                'rol'         => $u['rol'],
                'tarifa_hora' => $u['tarifa_hora'],
            ],
        ]);
    }
}
