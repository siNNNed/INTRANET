<?php
declare(strict_types=1);

namespace Intranet;

/** Reglas compartidas. Su equivalente en el navegador está en comun/assets/js/intranet.js. */
final class Validator
{
    public static function email(string $valor): bool
    {
        return mb_strlen($valor) <= 150 && filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Correo de uno de los dominios indicados. */
    public static function emailDeDominio(string $valor, array $dominios): bool
    {
        if (!self::email($valor)) {
            return false;
        }
        $dominio = strtolower(substr(strrchr($valor, '@') ?: '', 1));
        return in_array($dominio, $dominios, true);
    }

    /** Normaliza un RUT a 12345678-K. Devuelve null si el formato no sirve. */
    public static function normalizarRut(string $valor): ?string
    {
        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $valor) ?? '');
        if (strlen($limpio) < 2 || strlen($limpio) > 9) {
            return null;
        }
        $cuerpo = substr($limpio, 0, -1);
        $dv = substr($limpio, -1);
        if (!ctype_digit($cuerpo)) {
            return null;
        }
        return ltrim($cuerpo, '0') . '-' . $dv;
    }

    /** RUT chileno válido (dígito verificador módulo 11). */
    public static function rut(string $valor): bool
    {
        $rut = self::normalizarRut($valor);
        if ($rut === null) {
            return false;
        }
        [$cuerpo, $dv] = explode('-', $rut);
        $suma = 0;
        $factor = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $resto = 11 - ($suma % 11);
        $esperado = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string) $resto);
        return $dv === $esperado;
    }

    /** Mínimo 10 caracteres con letras y números. */
    public static function password(string $valor): bool
    {
        return strlen($valor) >= 10 && preg_match('/[A-Za-z]/', $valor) === 1 && preg_match('/\d/', $valor) === 1;
    }
}
