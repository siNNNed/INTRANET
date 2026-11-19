<?php
/**
 * Crear o editar una cuenta. Variables: $u (null = nueva), $esPropia, $accesos, $sistemas,
 * $cargos, $departamentos, $actividad.
 */
use Intranet\View;

$sinClave = $u === null || (int) $u['SinClave'] === 1;
$activo  = $u === null || (int) $u['Activo'] === 1;
$sel     = static fn ($a, $b): string => (string) $a === (string) $b ? ' selected' : '';
$fecha   = static fn ($v): string => $v ? date('d-m-Y H:i', strtotime((string) $v)) : '—';
?>
<div class="editor">
  <form id="form-usuario" class="editor-principal" novalidate
        data-url="<?= e(View::url('INTRANET', 'api/usuarios/guardar.php')) ?>"
        data-pide-clave="<?= $sinClave ? '1' : '0' ?>">
    <input type="hidden" name="id" value="<?= (int) ($u['UsuarioId'] ?? 0) ?>">

    <div class="alerta alerta-error" id="form-error" role="alert" hidden><?= View::icono('alert', 16) ?><span data-texto></span></div>

    <section class="tarjeta seccion">
      <h2><span class="seccion-num">1</span>Datos de la persona</h2>
      <div class="rejilla">
        <div>
          <label class="form-label" for="u-nombre">Nombre completo *</label>
          <input class="form-control" id="u-nombre" name="nombre" maxlength="100" required autocomplete="off" value="<?= e($u['Nombre'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label" for="u-email">Correo de la empresa *</label>
          <input class="form-control" id="u-email" name="email" type="email" maxlength="150" required autocomplete="off" value="<?= e($u['Email'] ?? '') ?>">
          <div class="form-ayuda">Es el usuario con que ingresará a la intranet.</div>
        </div>
        <div>
          <label class="form-label" for="u-rut">RUT</label>
          <input class="form-control" id="u-rut" name="rut" maxlength="12" placeholder="12.345.678-5" autocomplete="off" value="<?= e($u['Rut'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label" for="u-cargo">Cargo</label>
          <select class="form-select" id="u-cargo" name="cargo">
            <option value="">— Sin cargo —</option>
            <?php foreach ($cargos as $c): ?><option value="<?= (int) $c['Id'] ?>"<?= $sel($c['Id'], $u['CargoId'] ?? '') ?>><?= e($c['Nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="u-depto">Departamento</label>
          <select class="form-select" id="u-depto" name="departamento">
            <option value="">— Sin departamento —</option>
            <?php foreach ($departamentos as $d): ?><option value="<?= (int) $d['Id'] ?>"<?= $sel($d['Id'], $u['DepartamentoId'] ?? '') ?>><?= e($d['Nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <span class="form-label">Estado</span>
          <label class="casilla">
            <input type="checkbox" name="activo" value="1"<?= $activo ? ' checked' : '' ?><?= $esPropia ? ' disabled' : '' ?>> Cuenta activa
          </label>
          <?php if ($esPropia): ?><input type="hidden" name="activo" value="1"><div class="form-ayuda">No puede desactivar su propia cuenta.</div><?php endif; ?>
          <div class="invalid-feedback d-block" data-error="activo"></div>
        </div>
      </div>

    </section>

    <section class="tarjeta seccion">
      <h2><span class="seccion-num">2</span>Contraseña</h2>
      <?php if ($esPropia): ?>
        <p class="seccion-texto">Su propia contraseña se cambia en <a href="<?= e(View::url('INTRANET', 'cambiar_clave.php')) ?>">Cambiar contraseña</a>.</p>
      <?php else: ?>
        <p class="seccion-texto">
          <?php if ($u === null): ?>Asigne una contraseña inicial y entréguesela a la persona por un medio seguro.
          <?php elseif ($sinClave): ?>Esta cuenta no tiene contraseña y no puede ingresar. Asígnele una.
          <?php else: ?>Déjela vacía para mantener la actual. Si la persona la olvidó, asígnele una nueva aquí.<?php endif; ?>
        </p>
        <div class="rejilla">
          <div>
            <label class="form-label" for="u-pass"><?= $sinClave ? 'Contraseña inicial *' : 'Nueva contraseña' ?></label>
            <input class="form-control" id="u-pass" name="password" type="password" maxlength="200" autocomplete="new-password">
            <div class="form-ayuda">Mínimo 10 caracteres, con letras y números.</div>
          </div>
          <div>
            <label class="form-label" for="u-pass2">Repita la contraseña</label>
            <input class="form-control" id="u-pass2" name="password2" type="password" maxlength="200" autocomplete="new-password">
          </div>
        </div>
        <label class="casilla">
          <input type="checkbox" name="debe_cambiar" value="1" checked> Pedirle que la cambie la próxima vez que ingrese
        </label>
      <?php endif; ?>
    </section>

    <section class="tarjeta seccion">
      <h2><span class="seccion-num">3</span>Accesos por sistema</h2>
      <p class="seccion-texto">Un perfil por sistema. "Sin acceso" quita el sistema: la persona lo sigue viendo en la portada, pero con candado. Deje la fecha vacía para que no venza.</p>
      <div class="accesos">
        <?php foreach ($sistemas as $sid => $s):
            $a = $accesos[$sid] ?? null;
            $bloquear = $esPropia && $s['Codigo'] === 'INTRANET'; ?>
          <div class="acceso">
            <div class="acceso-sistema">
              <strong><?= e($s['Nombre']) ?></strong>
              <small><?= e($s['Descripcion'] ?? '') ?></small>
            </div>
            <div>
              <label class="form-label-chica" for="a-<?= (int) $sid ?>">Perfil</label>
              <select class="form-select" id="a-<?= (int) $sid ?>" name="accesos[<?= (int) $sid ?>][perfil]"<?= $bloquear ? ' disabled' : '' ?>>
                <option value="">Sin acceso</option>
                <?php foreach ($s['perfiles'] as $pid => $p): ?>
                  <option value="<?= (int) $pid ?>"<?= $sel($pid, $a['PerfilId'] ?? '') ?>><?= e($p['Nombre']) ?><?= (int) $p['Activo'] !== 1 ? ' (inactivo)' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($bloquear): ?>
                <input type="hidden" name="accesos[<?= (int) $sid ?>][perfil]" value="<?= (int) ($a['PerfilId'] ?? 0) ?>">
                <div class="form-ayuda">No puede quitarse su propio acceso de administrador.</div>
              <?php endif; ?>
            </div>
            <div>
              <label class="form-label-chica" for="v-<?= (int) $sid ?>">Vigente hasta</label>
              <input class="form-control" id="v-<?= (int) $sid ?>" type="date" name="accesos[<?= (int) $sid ?>][vigente]" value="<?= e($a['VigenteHasta'] ?? '') ?>"<?= $bloquear ? ' readonly' : '' ?>>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="editor-botones">
      <a class="btn btn-secundario" href="<?= e(View::url('INTRANET', 'admin/')) ?>">Cancelar</a>
      <button class="btn btn-primario" type="submit"><?= $u ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
  </form>

  <?php if ($u): ?>
  <aside class="editor-lateral">
    <section class="tarjeta seccion">
      <h2>Resumen</h2>
      <dl class="resumen">
        <div><dt>Contraseña</dt><dd><?= $sinClave ? '<span class="chip chip-error">Sin contraseña</span>' : ((int) $u['DebeCambiarPassword'] === 1 ? '<span class="chip chip-aviso">Debe cambiarla</span>' : 'Asignada') ?></dd></div>
        <div><dt>Último ingreso</dt><dd><?= e($fecha($u['UltimoAcceso'])) ?></dd></div>
        <div><dt>Creada</dt><dd><?= e($fecha($u['CreadoEn'])) ?></dd></div>
        <?php if ((int) $u['Bloqueado'] === 1): ?>
          <div><dt>Bloqueada hasta</dt><dd class="text-danger"><?= e($fecha($u['BloqueadoHasta'])) ?></dd></div>
        <?php endif; ?>
      </dl>
      <?php if ((int) $u['Bloqueado'] === 1): ?><p class="seccion-texto">Bloqueada por intentos fallidos. Se desbloquea sola, o al asignarle una nueva contraseña.</p><?php endif; ?>
    </section>
    <section class="tarjeta seccion">
      <h2>Actividad reciente</h2>
      <?php if (!$actividad): ?>
        <p class="seccion-texto">Sin actividad registrada.</p>
      <?php else: ?>
        <ul class="actividad">
          <?php foreach ($actividad as $act): ?>
            <li><span><?= e($act['texto']) ?></span><small><?= e($fecha($act['fecha'])) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
  <?php endif; ?>
</div>
