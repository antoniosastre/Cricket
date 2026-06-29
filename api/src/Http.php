<?php
namespace App;

// Utilidades para leer la peticion y emitir respuestas JSON.
final class Http
{
    // Cuerpo JSON de la peticion como array asociativo.
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }

    // Envia el contenido como descarga CSV.
    public static function csv(string $content, string $filename): void
    {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // BOM para que Excel reconozca utf-8.
        echo "\xEF\xBB\xBF" . $content;
        exit;
    }

    public static function cors(): void
    {
        $origins = Config::get('cors_origins', '*');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origins === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin && in_array($origin, (array) $origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
