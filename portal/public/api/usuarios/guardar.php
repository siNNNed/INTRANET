<?php
/**
 * POST /intranet/api/usuarios/guardar.php  (perfil ADMIN de INTRANET)
 * Crea o edita una cuenta y sus accesos por sistema.
 * Respuesta: {"ok":true,"data":{"id":N,"creado":bool,"redirigir":"/intranet/admin/"}} o error con "campos".
 */
require dirname(__DIR__, 4) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Csrf;
use Intranet\Http;
use Intranet\Json;
use Intranet\Usuarios;
use Intranet\View;

Http::requierePost();
Csrf::verificar();
Auth::requirePerfil('INTRANET', ['ADMIN']);
$admin = Auth::usuario();

$r = Usuarios::guardar($_POST, (int) $admin['id']);

// Si se editó a sí mismo, refresca el nombre y el correo de la sesión.
if ($r['id'] === (int) $admin['id']) {
    $u = Usuarios::obtener($r['id']);
    $_SESSION['usuario']['nombre'] = (string) $u['Nombre'];
    $_SESSION['usuario']['email']  = (string) $u['Email'];
}

$_SESSION['flash_ok'] = $r['creado'] ? 'Usuario creado.' : 'Cambios guardados.';
Json::ok($r + ['redirigir' => View::url('INTRANET', 'admin/')]);
