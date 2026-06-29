<?php
// Ejecuta las migraciones SQL y los datos semilla.
//   php migrations/migrate.php
// Requiere config/config.php configurado.

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Db.php';

use App\Db;

$db = Db::conn();

// 1) Esquema
$schema = file_get_contents(__DIR__ . '/001_schema.sql');
foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
    if ($stmt !== '') {
        $db->exec($stmt);
    }
}
echo "Esquema aplicado.\n";

// 2) Semilla (solo si no hay usuarios todavia)
$count = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($count === 0) {
    $admin = password_hash('admin1234', PASSWORD_DEFAULT);
    $pin   = password_hash('1234', PASSWORD_DEFAULT);

    $db->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?,?,?,?)')
       ->execute(['Administrador', 'admin@empresa.com', $admin, 'admin']);

    $db->prepare('INSERT INTO usuarios (nombre, email, pin_hash, rol, tarifa_hora) VALUES (?,?,?,?,?)')
       ->execute(['Juan Electricista', 'juan@empresa.com', $pin, 'trabajador', 25.00]);
    $db->prepare('INSERT INTO usuarios (nombre, email, pin_hash, rol, tarifa_hora) VALUES (?,?,?,?,?)')
       ->execute(['Marta Electricista', 'marta@empresa.com', $pin, 'trabajador', 25.00]);

    $db->prepare('INSERT INTO clientes (nombre, nif, direccion) VALUES (?,?,?)')
       ->execute(['Comunidad Las Flores', 'H12345678', 'C/ Mayor 1']);
    $clienteId = (int) $db->lastInsertId();

    $db->prepare('INSERT INTO instalaciones (cliente_id, nombre, direccion) VALUES (?,?,?)')
       ->execute([$clienteId, 'Portal 1 - Cuadro general', 'C/ Mayor 1']);

    $cat = [
        ['Cable libre de halogenos 1.5mm', 'm', 0.45],
        ['Cable libre de halogenos 2.5mm', 'm', 0.65],
        ['Magnetotermico 16A', 'ud', 8.50],
        ['Diferencial 40A 30mA', 'ud', 28.00],
        ['Base de enchufe schuko', 'ud', 3.20],
        ['Caja de empalmes', 'ud', 1.80],
    ];
    $stc = $db->prepare('INSERT INTO materiales_catalogo (descripcion, unidad, precio) VALUES (?,?,?)');
    foreach ($cat as $c) {
        $stc->execute($c);
    }

    echo "Semilla creada. Admin: admin@empresa.com / admin1234. Trabajadores PIN: 1234.\n";
} else {
    echo "Ya habia datos; semilla omitida.\n";
}

echo "Listo.\n";
