<?php
namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http;

final class FacturacionController
{
    // Marca como facturadas las jornadas y materiales indicados: crea un lote
    // y les asigna el lote_id. Devuelve el lote creado.
    public static function facturar(): void
    {
        $p = Auth::require('admin');
        $b = Http::body();
        $jornadas   = array_values(array_unique(array_map('intval', (array) ($b['jornadas'] ?? []))));
        $materiales = array_values(array_unique(array_map('intval', (array) ($b['materiales'] ?? []))));
        $referencia = trim((string) ($b['referencia'] ?? ''));

        if (!$jornadas && !$materiales) {
            Http::error('No hay líneas pendientes seleccionadas', 409);
        }

        $db = Db::conn();
        $db->beginTransaction();
        try {
            $st = $db->prepare('INSERT INTO lotes_facturacion (admin_id, referencia) VALUES (?,?)');
            $st->execute([(int) $p['sub'], $referencia !== '' ? $referencia : null]);
            $loteId = (int) $db->lastInsertId();

            $asignadas = 0;
            if ($jornadas) {
                $in = implode(',', array_fill(0, count($jornadas), '?'));
                $st = $db->prepare("UPDATE jornadas j JOIN usuarios u ON u.id=j.usuario_id
                    SET j.lote_id=?, j.tarifa_hora=u.tarifa_hora
                    WHERE j.id IN ($in) AND j.estado='confirmada' AND j.lote_id IS NULL");
                $st->execute([$loteId, ...$jornadas]);
                $asignadas += $st->rowCount();
            }
            if ($materiales) {
                $in = implode(',', array_fill(0, count($materiales), '?'));
                $st = $db->prepare("UPDATE materiales_linea SET lote_id=? WHERE id IN ($in) AND lote_id IS NULL");
                $st->execute([$loteId, ...$materiales]);
                $asignadas += $st->rowCount();
            }
            if (!$asignadas) {
                $db->rollBack();
                Http::error('Ninguna línea seleccionada está pendiente', 409);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Http::error('Error al facturar', 500);
        }

        Http::json(['lote_id' => $loteId], 201);
    }

    // Deshace un lote facturado por error: libera las lineas y borra el lote.
    public static function revertir(int $loteId): void
    {
        Auth::require('admin');
        $db = Db::conn();
        $db->beginTransaction();
        try {
            $existe = $db->prepare('SELECT id FROM lotes_facturacion WHERE id=? FOR UPDATE');
            $existe->execute([$loteId]);
            if (!$existe->fetchColumn()) {
                $db->rollBack();
                Http::error('Lote no encontrado', 404);
            }
            $db->prepare('UPDATE jornadas SET lote_id = NULL, tarifa_hora = NULL WHERE lote_id = ?')->execute([$loteId]);
            $db->prepare('UPDATE materiales_linea SET lote_id = NULL WHERE lote_id = ?')->execute([$loteId]);
            $db->prepare('DELETE FROM lotes_facturacion WHERE id = ?')->execute([$loteId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Http::error('Error al revertir', 500);
        }
        Http::json(['ok' => true]);
    }

    // Exporta a CSV las lineas de un lote con sus importes.
    public static function exportCsv(int $loteId): void
    {
        Auth::require('admin');
        $db = Db::conn();

        $db->beginTransaction();
        $existe = $db->prepare('SELECT id FROM lotes_facturacion WHERE id=?');
        $existe->execute([$loteId]);
        if (!$existe->fetchColumn()) {
            $db->rollBack();
            Http::error('Lote no encontrado', 404);
        }
        $jh = $db->prepare(
            "SELECT i.nombre AS instalacion, c.nombre AS cliente, u.nombre AS trabajador,
                    j.tarifa_hora, j.inicio, j.duracion_min
             FROM jornadas j
             JOIN instalaciones i ON i.id = j.instalacion_id
             LEFT JOIN clientes c ON c.id = i.cliente_id
             JOIN usuarios u ON u.id = j.usuario_id
             WHERE j.lote_id = ? ORDER BY i.nombre, j.inicio"
        );
        $jh->execute([$loteId]);

        $jm = $db->prepare(
            "SELECT i.nombre AS instalacion, c.nombre AS cliente, u.nombre AS trabajador,
                    m.fecha, m.descripcion, m.cantidad, m.unidad, m.precio_unit, m.origen
             FROM materiales_linea m
             JOIN instalaciones i ON i.id = m.instalacion_id
             LEFT JOIN clientes c ON c.id = i.cliente_id
             JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.lote_id = ? ORDER BY i.nombre, FIELD(m.origen,'empresa','cliente'), m.fecha"
        );
        $jm->execute([$loteId]);

        $rows = [];
        $rows[] = ['Tipo', 'Cliente', 'Instalacion', 'Fecha', 'Concepto', 'Trabajador', 'Origen', 'Cantidad', 'Unidad', 'Precio', 'Importe'];

        foreach ($jh->fetchAll() as $r) {
            $horas  = round(((int) $r['duracion_min']) / 60, 2);
            $tarifa = $r['tarifa_hora'] !== null ? (float) $r['tarifa_hora'] : null;
            $importe = $tarifa !== null ? round($horas * $tarifa, 2) : null;
            $rows[] = [
                'Horas', $r['cliente'], $r['instalacion'], $r['inicio'],
                'Mano de obra', $r['trabajador'], '',
                self::num($horas), 'h', self::num($tarifa), self::num($importe),
            ];
        }
        foreach ($jm->fetchAll() as $r) {
            $cant   = (float) $r['cantidad'];
            $precio = $r['precio_unit'] !== null ? (float) $r['precio_unit'] : null;
            $importe = $precio !== null ? round($cant * $precio, 2) : null;
            $rows[] = [
                'Material', $r['cliente'], $r['instalacion'], $r['fecha'],
                $r['descripcion'], $r['trabajador'], $r['origen'],
                self::num($cant), $r['unidad'], self::num($precio), self::num($importe),
            ];
        }

        $db->commit();
        $out = '';
        foreach ($rows as $row) {
            foreach ([0, 1, 2, 3, 4, 5, 6, 8] as $i) {
                if (preg_match('/^[=+@\-\t\r\n]/', (string) $row[$i])) {
                    $row[$i] = "'" . $row[$i];
                }
            }
            $out .= implode(';', array_map([self::class, 'esc'], $row)) . "\r\n";
        }
        Http::csv($out, 'facturacion_lote_' . $loteId . '.csv');
    }

    // Historial: lotes facturados con totales.
    public static function archivado(): void
    {
        Auth::require('admin');
        $sql = "SELECT l.id, l.fecha, l.referencia, a.nombre AS admin,
                       COALESCE(jh.minutos,0) AS minutos,
                       COALESCE(jh.n,0) AS n_horas,
                       COALESCE(mm.n,0) AS n_mat,
                       COALESCE(jh.importe,0) + COALESCE(mm.importe,0) AS total_euros
                FROM lotes_facturacion l
                LEFT JOIN usuarios a ON a.id = l.admin_id
                LEFT JOIN (SELECT lote_id, SUM(duracion_min) minutos, COUNT(*) n,
                           SUM(ROUND(ROUND(duracion_min/60,2)*tarifa_hora,2)) importe FROM jornadas GROUP BY lote_id) jh ON jh.lote_id = l.id
                LEFT JOIN (SELECT lote_id, COUNT(*) n, SUM(ROUND(cantidad*precio_unit,2)) importe FROM materiales_linea GROUP BY lote_id) mm ON mm.lote_id = l.id
                ORDER BY l.fecha DESC";
        $rows = Db::conn()->query($sql)->fetchAll();
        $out = array_map(static function ($r) {
            return [
                'id'          => (int) $r['id'],
                'fecha'       => $r['fecha'],
                'referencia'  => $r['referencia'],
                'admin'       => $r['admin'],
                'total_horas' => round(((int) $r['minutos']) / 60, 2),
                'total_mat'   => (int) $r['n_mat'],
                'total_euros' => (float) $r['total_euros'],
            ];
        }, $rows);
        Http::json($out);
    }

    private static function num($v): string
    {
        if ($v === null) {
            return '';
        }
        // Coma decimal para Excel en espanol.
        return str_replace('.', ',', (string) $v);
    }

    private static function esc($v): string
    {
        $v = (string) $v;
        if (strpbrk($v, ";\"\r\n") !== false) {
            $v = '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }
}
