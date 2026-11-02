<?php
namespace App;

use PDO;

final class Edicion
{
    // Solo se escriben columnas de la lista fija del controlador.
    public static function campos(array $entrada, array $permitidos, array $obligatorios): array
    {
        $campos = array_intersect_key($entrada, array_flip($permitidos));
        foreach ($campos as $clave => &$valor) {
            if (!is_scalar($valor) && $valor !== null) {
                Http::error('Valor no válido para ' . $clave);
            }
            $valor = is_string($valor) ? trim($valor) : $valor;
            if ($valor === '' || $valor === null) {
                if (in_array($clave, $obligatorios, true)) {
                    Http::error('El campo ' . $clave . ' es obligatorio');
                }
                $valor = null;
            }
        }
        return $campos;
    }

    public static function guardar(PDO $db, string $tabla, int $id, array $campos): void
    {
        if ($campos) {
            $asignaciones = implode('=?, ', array_keys($campos)) . '=?';
            $st = $db->prepare("UPDATE $tabla SET $asignaciones WHERE id=?");
            $st->execute([...array_values($campos), $id]);
            if ($st->rowCount()) {
                return;
            }
        }
        $st = $db->prepare("SELECT id FROM $tabla WHERE id=?");
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            Http::error('Registro no encontrado', 404);
        }
    }
}
