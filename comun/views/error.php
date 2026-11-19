<?php
/**
 * Página de error independiente (no consulta la base).
 * Variables: $titulo, $mensaje, $detalle (solo en desarrollo).
 */
use Intranet\View;
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · Intranet</title>
  <link rel="icon" type="image/png" href="<?= e(View::asset('img/favicon_32.png')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="<?= e(View::asset('css/intranet.css')) ?>" rel="stylesheet">
</head>
<body class="pagina-error">
  <main class="tarjeta pagina-error-caja">
    <img src="<?= e(View::asset('img/logo_oscuro_256.png')) ?>" alt="Salmones Antártica" height="56">
    <h1><?= e($titulo) ?></h1>
    <p><?= e($mensaje) ?></p>
    <?php if (!empty($detalle)): ?><pre><?= e($detalle) ?></pre><?php endif; ?>
    <a class="btn btn-primario" href="<?= e(View::url('INTRANET', '')) ?>">Ir a la portada</a>
  </main>
</body>
</html>
