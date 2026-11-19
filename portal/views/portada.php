<?php
/**
 * Portada. Variables: $sistemas, $perfilEgresos, $pendientes, $totalPendientes, $conPerfil,
 * $comunicados, $saludo, $fecha.
 */
use Intranet\Config;
use Intranet\Portal;
use Intranet\View;

$tonos = [
    'azul'     => ['#eef2ff', '#1e40af'],
    'naranjo'  => ['#fff1e6', '#9a3412'],
    'amarillo' => ['#fef6e0', '#8a4b06'],
];
$categorias = [
    'Finanzas' => ['#eef2ff', '#1e40af'],
    'Intranet' => ['#e6f6ec', '#166534'],
    'Personas' => ['#fef6e0', '#8a4b06'],
];
$urlEgresos = View::url('EGRESOS', '');
$soporte = (string) Config::get('app.soporte_email', '');
$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
?>
<section class="hero">
  <svg class="hero-olas" aria-hidden="true" viewBox="0 0 900 260" preserveAspectRatio="none">
    <?php foreach ([[70, .06], [110, .08], [150, .10], [190, .08], [230, .06]] as [$y, $o]): ?>
      <path d="M0 <?= $y ?> C 150 <?= $y - 28 ?>, 300 <?= $y + 28 ?>, 450 <?= $y ?> S 750 <?= $y - 28 ?>, 900 <?= $y ?>" fill="none" stroke="#ffffff" stroke-opacity="<?= $o ?>" stroke-width="1.5"/>
    <?php endforeach; ?>
  </svg>
  <img class="hero-logo" src="<?= e(View::asset('img/logo_blanco_256.png')) ?>" alt="" aria-hidden="true">
  <div class="hero-texto">
    <span class="hero-fecha"><?= e($fecha) ?></span>
    <h2><?= e($saludo) ?></h2>
    <p>Bienvenido a la intranet de Salmones Antártica. Desde aquí accede a sus sistemas y se entera de lo que pasa en la empresa.</p>
  </div>
  <div class="hero-cifras">
    <?php if ($pendientes): ?>
      <div class="hero-cifra"><span>Pendientes</span><strong><?= (int) $totalPendientes ?></strong></div>
    <?php endif; ?>
    <div class="hero-cifra"><span>Sistemas activos</span><strong><?= (int) $conPerfil ?> de <?= count($sistemas) ?></strong></div>
  </div>
</section>

<?php if ($perfilEgresos !== null || $soporte !== ''): ?>
<div class="rapidos">
  <?php if (in_array($perfilEgresos, ['DIGITADOR', 'ADMIN'], true)): ?>
    <a class="tarjeta rapido" href="<?= e($urlEgresos) ?>"><span class="icono"><?= View::icono('plus', 20) ?></span><span><strong>Nuevo egreso</strong><small>Registrar un gasto</small></span></a>
    <a class="tarjeta rapido" href="<?= e($urlEgresos) ?>"><span class="icono"><?= View::icono('list', 20) ?></span><span><strong>Mis egresos</strong><small>Ver y corregir</small></span></a>
  <?php elseif ($perfilEgresos === 'APROBADOR'): ?>
    <a class="tarjeta rapido" href="<?= e($urlEgresos) ?>"><span class="icono"><?= View::icono('check', 20) ?></span><span><strong>Aprobaciones</strong><small>Egresos de su nivel</small></span></a>
  <?php elseif ($perfilEgresos === 'TESORERIA'): ?>
    <a class="tarjeta rapido" href="<?= e($urlEgresos) ?>"><span class="icono"><?= View::icono('wallet', 20) ?></span><span><strong>Tesorería</strong><small>Registrar pagos</small></span></a>
  <?php endif; ?>
  <?php if ($perfilEgresos !== null): ?>
    <a class="tarjeta rapido" href="<?= e($urlEgresos) ?>"><span class="icono"><?= View::icono('chart', 20) ?></span><span><strong>Reportes</strong><small>Gasto del mes</small></span></a>
  <?php endif; ?>
  <?php if ($soporte !== ''): ?>
    <a class="tarjeta rapido" href="mailto:<?= e($soporte) ?>"><span class="icono"><?= View::icono('mail', 20) ?></span><span><strong>Soporte TI</strong><small>Pedir ayuda o acceso</small></span></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="portada-columnas">
  <div class="portada-principal">
    <section>
      <div class="titulo-seccion mb-2"><h2>Sus sistemas</h2><small>Los perfiles los asigna el administrador</small></div>
      <div class="sistemas">
        <?php foreach ($sistemas as $s): $ok = (int) $s['TienePerfil'] === 1; ?>
          <a class="tarjeta sistema<?= $ok ? '' : ' bloqueado' ?>" href="<?= e(Portal::urlSistema($s)) ?>">
            <span class="sistema-cabeza">
              <span class="icono"><?= View::icono($s['Icono'] ?: 'layers', 22) ?></span>
              <?php if ($ok): ?>
                <span class="chip"><?= e($s['Perfil']) ?></span>
              <?php else: ?>
                <span class="chip chip-gris"><?= View::icono('lock', 12) ?> Sin acceso</span>
              <?php endif; ?>
            </span>
            <span>
              <strong><?= e($s['SistemaCodigo'] === 'INTRANET' ? 'Administración' : $s['Sistema']) ?></strong>
              <p><?= e($s['Descripcion']) ?></p>
            </span>
            <span class="sistema-accion"><?= $ok ? 'Entrar →' : 'Solicitar acceso' ?></span>
          </a>
        <?php endforeach; ?>
        <div class="sistema-proximo">
          <strong>Próximos sistemas</strong>
          <span>Los nuevos sistemas internos aparecerán aquí a medida que se incorporen a la intranet.</span>
        </div>
      </div>
    </section>

    <?php if ($pendientes): ?>
      <section class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Pendientes en Egresos</h2><a href="<?= e($urlEgresos) ?>">Ir a Egresos →</a></div>
        <div class="lista-filas">
          <?php foreach ($pendientes as $p): [$fondo, $color] = $tonos[$p['tono']]; ?>
            <a href="<?= e($urlEgresos) ?>">
              <span class="icono" style="background: <?= $fondo ?>; color: <?= $color ?>"><?= View::icono($p['icono'], 16) ?></span>
              <span class="texto"><?= e($p['texto']) ?></span>
              <strong class="num"><?= (int) $p['n'] ?></strong>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <aside class="portada-lateral tarjeta">
    <div class="tarjeta-cabecera"><h2><?= View::icono('megaphone', 16) ?> Comunicados</h2></div>
    <?php if (!$comunicados): ?>
      <p class="vacio">No hay comunicados publicados.</p>
    <?php endif; ?>
    <?php foreach ($comunicados as $c):
        [$fondo, $color] = $categorias[$c['Categoria']] ?? ['#f3f4f6', '#374151'];
        $f = new DateTime((string) $c['PublicadoEn']); ?>
      <article class="comunicado">
        <span class="comunicado-meta">
          <span class="chip" style="background: <?= $fondo ?>; color: <?= $color ?>"><?= e($c['Categoria']) ?></span>
          <span><?= (int) $f->format('j') ?> <?= $meses[(int) $f->format('n') - 1] ?></span>
        </span>
        <h3><?= e($c['Titulo']) ?></h3>
        <p><?= e($c['Resumen']) ?></p>
      </article>
    <?php endforeach; ?>
  </aside>
</div>
