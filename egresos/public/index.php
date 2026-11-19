<?php
/**
 * Entrada al sistema de Egresos.
 * requireSistema('EGRESOS') corta el paso con "Usted no tiene el perfil para este sistema"
 * si el usuario no tiene perfil vigente. Los módulos se construyen en la etapa 2.
 */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\View;

$acceso = Auth::requireSistema('EGRESOS');

View::render(dirname(__DIR__) . '/views/inicio.php', [
    'titulo'    => 'Egresos',
    'subtitulo' => 'Perfil: ' . $acceso['Perfil'],
    'marca'     => 'Intranet · Egresos',
    'acceso'    => $acceso,
    'menu'      => [
        ['texto' => 'Inicio', 'url' => View::url('EGRESOS', ''), 'icono' => 'home', 'activo' => true],
        ['seccion' => 'Intranet'],
        ['texto' => 'Volver a la portada', 'url' => View::url('INTRANET', ''), 'icono' => 'back'],
    ],
]);
