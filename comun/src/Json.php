<?php
declare(strict_types=1);

namespace Intranet;

/**
 * Sobre JSON único para todos los endpoints:
 *   {"ok":true,"data":...}
 *   {"ok":false,"error":{"codigo":"...","mensaje":"...","campos":{...}}}
 */
final class Json
{
    public static function ok(mixed $data = null, int $http = 200): never
    {
        self::enviar(['ok' => true, 'data' => $data], $http);
    }

    public static function error(string $codigo, string $mensaje, int $http = 400, array $campos = []): never
    {
        $error = ['codigo' => $codigo, 'mensaje' => $mensaje];
        if ($campos !== []) {
            $error['campos'] = $campos;
        }
        self::enviar(['ok' => false, 'error' => $error], $http);
    }

    private static function enviar(array $cuerpo, int $http): never
    {
        if (!headers_sent()) {
            http_response_code($http);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
