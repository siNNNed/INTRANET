<?php
/** Portada de la intranet. */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Db;
use Intranet\Portal;
use Intranet\View;

$usuario  = Auth::requireLogin();
$sistemas = Portal::sistemas($usuario['id']);

// Perfil en Egresos (null si no tiene)
$perfilEgresos = null;
foreach ($sistemas as $s) {
    if ($s['SistemaCodigo'] === 'EGRESOS' && (int) $s['TienePerfil'] === 1) {
        $perfilEgresos = $s['PerfilCodigo'];
    }
}

// Pendientes según el perfil en Egresos
$pendientes = [];
if (in_array($perfilEgresos, ['DIGITADOR', 'ADMIN'], true)) {
    $c = Db::fila(
        "SELECT SUM(CASE WHEN EstadoId = 'BORRADOR'  THEN 1 ELSE 0 END) AS Borradores,
                SUM(CASE WHEN EstadoId = 'DEVUELTO'  THEN 1 ELSE 0 END) AS Devueltos,
                SUM(CASE WHEN EstadoId = 'PENDIENTE' THEN 1 ELSE 0 END) AS EnAprobacion
           FROM egr.Egresos WHERE CreadoPor = ?",
        [$usuario['id']],
        'EGRESOS'
    ) ?? [];
    $pendientes[] = ['icono' => 'edit',  'tono' => 'azul',    'texto' => 'Borradores por completar', 'n' => (int) ($c['Borradores'] ?? 0)];
    $pendientes[] = ['icono' => 'undo',  'tono' => 'naranjo', 'texto' => 'Devueltos para corregir',  'n' => (int) ($c['Devueltos'] ?? 0)];
    $pendientes[] = ['icono' => 'clock', 'tono' => 'amarillo','texto' => 'En aprobación',            'n' => (int) ($c['EnAprobacion'] ?? 0)];
} elseif ($perfilEgresos === 'APROBADOR') {
    $n = (int) Db::valor('SELECT COUNT(DISTINCT EgresoId) FROM egr.vw_PendientesAprobacion WHERE AprobadorId = ?', [$usuario['id']], 'EGRESOS');
    $pendientes[] = ['icono' => 'check', 'tono' => 'azul', 'texto' => 'Esperando su aprobación', 'n' => $n];
} elseif ($perfilEgresos === 'TESORERIA') {
    $n = (int) Db::valor("SELECT COUNT(*) FROM egr.Egresos WHERE EstadoId = 'APROBADO'", [], 'EGRESOS');
    $pendientes[] = ['icono' => 'wallet', 'tono' => 'azul', 'texto' => 'Aprobados por pagar', 'n' => $n];
}

$totalPendientes = array_sum(array_column($pendientes, 'n'));
$conPerfil = count(array_filter($sistemas, static fn ($s) => (int) $s['TienePerfil'] === 1));

// Saludo y fecha en español
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 20 ? 'Buenas tardes' : 'Buenas noches');
$fecha = class_exists(IntlDateFormatter::class)
    ? (new IntlDateFormatter('es_CL', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'America/Santiago', null, "EEEE d 'de' MMMM 'de' y"))->format(new DateTime())
    : date('d-m-Y');
$primerNombre = explode(' ', trim($usuario['nombre']))[0] ?? $usuario['nombre'];

View::render(dirname(__DIR__) . '/views/portada.php', [
    'titulo'          => 'Portada',
    'sinCabecera'     => true,
    'menu'            => Portal::menu('portada', $sistemas, $usuario),
    'sistemas'        => $sistemas,
    'perfilEgresos'   => $perfilEgresos,
    'pendientes'      => $pendientes,
    'totalPendientes' => $totalPendientes,
    'conPerfil'       => $conPerfil,
    'comunicados'     => Portal::comunicados(3),
    'saludo'          => $saludo . ', ' . $primerNombre,
    'fecha'           => $fecha,
]);
