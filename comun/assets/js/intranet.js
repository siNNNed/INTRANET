/* =====================================================================
   Intranet Salmones Antártica — utilidades comunes (jQuery)
   - Envía el token CSRF en cada petición AJAX.
   - Intranet.post(): entiende el sobre {ok, data | error}.
   - Validaciones del cliente equivalentes a comun/src/Validator.php.
   ===================================================================== */
(function ($, window) {
  'use strict';

  var csrf = $('meta[name="csrf-token"]').attr('content') || '';
  $.ajaxSetup({ headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } });

  var Intranet = {};

  /** POST que devuelve una promesa: resuelve con data, rechaza con {codigo, mensaje, campos}. */
  Intranet.post = function (url, datos) {
    return $.ajax({ url: url, method: 'POST', data: datos, dataType: 'json' })
      .then(function (r) {
        if (r && r.ok) { return r.data; }
        return $.Deferred().reject(r && r.error ? r.error : { codigo: 'DESCONOCIDO', mensaje: 'Respuesta inesperada del servidor.' });
      }, function (xhr) {
        var r = xhr.responseJSON;
        if (xhr.status === 401 && window.INTRANET_LOGIN) {
          window.location.href = window.INTRANET_LOGIN + '?next=' + encodeURIComponent(window.location.pathname + window.location.search);
        }
        return $.Deferred().reject(r && r.error ? r.error : { codigo: 'RED', mensaje: 'No se pudo conectar con el servidor. Intente nuevamente.' });
      });
  };

  /** Marca los campos con error (Bootstrap is-invalid) y escribe el mensaje con .text(). */
  Intranet.mostrarErrores = function ($form, campos) {
    Intranet.limpiarErrores($form);
    $.each(campos || {}, function (nombre, mensaje) {
      var $campo = $form.find('[name="' + nombre + '"]');
      $campo.addClass('is-invalid').attr('aria-invalid', 'true');
      var $fb = $campo.siblings('.invalid-feedback');
      if (!$fb.length) { $fb = $('<div class="invalid-feedback"></div>').insertAfter($campo); }
      $fb.text(mensaje);
    });
  };

  Intranet.limpiarErrores = function ($form) {
    $form.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
    $form.find('.invalid-feedback').text('');
  };

  /** Muestra un mensaje en un contenedor .alerta (texto plano, nunca HTML). */
  Intranet.alerta = function ($caja, mensaje) {
    $caja.find('[data-texto]').text(mensaje);
    $caja.prop('hidden', !mensaje);
  };

  Intranet.validar = {
    email: function (v) { return typeof v === 'string' && v.length <= 150 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); },
    texto: function (v, min, max) { var n = $.trim(String(v || '')).length; return n >= min && n <= max; },
    fecha: function (v) {
      var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v || ''));
      if (!m) { return false; }
      var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
      return d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3];
    },
    /** Correo de uno de los dominios (lista en minúsculas). */
    emailDeDominio: function (v, dominios) {
      if (!Intranet.validar.email(v)) { return false; }
      return $.inArray(String(v).split('@').pop().toLowerCase(), dominios || []) !== -1;
    },
    password: function (v) { return typeof v === 'string' && v.length >= 10 && /[A-Za-z]/.test(v) && /\d/.test(v); },
    rut: function (v) {
      var limpio = String(v || '').replace(/[^0-9kK]/g, '').toUpperCase();
      if (limpio.length < 2 || limpio.length > 9) { return false; }
      var cuerpo = limpio.slice(0, -1), dv = limpio.slice(-1);
      if (!/^\d+$/.test(cuerpo)) { return false; }
      var suma = 0, factor = 2;
      for (var i = cuerpo.length - 1; i >= 0; i--) {
        suma += parseInt(cuerpo.charAt(i), 10) * factor;
        factor = factor === 7 ? 2 : factor + 1;
      }
      var resto = 11 - (suma % 11);
      var esperado = resto === 11 ? '0' : (resto === 10 ? 'K' : String(resto));
      return dv === esperado;
    }
  };

  window.Intranet = Intranet;
})(jQuery, window);
