<?php
/**
 * Layout común: menú lateral + encabezado + contenido.
 * Variables: $titulo, $subtitulo, $menu, $acciones (HTML confiable), $marca, $sinCabecera,
 *            $usuario, $csrf, $contenido.
 */
use Intranet\View;

$menu        = $menu ?? [];
$marca       = $marca ?? 'Intranet';
$sinCabecera = $sinCabecera ?? false;
$iniciales   = '';
if (!empty($usuario['nombre'])) {
    foreach (array_slice(preg_split('/\s+/', trim($usuario['nombre'])) ?: [], 0, 2) as $parte) {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title><?= e($titulo ?? 'Intranet') ?> · Intranet Salmones Antártica</title>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(View::asset('img/favicon_32.png')) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= e(View::asset('img/favicon_192.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(View::asset('css/intranet.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app">
  <nav class="app-menu" aria-label="Menú principal">
    <a class="app-marca" href="<?= e(View::url('INTRANET', '')) ?>">
      <img src="<?= e(View::asset('img/logo_blanco_256.png')) ?>" alt="Salmones Antártica">
      <span><strong><?= e($marca) ?></strong><small>Salmones Antártica S.A.</small></span>
    </a>

    <?php foreach ($menu as $item): ?>
      <?php if (isset($item['seccion'])): ?>
        <div class="app-menu-seccion"><?= e($item['seccion']) ?></div>
      <?php else: ?>
        <a href="<?= e($item['url']) ?>"
           class="app-menu-item<?= !empty($item['activo']) ? ' activo' : '' ?><?= !empty($item['bloqueado']) ? ' bloqueado' : '' ?>"
           <?= !empty($item['activo']) ? 'aria-current="page"' : '' ?>>
          <?= View::icono($item['icono'] ?? 'dot') ?>
          <span><?= e($item['texto']) ?></span>
          <?php if (!empty($item['bloqueado'])): ?>
            <span class="app-menu-candado" title="Sin acceso"><?= View::icono('lock', 14) ?><span class="visually-hidden">Sin acceso</span></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="app-menu-espacio"></div>

    <?php if ($usuario): ?>
      <div class="app-usuario">
        <span class="app-avatar" aria-hidden="true"><?= e($iniciales) ?></span>
        <span class="app-usuario-texto">
          <strong><?= e($usuario['nombre']) ?></strong>
          <small><?= e($usuario['email']) ?></small>
        </span>
        <a class="app-salir" href="<?= e(View::url('INTRANET', 'cambiar_clave.php')) ?>" aria-label="Cambiar contraseña" title="Cambiar contraseña"><?= View::icono('key') ?></a>
        <form method="post" action="<?= e(View::url('INTRANET', 'logout.php')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button type="submit" class="app-salir" aria-label="Cerrar sesión" title="Cerrar sesión"><?= View::icono('logout') ?></button>
        </form>
      </div>
    <?php endif; ?>
  </nav>

  <div class="app-cuerpo">
    <?php if (!$sinCabecera): ?>
      <header class="app-cabecera">
        <div>
          <h1><?= e($titulo ?? '') ?></h1>
          <?php if (!empty($subtitulo)): ?><p><?= e($subtitulo) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($acciones)): ?><div class="app-acciones"><?= $acciones ?></div><?php endif; ?>
      </header>
    <?php endif; ?>
    <main class="app-contenido">
      <?php if (!empty($_SESSION['flash_ok'])): ?>
        <div class="alerta alerta-ok" role="status"><?= View::icono('check', 16) ?><span><?= e($_SESSION['flash_ok']) ?></span></div>
        <?php unset($_SESSION['flash_ok']); ?>
      <?php endif; ?>
      <?= $contenido ?>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(View::asset('js/intranet.js')) ?>"></script>
<?php if (!empty($scripts)) foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
