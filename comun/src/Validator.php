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

    /** Texto obligatorio, sin espacios sobrantes, entre $min y $max caracteres. */
    public static function texto(string $valor, int $min, int $max): bool
    {
        $largo = mb_strlen(trim($valor));
        return $largo >= $min && $largo <= $max;
    }

    /** Fecha real en formato AAAA-MM-DD. */
    public static function fecha(string $valor): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) !== 1) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Mínimo 10 caracteres con letras y números. */
    public static function password(string $valor): bool
    {
        return strlen($valor) >= 10 && preg_match('/[A-Za-z]/', $valor) === 1 && preg_match('/\d/', $valor) === 1;
    }
}
