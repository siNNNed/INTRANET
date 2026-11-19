<?php
declare(strict_types=1);

namespace Intranet;

/** Lee seg.Parametros (una consulta por petición). */
final class Parametros
{
    private static ?array $cache = null;

    public static function get(string $clave, ?string $porDefecto = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::todas('SELECT Clave, Valor FROM seg.Parametros') as $fila) {
                self::$cache[$fila['Clave']] = $fila['Valor'];
            }
        }
        $valor = self::$cache[$clave] ?? null;
        return ($valor === null || $valor === '') ? $porDefecto : $valor;
    }

    public static function entero(string $clave, int $porDefecto): int
    {
        $valor = self::get($clave);
        return ($valor !== null && ctype_digit($valor)) ? (int) $valor : $porDefecto;
    }

    /** Dominios permitidos para Google, en minúsculas. */
    public static function dominiosGoogle(): array
    {
        $lista = array_map('trim', explode(',', (string) self::get('GOOGLE_DOMINIOS', '')));
        return array_values(array_filter(array_map('strtolower', $lista)));
    }
}
