<?php
/** Contenido de la respuesta 403 "sin perfil". Variables: $sistemaNombre. */
use Intranet\View;
?>
<div class="sin-perfil">
  <section class="tarjeta sin-perfil-caja" role="alert">
    <span class="sin-perfil-icono"><?= View::icono('lock', 30) ?></span>
    <h2>Usted no tiene el perfil para este sistema</h2>
    <p>Su cuenta no tiene un perfil asignado en <strong><?= e($sistemaNombre) ?></strong>.
       Si necesita acceso, solicítelo al administrador de la intranet.</p>
    <a class="btn btn-primario" href="<?= e(View::url('INTRANET', '')) ?>"><?= View::icono('back', 16) ?> Volver a la portada</a>
    <small>Este intento quedó registrado en la bitácora de la intranet.</small>
  </section>
</div>
