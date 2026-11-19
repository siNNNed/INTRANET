/* Login: muestra el acceso con contraseña y lo envía por AJAX. */
(function ($) {
  'use strict';

  var $form = $('#form-local');
  var $error = $('#login-error');

  $('#mostrar-local').on('click', function () {
    var abrir = $form.prop('hidden');
    $form.prop('hidden', !abrir);
    $(this).attr('aria-expanded', abrir ? 'true' : 'false');
    if (abrir) { $('#email').trigger('focus'); }
  });

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
