<?php
/** Inicio de sesión de la intranet: Google Workspace y, como respaldo, cuenta LOCAL. */
require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Csrf;
use Intranet\GoogleOidc;
use Intranet\Http;
use Intranet\Parametros;
use Intranet\View;

$destino = Http::destinoSeguro($_GET['next'] ?? '');
if (Auth::usuario() !== null) {
    Http::redirigir($destino);
}

$codigo = $_SESSION['flash_error'] ?? null;
$emailFlash = $_SESSION['flash_email'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_email']);

$mensajes = [
    'dominio'         => 'La cuenta {email} no pertenece a un dominio autorizado. Use su correo de la empresa.',
    'no_habilitado'   => 'La cuenta {email} no está habilitada en la intranet. Contacte al administrador.',
    'cuenta_distinta' => 'Esta cuenta de Google no coincide con la registrada para su correo. Contacte al administrador.',
    'estado'          => 'El inicio de sesión expiró o no es válido. Intente nuevamente.',
    'cancelado'       => 'Se canceló el inicio de sesión con Google.',
    'google'          => 'No se pudo completar el inicio de sesión con Google. Intente nuevamente.',
    'sesion_expirada' => 'Su sesión se cerró por inactividad. Vuelva a ingresar.',
    'no_configurado'  => 'El inicio de sesión con Google todavía no está configurado. Contacte a TI.',
];
$mensaje = isset($mensajes[$codigo])
    ? str_replace('{email}', $emailFlash !== null ? $emailFlash : 'indicada', $mensajes[$codigo])
    : null;

$dominios = [];
try {
    $dominios = Parametros::dominiosGoogle();
} catch (Throwable $e) {
    error_log('login: no se pudieron leer los dominios: ' . $e->getMessage());
}
$googleListo = GoogleOidc::configurado();
$mostrarLocal = isset($_GET['local']);
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
      <p>Un solo acceso a los sistemas internos, con su cuenta de Google Workspace.</p>
      <ul class="login-ventajas">
        <li><span class="icono"><?= View::icono('shield') ?></span><span><strong>Su cuenta de Google</strong><small>Entre con su correo de la empresa.</small></span></li>
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
        <p class="bajada">Ingrese con su cuenta corporativa de Google Workspace.</p>
      </div>

      <a class="btn btn-google" href="<?= e(View::url('INTRANET', 'auth/google.php') . '?next=' . rawurlencode($destino)) ?>"
         <?= $googleListo ? '' : 'aria-disabled="true" tabindex="-1"' ?>>Iniciar sesión con Google</a>

      <?php if ($dominios): ?>
        <div class="alerta alerta-info">
          <?= View::icono('shield', 16) ?>
          <span>Solo cuentas <?php foreach ($dominios as $i => $d): ?><?= $i ? ($i === count($dominios) - 1 ? ' y ' : ', ') : '' ?><strong>@<?= e($d) ?></strong><?php endforeach; ?> habilitadas por el administrador.</span>
        </div>
      <?php endif; ?>

      <?php if (!$googleListo): ?>
        <div class="alerta alerta-info"><?= View::icono('alert', 16) ?><span>El inicio con Google todavía no está configurado. Mientras tanto, use el acceso con contraseña.</span></div>
      <?php endif; ?>

      <div class="alerta alerta-error" id="login-error" role="alert" <?= $mensaje ? '' : 'hidden' ?>>
        <?= View::icono('alert', 16) ?><span data-texto><?= e($mensaje) ?></span>
      </div>

      <div class="login-separador">o</div>

      <button type="button" class="login-enlace-local" id="mostrar-local" aria-expanded="<?= $mostrarLocal ? 'true' : 'false' ?>" aria-controls="form-local">
        Acceso con contraseña (solo administradores)
      </button>

      <form id="form-local" method="post" action="" novalidate <?= $mostrarLocal ? '' : 'hidden' ?>
            data-url="<?= e(View::url('INTRANET', 'api/auth/login_local.php')) ?>">
        <input type="hidden" name="next" value="<?= e($destino) ?>">
        <div class="mb-3">
          <label class="form-label" for="email">Correo</label>
          <input class="form-control" type="email" id="email" name="email" autocomplete="username" maxlength="150" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Contraseña</label>
          <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primario w-100 justify-content-center">Ingresar</button>
      </form>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="<?= e(View::asset('js/intranet.js')) ?>"></script>
<script src="<?= e(View::url('INTRANET', 'assets/login.js')) ?>"></script>
</body>
</html>
