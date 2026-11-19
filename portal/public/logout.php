<?php
/** Cierra la sesión de toda la intranet (solo por POST con token CSRF). */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Bitacora;
use Intranet\Csrf;
use Intranet\Http;
use Intranet\View;

if (!Http::esPost()) {
    Http::redirigir(View::url('INTRANET', ''));
}
Csrf::verificar();

if (Auth::usuario() !== null) {
    Bitacora::registrar('LOGOUT', 'INTRANET', 'Usuario', Auth::usuario()['id']);
}
Auth::cerrarSesion();
Http::redirigir(View::url('INTRANET', 'login.php'));
