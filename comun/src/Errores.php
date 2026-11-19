<?php
declare(strict_types=1);

namespace Intranet;

use Throwable;

/** Manejador global: registra el detalle en el log y muestra un mensaje genérico. */
final class Errores
{
    public static function manejar(Throwable $e): void
    {
        $id = bin2hex(random_bytes(4));
        error_log(sprintf('[intranet %s] %s en %s:%d%s%s', $id, $e->getMessage(), $e->getFile(), $e->getLine(), PHP_EOL, $e->getTraceAsString()));

        $mensaje = 'Ocurrió un error inesperado. Si se repite, informe a TI el código ' . $id . '.';
        if (Http::esAjax()) {
            Json::error('INTERNO', $mensaje, 500);
        }

        $detalle = Config::get('app.entorno') === 'desarrollo'
            ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            : null;
        View::error('Algo salió mal', $mensaje, 500, $detalle);
    }
}
