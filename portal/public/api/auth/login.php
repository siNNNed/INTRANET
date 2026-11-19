<?php
/**
 * POST /intranet/api/auth/login.php
 * Inicio de sesión con correo y contraseña.
 * Respuesta: {"ok":true,"data":{"redirigir":"/intranet/"}} o error.
 * Si la persona debe cambiar su contraseña, la envía a cambiar_clave.php.
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
use Intranet\View;

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
    'SELECT UsuarioId, Nombre, Email, PasswordHash, Activo, DebeCambiarPassword,
            CASE WHEN BloqueadoHasta > SYSDATETIME() THEN 1 ELSE 0 END AS Bloqueado
       FROM seg.Usuarios
      WHERE Email = ?',
    [$email]
);

if ($usuario === null) {
    // Mismo costo que una verificación real, para no revelar qué correos existen.
    password_verify($password, '$2y$10$BynGCybrE67cdA9EOtrMseQ0u4m6tJi3rDkrSO8ZijaZCIplyQ0BC');
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', null, null, ['motivo' => 'no_existe', 'email' => $email]);
    Json::error('CREDENCIALES', $generico, 400);
}

$id = (int) $usuario['UsuarioId'];

if ((int) $usuario['Bloqueado'] === 1) {
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', 'Usuario', $id, ['motivo' => 'bloqueado'], $id);
    Json::error('BLOQUEADO', 'La cuenta está bloqueada temporalmente por intentos fallidos. Intente más tarde.', 400);
}

// Sin contraseña asignada (PasswordHash NULL): no puede entrar; se trata igual que una clave incorrecta.
$hash = (string) ($usuario['PasswordHash'] ?? '');
if ((int) $usuario['Activo'] !== 1 || $hash === '' || !password_verify($password, $hash)) {
    $maximo  = Parametros::entero('LOGIN_MAX_INTENTOS', 5);
    $minutos = Parametros::entero('LOGIN_MINUTOS_BLOQUEO', 15);
    Db::ejecutar('UPDATE seg.Usuarios SET IntentosFallidos = IntentosFallidos + 1 WHERE UsuarioId = ?', [$id]);
    Db::ejecutar(
        'UPDATE seg.Usuarios
            SET BloqueadoHasta = DATEADD(MINUTE, CAST(? AS INT), SYSDATETIME()), IntentosFallidos = 0
          WHERE UsuarioId = ? AND IntentosFallidos >= CAST(? AS INT)',
        [$minutos, $id, $maximo]
    );
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', 'Usuario', $id, ['motivo' => (int) $usuario['Activo'] !== 1 ? 'inactivo' : 'clave'], $id);
    Json::error('CREDENCIALES', $generico, 400);
}

if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
    Db::ejecutar('UPDATE seg.Usuarios SET PasswordHash = ? WHERE UsuarioId = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
}

Auth::iniciarComo($usuario);
$destino = Http::destinoSeguro($_POST['next'] ?? '');
if ((int) $usuario['DebeCambiarPassword'] === 1) {
    $destino = View::url('INTRANET', 'cambiar_clave.php') . '?next=' . rawurlencode($destino);
}
Json::ok(['redirigir' => $destino]);
