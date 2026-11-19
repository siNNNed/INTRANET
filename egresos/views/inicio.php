<?php
/** Inicio de Egresos (provisional). Variables: $acceso. */
use Intranet\View;
?>
<section class="tarjeta" style="padding: 28px; max-width: 720px">
  <h2 style="font-size: 18px; font-weight: 700; margin: 0 0 8px">Bienvenido a Egresos</h2>
  <p style="margin: 0 0 16px; color: var(--texto-2); font-size: 14px; line-height: 1.6">
    Su perfil en este sistema es <span class="chip"><?= e($acceso['Perfil']) ?></span>.
    Los módulos de registro, aprobación, tesorería y reportes se incorporan en la siguiente etapa.
  </p>
  <a class="btn btn-secundario" href="<?= e(View::url('INTRANET', '')) ?>"><?= View::icono('back', 16) ?> Volver a la portada</a>
</section>
