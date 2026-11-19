<?php
/** Administración de la intranet: listado de usuarios y sus accesos (perfil ADMIN de INTRANET). */
require dirname(__DIR__, 3) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Db;
use Intranet\Portal;
use Intranet\Usuarios;
use Intranet\View;

Auth::requirePerfil('INTRANET', ['ADMIN']);
$usuario = Auth::usuario();

$sistemas = Db::todas('SELECT Codigo, Nombre FROM seg.Sistemas WHERE Activo = 1 ORDER BY Orden, Nombre');
$perfiles = Db::todas('SELECT Codigo, MIN(Nombre) AS Nombre FROM seg.Perfiles WHERE Activo = 1 GROUP BY Codigo ORDER BY MIN(PerfilId)');

// Filtros: solo valores conocidos (lista blanca).
$codigosSistema = array_column($sistemas, 'Codigo');
$codigosPerfil  = array_merge(array_column($perfiles, 'Codigo'), ['NINGUNO']);
$filtros = [
    'q'       => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
    'sistema' => in_array($_GET['sistema'] ?? '', $codigosSistema, true) ? $_GET['sistema'] : '',
    'perfil'  => in_array($_GET['perfil'] ?? '', $codigosPerfil, true) ? $_GET['perfil'] : '',
    'estado'  => in_array($_GET['estado'] ?? '', Usuarios::ESTADOS, true) ? $_GET['estado'] : 'activos',
];

$flash = $_SESSION['flash_ok'] ?? null;
unset($_SESSION['flash_ok']);

View::render(dirname(__DIR__, 2) . '/views/admin_usuarios.php', [
    'titulo'    => 'Usuarios y accesos',
    'subtitulo' => 'Cuentas de la intranet y su perfil en cada sistema',
    'menu'      => Portal::menu('admin', Portal::sistemas($usuario['id']), $usuario),
    'acciones'  => '<a class="btn btn-primario" href="' . e(View::url('INTRANET', 'admin/usuario.php')) . '">' . View::icono('plus', 16) . 'Nuevo usuario</a>',
    'filas'     => Usuarios::listar($filtros),
    'filtros'   => $filtros,
    'sistemas'  => $sistemas,
    'perfiles'  => $perfiles,
    'flash'     => $flash,
]);
