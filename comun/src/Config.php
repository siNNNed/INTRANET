<?php
declare(strict_types=1);

namespace Intranet;

/** Lee config/config.php con notación de puntos: Config::get('db.dsn'). */
final class Config
{
    private static array $datos = [];

    public static function cargar(array $datos): void
    {
        self::$datos = $datos;
    }

    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        $valor = self::$datos;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $porDefecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }
}
