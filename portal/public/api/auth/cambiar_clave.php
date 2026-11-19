<?php
/**
 * POST /intranet/api/auth/cambiar_clave.php
 * Cambia la contraseña de quien tiene la sesión: actual, nueva, nueva2.
 * Respuesta: {"ok":true,"data":{"redirigir":"..."}} o error con "campos".
 */
require dirname(__DIR__, 4) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Bitacora;
use Intranet\Csrf;
use Intranet\Db;
use Intranet\Http;
use Intranet\Json;
use Intranet\Validator;

Http::requierePost();
Csrf::verificar();
$usuario = Auth::requireLogin(true);
$id = (int) $usuario['id'];

$actual = (string) ($_POST['actual'] ?? '');
$nueva  = (string) ($_POST['nueva'] ?? '');
$nueva2 = (string) ($_POST['nueva2'] ?? '');

$campos = [];
if ($actual === '' || strlen($actual) > 200) {
    $campos['actual'] = 'Ingrese su contraseña actual.';
}
if (strlen($nueva) > 200 || !Validator::password($nueva)) {
    $campos['nueva'] = 'Mínimo 10 caracteres, con letras y números.';
} elseif (hash_equals($actual, $nueva)) {
    $campos['nueva'] = 'La nueva contraseña debe ser distinta de la actual.';
} elseif (!hash_equals($nueva, $nueva2)) {
    $campos['nueva2'] = 'Las contraseñas no coinciden.';
}
if ($campos) {
    Json::error('VALIDACION', 'Revise los campos marcados.', 400, $campos);
}

$hash = (string) (Db::valor('SELECT PasswordHash FROM seg.Usuarios WHERE UsuarioId = ?', [$id]) ?? '');
if ($hash === '' || !password_verify($actual, $hash)) {
    Bitacora::registrar('CLAVE_FALLIDA', 'INTRANET', 'Usuario', $id);
    Json::error('VALIDACION', 'Revise los campos marcados.', 400, ['actual' => 'La contraseña actual no es correcta.']);
}

Db::ejecutar(
    'UPDATE seg.Usuarios
        SET PasswordHash = ?, DebeCambiarPassword = 0, IntentosFallidos = 0, BloqueadoHasta = NULL, ActualizadoEn = SYSDATETIME()
      WHERE UsuarioId = ?',
    [password_hash($nueva, PASSWORD_DEFAULT), $id]
);
Bitacora::registrar('CLAVE_CAMBIADA', 'INTRANET', 'Usuario', $id);

session_regenerate_id(true);
$_SESSION['usuario']['debe_cambiar'] = false;
$_SESSION['flash_ok'] = 'Su contraseña se cambió correctamente.';

Json::ok(['redirigir' => Http::destinoSeguro($_POST['next'] ?? '')]);
