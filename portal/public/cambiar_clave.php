<?php
/** Cambio de la propia contraseña. Obligatorio cuando el administrador la asignó o la reinició. */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Http;
use Intranet\Portal;
use Intranet\View;

$usuario = Auth::requireLogin(true);
$obligatorio = !empty($usuario['debe_cambiar']);

View::render(dirname(__DIR__) . '/views/cambiar_clave.php', [
    'titulo'      => 'Cambiar contraseña',
    'subtitulo'   => $usuario['nombre'] . ' · ' . $usuario['email'],
    'menu'        => $obligatorio ? [] : Portal::menu('', Portal::sistemas($usuario['id']), $usuario),
    'obligatorio' => $obligatorio,
    'destino'     => Http::destinoSeguro($_GET['next'] ?? ''),
    'scripts'     => [View::url('INTRANET', 'assets/cambiar_clave.js')],
]);
