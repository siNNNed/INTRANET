<?php
/**
 * POST /intranet/api/auth/login_local.php
 * Acceso con contraseña, solo para cuentas LOCAL (emergencia de administradores).
 * Respuesta: {"ok":true,"data":{"redirigir":"/intranet/"}} o error.
 */
require dirname(__DIR__, 4) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Bitacora;
use Intranet\Csrf;
use Intranet\Db;
use Intranet\Http;
use Intranet\Json;
use Intranet\Parametros;
use Intranet\Validator;

Http::requierePost();
Csrf::verificar();

$email    = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

$campos = [];
if (!Validator::email($email)) {
    $campos['email'] = 'Ingrese un correo válido.';
}
if ($password === '' || strlen($password) > 200) {
    $campos['password'] = 'Ingrese su contraseña.';
}
if ($campos) {
    Json::error('VALIDACION', 'Revise los campos marcados.', 400, $campos);
}

$generico = 'Correo o contraseña incorrectos.';
$usuario = Db::fila(
    "SELECT UsuarioId, Nombre, Email, PasswordHash, Activo,
            CASE WHEN BloqueadoHasta > SYSDATETIME() THEN 1 ELSE 0 END AS Bloqueado
       FROM seg.Usuarios
      WHERE Email = ? AND MetodoAcceso = 'LOCAL'",
    [$email]
);

if ($usuario === null) {
    // Mismo costo que una verificación real, para no revelar qué correos existen.
    password_verify($password, '$2y$10$BynGCybrE67cdA9EOtrMseQ0u4m6tJi3rDkrSO8ZijaZCIplyQ0BC');
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', null, null, ['metodo' => 'LOCAL', 'motivo' => 'no_existe', 'email' => $email]);
    Json::error('CREDENCIALES', $generico, 400);
}

$id = (int) $usuario['UsuarioId'];

if ((int) $usuario['Bloqueado'] === 1) {
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', 'Usuario', $id, ['metodo' => 'LOCAL', 'motivo' => 'bloqueado'], $id);
    Json::error('BLOQUEADO', 'La cuenta está bloqueada temporalmente por intentos fallidos. Intente más tarde.', 400);
}

if ((int) $usuario['Activo'] !== 1 || !password_verify($password, (string) $usuario['PasswordHash'])) {
    $maximo  = Parametros::entero('LOGIN_MAX_INTENTOS', 5);
    $minutos = Parametros::entero('LOGIN_MINUTOS_BLOQUEO', 15);
    Db::ejecutar('UPDATE seg.Usuarios SET IntentosFallidos = IntentosFallidos + 1 WHERE UsuarioId = ?', [$id]);
    Db::ejecutar(
        'UPDATE seg.Usuarios
            SET BloqueadoHasta = DATEADD(MINUTE, CAST(? AS INT), SYSDATETIME()), IntentosFallidos = 0
          WHERE UsuarioId = ? AND IntentosFallidos >= CAST(? AS INT)',
        [$minutos, $id, $maximo]
    );
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', 'Usuario', $id, ['metodo' => 'LOCAL', 'motivo' => 'clave'], $id);
    Json::error('CREDENCIALES', $generico, 400);
}

if (password_needs_rehash((string) $usuario['PasswordHash'], PASSWORD_DEFAULT)) {
    Db::ejecutar('UPDATE seg.Usuarios SET PasswordHash = ? WHERE UsuarioId = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
}

Auth::iniciarComo($usuario, 'LOCAL');
Json::ok(['redirigir' => Http::destinoSeguro($_POST['next'] ?? '')]);
