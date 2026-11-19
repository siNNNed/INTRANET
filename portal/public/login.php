<?php
/** Inicio de sesión de la intranet: correo y contraseña. */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Csrf;
use Intranet\Http;
use Intranet\View;

$destino = Http::destinoSeguro($_GET['next'] ?? '');
if (Auth::usuario() !== null) {
    Http::redirigir($destino);
}

$codigo = (string) ($_SESSION['flash_error'] ?? '');   // '' = sin error (PHP 8.5 no acepta null como índice)
unset($_SESSION['flash_error'], $_SESSION['flash_email']);

$mensajes = [
    'no_habilitado'   => 'Su cuenta no está habilitada en la intranet. Contacte al administrador.',
    'sesion_expirada' => 'Su sesión se cerró por inactividad. Vuelva a ingresar.',
];
$mensaje = $codigo !== '' ? ($mensajes[$codigo] ?? null) : null;
$csrf = Csrf::token();
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>Iniciar sesión · Intranet Salmones Antártica</title>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(View::asset('img/favicon_32.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(View::asset('css/intranet.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="login">
  <section class="login-marca">
    <div class="login-marca-logo">
      <img src="<?= e(View::asset('img/logo_blanco_256.png')) ?>" alt="Salmones Antártica">
      <span><strong>Salmones Antártica S.A.</strong><small>Intranet corporativa</small></span>
    </div>
    <div>
      <h1>Intranet</h1>
      <p>Un solo acceso a los sistemas internos de la empresa.</p>
      <ul class="login-ventajas">
        <li><span class="icono"><?= View::icono('lock') ?></span><span><strong>Su cuenta personal</strong><small>Entre con su correo y su contraseña.</small></span></li>
        <li><span class="icono"><?= View::icono('layers') ?></span><span><strong>Accesos por perfil</strong><small>Cada sistema muestra solo lo que su perfil permite.</small></span></li>
        <li><span class="icono"><?= View::icono('wallet') ?></span><span><strong>Egresos</strong><small>Registro, aprobación y pago de egresos.</small></span></li>
      </ul>
    </div>
    <p class="login-pie">Versión 1.0 · Uso interno</p>
  </section>

  <main class="login-panel">
    <div class="login-caja">
      <div>
        <h2>Bienvenido</h2>
        <p class="bajada">Ingrese con su correo y contraseña.</p>
      </div>

      <div class="alerta alerta-error" id="login-error" role="alert" <?= $mensaje ? '' : 'hidden' ?>>
        <?= View::icono('alert', 16) ?><span data-texto><?= e($mensaje) ?></span>
      </div>

      <form id="form-login" method="post" action="" novalidate
            data-url="<?= e(View::url('INTRANET', 'api/auth/login.php')) ?>">
        <input type="hidden" name="next" value="<?= e($destino) ?>">
        <div class="mb-3">
          <label class="form-label" for="email">Correo</label>
          <input class="form-control" type="email" id="email" name="email" autocomplete="username" maxlength="150" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Contraseña</label>
          <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primario w-100 justify-content-center">Ingresar</button>
      </form>

      <p class="login-ayuda">¿Olvidó su contraseña? Pídale al administrador de la intranet que le asigne una nueva.</p>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="<?= e(View::asset('js/intranet.js')) ?>"></script>
<script src="<?= e(View::url('INTRANET', 'assets/login.js')) ?>"></script>
</body>
</html>
