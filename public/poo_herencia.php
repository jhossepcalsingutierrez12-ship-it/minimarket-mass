<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/../models/Cajero.php';
require __DIR__ . '/../models/Supervisor.php';
require __DIR__ . '/../models/Administrador.php';

// Datos inventados para la prueba
$usuarios = [
    new Cajero(3, 'Rosa', 'Quispe'),
    new Supervisor(2, 'Carlos', 'Huamán'),
    new Administrador(1, 'Julia', 'Mamani'),
];

$sn = fn(bool $b): string => $b ? 'sí' : 'no';

foreach ($usuarios as $u) {
    echo $u->getNombreCompleto() . ' · ' . $u->rol()
       . ' · anula ventas: ' . $sn($u->puedeAnularVenta())
       . ' · autoriza mermas: ' . $sn($u->puedeAutorizarMerma())
       . ' · ve reportes: ' . $sn($u->puedeVerReportes()) . "\n";
}

// CK_MER_6: el supervisor 2 no puede autorizar una merma que él mismo registró
$sup = $usuarios[1];
echo "\nSupervisor autoriza merma registrada por la cajera (3): " . $sn($sup->autorizaMermaDe(3)) . "\n";
echo "Supervisor autoriza merma registrada por él mismo (2): " . $sn($sup->autorizaMermaDe(2)) . "\n";
