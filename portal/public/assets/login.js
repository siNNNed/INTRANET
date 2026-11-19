/* Login: valida y envía el correo y la contraseña por AJAX. */
(function ($) {
  'use strict';

  var $form = $('#form-login');
  var $error = $('#login-error');

  $form.on('submit', function (ev) {
    ev.preventDefault();
    Intranet.alerta($error, '');

    var email = $.trim($('#email').val()).toLowerCase();
    var password = $('#password').val();
    var campos = {};
    if (!Intranet.validar.email(email)) { campos.email = 'Ingrese un correo válido.'; }
    if (!password) { campos.password = 'Ingrese su contraseña.'; }
    if (!$.isEmptyObject(campos)) { Intranet.mostrarErrores($form, campos); return; }
    Intranet.limpiarErrores($form);

    var $boton = $form.find('button[type="submit"]').prop('disabled', true).text('Ingresando…');
    Intranet.post($form.data('url'), { email: email, password: password, next: $form.find('[name="next"]').val() })
      .done(function (data) { window.location.href = data.redirigir; })
      .fail(function (err) {
        if (err.campos) { Intranet.mostrarErrores($form, err.campos); }
        Intranet.alerta($error, err.mensaje);
        $('#password').val('').trigger('focus');
      })
      .always(function () { $boton.prop('disabled', false).text('Ingresar'); });
  });
})(jQuery);
