/* Cambio de contraseña: validación en el cliente (guía) y envío por AJAX. */
(function ($) {
  'use strict';

  var $form = $('#form-clave');
  var $error = $('#form-error');

  $form.on('submit', function (ev) {
    ev.preventDefault();
    Intranet.alerta($error, '');

    var actual = $('#c-actual').val();
    var nueva = $('#c-nueva').val();
    var campos = {};
    if (!actual) { campos.actual = 'Ingrese su contraseña actual.'; }
    if (!Intranet.validar.password(nueva)) {
      campos.nueva = 'Mínimo 10 caracteres, con letras y números.';
    } else if (nueva === actual) {
      campos.nueva = 'La nueva contraseña debe ser distinta de la actual.';
    } else if (nueva !== $('#c-nueva2').val()) {
      campos.nueva2 = 'Las contraseñas no coinciden.';
    }
    if (!$.isEmptyObject(campos)) { Intranet.mostrarErrores($form, campos); return; }
    Intranet.limpiarErrores($form);

    var $boton = $form.find('button[type="submit"]').prop('disabled', true).text('Guardando…');
    Intranet.post($form.data('url'), $form.serialize())
      .done(function (data) { window.location.href = data.redirigir; })
      .fail(function (err) {
        if (err.campos) { Intranet.mostrarErrores($form, err.campos); }
        Intranet.alerta($error, err.mensaje);
        $form.find('input[type="password"]').val('');
        $('#c-actual').trigger('focus');
        $boton.prop('disabled', false).text('Cambiar contraseña');
      });
  });
})(jQuery);
