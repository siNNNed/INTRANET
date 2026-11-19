<?php
/** Listado de usuarios. Variables: $filas, $filtros, $sistemas, $perfiles, $dominios, $flash. */
use Intranet\View;

$opcion = static function (string $valor, string $texto, string $actual): string {
    return '<option value="' . e($valor) . '"' . ($valor === $actual ? ' selected' : '') . '>' . e($texto) . '</option>';
};
$hayFiltros = $filtros['q'] !== '' || $filtros['sistema'] !== '' || $filtros['perfil'] !== '' || $filtros['estado'] !== 'activos';
?>
<?php if ($flash): ?>
  <div class="alerta alerta-ok" role="status"><?= View::icono('check', 16) ?><span><?= e($flash) ?></span></div>
<?php endif; ?>

<form class="tarjeta filtros" method="get" action="" aria-label="Filtros">
  <div>
    <label class="form-label" for="f-q">Buscar</label>
    <input class="form-control" id="f-q" name="q" type="search" value="<?= e($filtros['q']) ?>" placeholder="Nombre, correo o RUT" maxlength="100">
  </div>
  <div>
    <label class="form-label" for="f-sistema">Sistema</label>
    <select class="form-select" id="f-sistema" name="sistema" data-autoenviar>
      <?= $opcion('', 'Todos', $filtros['sistema']) ?>
      <?php foreach ($sistemas as $s): ?><?= $opcion($s['Codigo'], $s['Nombre'], $filtros['sistema']) ?><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label" for="f-perfil">Perfil</label>
    <select class="form-select" id="f-perfil" name="perfil" data-autoenviar>
      <?= $opcion('', 'Todos', $filtros['perfil']) ?>
      <?php foreach ($perfiles as $p): ?><?= $opcion($p['Codigo'], $p['Nombre'], $filtros['perfil']) ?><?php endforeach; ?>
      <?= $opcion('NINGUNO', 'Sin acceso', $filtros['perfil']) ?>
    </select>
  </div>
  <div>
    <label class="form-label" for="f-estado">Estado</label>
    <select class="form-select" id="f-estado" name="estado" data-autoenviar>
      <?= $opcion('activos', 'Activos', $filtros['estado']) ?>
      <?= $opcion('inactivos', 'Inactivos', $filtros['estado']) ?>
      <?= $opcion('todos', 'Todos', $filtros['estado']) ?>
    </select>
  </div>
  <div class="filtros-botones">
    <button class="btn btn-secundario" type="submit">Filtrar</button>
    <?php if ($hayFiltros): ?><a class="btn btn-enlace" href="<?= e(View::url('INTRANET', 'admin/')) ?>">Limpiar</a><?php endif; ?>
  </div>
</form>

<section class="tarjeta" style="overflow: hidden">
  <div class="tarjeta-cabecera">
    <span class="text-secondary" style="font-size: 13px"><strong style="color: var(--tinta)"><?= count($filas) ?></strong> usuarios<?php if ($dominios): ?> · con Google solo pueden entrar correos @<?= e(implode(' y @', $dominios)) ?><?php endif; ?></span>
  </div>
  <?php if (!$filas): ?>
    <p class="vacio">No hay usuarios con estos filtros.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 tabla">
      <thead class="table-light">
        <tr><th class="ps-3">Usuario</th><th>Ingreso</th><th>Cargo</th><th>Accesos</th><th>Último ingreso</th><th>Estado</th><th class="pe-3 text-end">Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($filas as $f): ?>
          <tr>
            <td class="ps-3"><strong><?= e($f['Nombre']) ?></strong><br><span class="text-secondary"><?= e($f['Email']) ?></span></td>
            <td><?= $f['MetodoAcceso'] === 'GOOGLE' ? '<span class="chip chip-google">Google</span>' : '<span class="chip chip-gris">Local</span>' ?></td>
            <td class="text-secondary"><?= e($f['Cargo'] ?? '—') ?></td>
            <td>
              <?php if ($f['accesos']): foreach ($f['accesos'] as $a): ?>
                <span class="chip me-1<?= (int) $a['Vencido'] === 1 ? ' chip-gris' : '' ?>" <?= (int) $a['Vencido'] === 1 ? 'title="Acceso vencido"' : '' ?>>
                  <?= e(($a['SistemaCodigo'] === 'INTRANET' ? 'Admin. intranet' : $a['Sistema'] . ': ' . $a['Perfil'])
                      . ($a['VigenteHasta'] ? ((int) $a['Vencido'] === 1 ? ' · venció ' : ' · hasta ') . date('d-m-Y', strtotime((string) $a['VigenteHasta'])) : '')) ?>
                </span>
              <?php endforeach; else: ?>
                <span class="text-secondary">Sin accesos</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary"><?= $f['UltimoAcceso'] ? e(date('d-m-Y H:i', strtotime((string) $f['UltimoAcceso']))) : '—' ?></td>
            <td><?= (int) $f['Activo'] === 1 ? '<span class="chip chip-ok">Activo</span>' : '<span class="chip chip-gris">Inactivo</span>' ?></td>
            <td class="pe-3 text-end">
              <a class="btn-icono" href="<?= e(View::url('INTRANET', 'admin/usuario.php') . '?id=' . (int) $f['UsuarioId']) ?>" title="Editar" aria-label="Editar a <?= e($f['Nombre']) ?>"><?= View::icono('edit', 16) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<script>document.querySelectorAll('[data-autoenviar]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });</script>
