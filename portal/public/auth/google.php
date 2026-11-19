<?php
/** Inicia el inicio de sesión con Google: redirige a la pantalla de Google. */
require dirname(__DIR__, 3) . '/comun/src/bootstrap.php';

use Intranet\GoogleOidc;
use Intranet\Http;
use Intranet\View;

if (!GoogleOidc::configurado()) {
    $_SESSION['flash_error'] = 'no_configurado';
    Http::redirigir(View::url('INTRANET', 'login.php'));
}

$destino = Http::destinoSeguro($_GET['next'] ?? '');
Http::redirigir(GoogleOidc::urlAutorizacion($destino));
