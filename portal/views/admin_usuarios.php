<?php
/** Listado de usuarios (solo lectura). Variables: $filas. */
?>
<div class="alerta alerta-info">
  <span>La creación y edición de usuarios y accesos llega en la siguiente etapa. Mientras tanto, este listado muestra lo que hay en la base.</span>
</div>
<section class="tarjeta" style="overflow: hidden">
  <div class="tarjeta-cabecera"><h2><?= count($filas) ?> usuarios</h2></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" style="font-size: 13px; white-space: nowrap">
      <thead class="table-light">
        <tr><th class="ps-3">Usuario</th><th>Ingreso</th><th>Cargo</th><th>Accesos</th><th>Último ingreso</th><th class="pe-3">Estado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($filas as $f): ?>
          <tr>
            <td class="ps-3"><strong><?= e($f['Nombre']) ?></strong><br><span class="text-secondary"><?= e($f['Email']) ?></span></td>
            <td><span class="chip chip-gris"><?= $f['MetodoAcceso'] === 'GOOGLE' ? 'Google' : 'Local' ?></span></td>
            <td class="text-secondary"><?= e($f['Cargo'] ?? '—') ?></td>
            <td>
              <?php if ($f['Accesos']): foreach (explode(' | ', (string) $f['Accesos']) as $a): ?>
                <span class="chip me-1"><?= e($a) ?></span>
              <?php endforeach; else: ?>
                <span class="text-secondary">Sin accesos</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary"><?= $f['UltimoAcceso'] ? e(date('d-m-Y H:i', strtotime((string) $f['UltimoAcceso']))) : '—' ?></td>
            <td class="pe-3"><?= (int) $f['Activo'] === 1 ? '<span class="chip" style="background:#e6f6ec;color:#166534">Activo</span>' : '<span class="chip chip-gris">Inactivo</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
