<?php
/** Formulario de cambio de contraseña. Variables: $obligatorio, $destino. */
use Intranet\View;
?>
<div class="editor">
  <form id="form-clave" class="editor-principal" style="max-width: 560px" novalidate
        data-url="<?= e(View::url('INTRANET', 'api/auth/cambiar_clave.php')) ?>">
    <input type="hidden" name="next" value="<?= e($destino) ?>">
    <?php if ($obligatorio): ?>
      <div class="alerta alerta-info"><?= View::icono('key', 16) ?><span>Antes de continuar, cambie la contraseña que le entregó el administrador por una que solo usted conozca.</span></div>
    <?php endif; ?>
    <div class="alerta alerta-error" id="form-error" role="alert" hidden><?= View::icono('alert', 16) ?><span data-texto></span></div>

    <section class="tarjeta seccion">
      <div>
        <label class="form-label" for="c-actual">Contraseña actual</label>
        <input class="form-control" id="c-actual" name="actual" type="password" maxlength="200" autocomplete="current-password" required autofocus>
      </div>
      <div>
        <label class="form-label" for="c-nueva">Nueva contraseña</label>
        <input class="form-control" id="c-nueva" name="nueva" type="password" maxlength="200" autocomplete="new-password" required>
        <div class="form-ayuda">Mínimo 10 caracteres, con letras y números. Distinta de la actual.</div>
      </div>
      <div>
        <label class="form-label" for="c-nueva2">Repita la nueva contraseña</label>
        <input class="form-control" id="c-nueva2" name="nueva2" type="password" maxlength="200" autocomplete="new-password" required>
      </div>
    </section>

    <div class="editor-botones">
      <?php if (!$obligatorio): ?><a class="btn btn-secundario" href="<?= e($destino) ?>">Cancelar</a><?php endif; ?>
      <button class="btn btn-primario" type="submit">Cambiar contraseña</button>
    </div>
  </form>
</div>
