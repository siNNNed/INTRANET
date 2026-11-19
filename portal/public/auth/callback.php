<?php
/**
 * Regreso desde Google. Esta es la "URL de redireccionamiento autorizada"
 * que se registra en Google Cloud, por ejemplo:
 *   https://intranet.nissui.cl/intranet/auth/callback.php
 */
require dirname(__DIR__, 3) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Bitacora;
use Intranet\Db;
use Intranet\GoogleOidc;
use Intranet\Http;
use Intranet\LoginError;
use Intranet\View;

$volverAlLogin = static function (string $codigo, ?string $email = null): never {
    $_SESSION['flash_error'] = $codigo;
    if ($email !== null) {
        $_SESSION['flash_email'] = $email;
    }
    Http::redirigir(View::url('INTRANET', 'login.php'));
};

try {
    $google = GoogleOidc::procesarRetorno($_GET);
} catch (LoginError $e) {
    error_log('Login Google rechazado: ' . $e->getMessage());
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', null, null, [
        'metodo' => 'GOOGLE', 'motivo' => $e->codigo, 'email' => $e->email,
    ]);
    $volverAlLogin($e->codigo, $e->email);
}

$usuario = Db::fila(
    "SELECT UsuarioId, Nombre, Email, GoogleSub, Activo
       FROM seg.Usuarios
      WHERE Email = ? AND MetodoAcceso = 'GOOGLE'",
    [$google['email']]
);

if ($usuario === null || (int) $usuario['Activo'] !== 1) {
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', 'Usuario', $usuario ? (int) $usuario['UsuarioId'] : null, [
        'metodo' => 'GOOGLE', 'motivo' => 'no_habilitado', 'email' => $google['email'],
    ]);
    $volverAlLogin('no_habilitado', $google['email']);
}

if ($usuario['GoogleSub'] === null) {
    // Primer ingreso: se vincula la cuenta de Google con el usuario.
    Db::ejecutar(
        'UPDATE seg.Usuarios SET GoogleSub = ?, ActualizadoEn = SYSDATETIME() WHERE UsuarioId = ? AND GoogleSub IS NULL',
        [$google['sub'], $usuario['UsuarioId']]
    );
} elseif (!hash_equals((string) $usuario['GoogleSub'], $google['sub'])) {
    // El correo existe pero lo usa otra cuenta de Google (por ejemplo, un correo reasignado).
    Bitacora::registrar('LOGIN_FALLIDO', 'INTRANET', 'Usuario', (int) $usuario['UsuarioId'], [
        'metodo' => 'GOOGLE', 'motivo' => 'cuenta_distinta',
    ]);
    $volverAlLogin('cuenta_distinta');
}

Auth::iniciarComo($usuario, 'GOOGLE');
Http::redirigir(Http::destinoSeguro($google['destino']));
