<?php
// Front controller de la API. Todas las peticiones pasan por aqui
// (ver .htaccess). Sin framework: autoloader propio + router.

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 4)) . '.php';
    $path = __DIR__ . '/../src/' . $rel;
    if (is_file($path)) {
        require $path;
    }
});

use App\Http;
use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\TrabajosController;
use App\Controllers\FacturacionController;
use App\Controllers\ClientesController;
use App\Controllers\InstalacionesController;
use App\Controllers\TrabajadoresController;
use App\Controllers\CatalogoController;
use App\Controllers\JornadasController;
use App\Controllers\SyncController;

Http::cors();

// Captura errores no controlados como JSON.
set_exception_handler(static function (\Throwable $e): void {
    Http::error('Error interno', 500);
});

$r = new Router();

// --- Autenticacion ---
$r->post('/auth/login',     [AuthController::class, 'login']);
$r->post('/auth/login-pin', [AuthController::class, 'loginPin']);
$r->post('/auth/refresh',   [AuthController::class, 'refresh']);
$r->get('/auth/me',         [AuthController::class, 'me']);

// --- Portal admin ---
$r->get('/dashboard/estado', [DashboardController::class, 'estado']);
$r->get('/trabajos',         [TrabajosController::class, 'index']);
$r->get('/trabajos/{id}',    [TrabajosController::class, 'show']);

$r->post('/facturacion',                   [FacturacionController::class, 'facturar']);
$r->get('/facturacion/{id}/export.csv',    [FacturacionController::class, 'exportCsv']);
$r->post('/facturacion/{id}/revertir',     [FacturacionController::class, 'revertir']);
$r->get('/archivado',                      [FacturacionController::class, 'archivado']);

// --- CRUD ---
$r->get('/clientes',         [ClientesController::class, 'index']);
$r->post('/clientes',        [ClientesController::class, 'create']);
$r->patch('/clientes/{id}',  [ClientesController::class, 'update']);
$r->delete('/clientes/{id}', [ClientesController::class, 'delete']);

$r->get('/instalaciones',             [InstalacionesController::class, 'index']);
$r->post('/instalaciones',            [InstalacionesController::class, 'create']);
$r->patch('/instalaciones/{id}',      [InstalacionesController::class, 'update']);
$r->post('/instalaciones/{id}/estado',[InstalacionesController::class, 'setEstado']);

$r->get('/trabajadores',         [TrabajadoresController::class, 'index']);
$r->post('/trabajadores',        [TrabajadoresController::class, 'create']);
$r->patch('/trabajadores/{id}',  [TrabajadoresController::class, 'update']);
$r->delete('/trabajadores/{id}', [TrabajadoresController::class, 'delete']);

$r->get('/catalogo',         [CatalogoController::class, 'index']);
$r->post('/catalogo',        [CatalogoController::class, 'create']);
$r->patch('/catalogo/{id}',  [CatalogoController::class, 'update']);
$r->delete('/catalogo/{id}', [CatalogoController::class, 'delete']);

$r->patch('/jornadas/{id}',  [JornadasController::class, 'update']);
$r->delete('/jornadas/{id}', [JornadasController::class, 'delete']);

// --- PWA trabajador ---
$r->post('/sync', [SyncController::class, 'sync']);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Permite servir la API bajo un subdirectorio (p. ej. /api).
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
$r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path ?: '/');
