<?php
/** /intranet/sin_perfil.php?s=CODIGO — destino de las tarjetas con candado. */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Http;
use Intranet\Portal;
use Intranet\View;

$usuario = Auth::requireLogin();
$codigo  = strtoupper((string) ($_GET['s'] ?? ''));

if (!preg_match('/^[A-Z_]{2,20}$/', $codigo)) {
    Http::redirigir(View::url('INTRANET', ''));
}

// Si mientras tanto le asignaron el perfil, lo llevamos directo al sistema.
foreach (Portal::sistemas($usuario['id']) as $s) {
    if ($s['SistemaCodigo'] === $codigo && (int) $s['TienePerfil'] === 1) {
        Http::redirigir(Portal::urlSistema($s));
    }
}

Auth::rechazarSinPerfil($codigo);
