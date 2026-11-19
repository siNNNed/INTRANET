/* Crear / editar usuario: validación en el cliente (guía) y envío por AJAX. El servidor vuelve a validar todo. */
(function ($) {
  'use strict';

  var $form = $('#form-usuario');
  if (!$form.length) { return; }
  var $error = $('#form-error');
  var dominios = String($form.data('dominios') || '').split(',').filter(Boolean);
  var localActual = String($form.data('local-actual')) === '1';

  function metodo() { return $form.find('[name="metodo"]:checked').val() || $form.find('input[type="hidden"][name="metodo"]').val() || ''; }

  function actualizarClave() {
    $('#bloque-clave').prop('hidden', metodo() !== 'LOCAL');
  }
  $form.on('change', '[name="metodo"]', actualizarClave);
  actualizarClave();

  /** Errores por campo: usa el contenedor data-error si existe; si no, el estándar de Intranet. */
  function mostrarErrores(campos) {
    Intranet.limpiarErrores($form);
    $form.find('[data-error]').text('');
    var normales = {};
    $.each(campos || {}, function (nombre, mensaje) {
      var $caja = $form.find('[data-error="' + nombre + '"]');
      if ($caja.length) { $caja.text(mensaje); } else { normales[nombre] = mensaje; }
    });
    Intranet.mostrarErrores($form, normales);
    var $primero = $form.find('.is-invalid').first();
    if ($primero.length) { $primero.trigger('focus'); }
  }

  function validar() {
    var c = {};
    var nombre = $.trim($('#u-nombre').val());
    var email = $.trim($('#u-email').val()).toLowerCase();
    var rut = $.trim($('#u-rut').val());
    var m = metodo();
    var pass = $('#u-pass').val();

    if (!Intranet.validar.texto(nombre, 3, 100)) { c.nombre = 'Ingrese el nombre completo (3 a 100 caracteres).'; }
    if (!Intranet.validar.email(email)) {
      c.email = 'Ingrese un correo válido.';
    } else if (m === 'GOOGLE' && dominios.length && !Intranet.validar.emailDeDominio(email, dominios)) {
      c.email = 'Para ingresar con Google, el correo debe terminar en @' + dominios.join(' o @') + '.';
    }
    if (rut !== '' && !Intranet.validar.rut(rut)) { c.rut = 'El RUT no es válido. Revise el dígito verificador.'; }
    if (m !== 'GOOGLE' && m !== 'LOCAL') { c.metodo = 'Elija la forma de ingreso.'; }
    if (m === 'LOCAL' && (pass !== '' || !localActual)) {
      if (!Intranet.validar.password(pass)) {
        c.password = 'Mínimo 10 caracteres, con letras y números.';
      } else if (pass !== $('#u-pass2').val()) {
        c.password2 = 'Las contraseñas no coinciden.';
      }
    }
    var hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    $form.find('input[type="date"]').each(function () {
      var v = $(this).val();
      if (v === '' || $(this).prop('readonly')) { return; }
      if (!Intranet.validar.fecha(v)) { c[this.name] = 'Fecha no válida.'; return; }
      if (v !== this.defaultValue && new Date(v + 'T00:00:00') < hoy) { c[this.name] = 'Debe ser hoy o una fecha posterior.'; }
    });
    return c;
  }

  $form.on('submit', function (ev) {
    ev.preventDefault();
    Intranet.alerta($error, '');
    var campos = validar();
    if (!$.isEmptyObject(campos)) {
      mostrarErrores(campos);
      Intranet.alerta($error, 'Revise los campos marcados.');
      return;
    }
    mostrarErrores({});

    var $boton = $form.find('button[type="submit"]');
    var textoBoton = $boton.text();
    $boton.prop('disabled', true).text('Guardando…');
    // Los campos "disabled" no viajan; sus valores van en inputs ocultos.
    Intranet.post($form.data('url'), $form.serialize())
      .done(function (data) { window.location.href = data.redirigir; })
      .fail(function (err) {
        if (err.campos) { mostrarErrores(err.campos); }
        Intranet.alerta($error, err.mensaje);
        $boton.prop('disabled', false).text(textoBoton);
        $('#u-pass, #u-pass2').val('');
        if (!err.campos) { $('html, body').scrollTop(0); }
      });
  });
})(jQuery);
