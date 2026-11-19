<?php
declare(strict_types=1);

namespace Intranet;

final class Http
{
    /** true si la petición viene de jQuery/AJAX o pide JSON. */
    public static function esAjax(): bool
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public static function esPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function requierePost(): void
    {
        if (!self::esPost()) {
            header('Allow: POST');
            Json::error('METODO', 'Método no permitido.', 405);
        }
    }

    public static function redirigir(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Devuelve una ruta interna segura para redirigir después del login.
     * Solo acepta rutas que empiezan con "/" (no "//", sin esquema ni saltos de línea);
     * en otro caso, la portada.
     */
    public static function destinoSeguro(?string $destino): string
    {
        $destino = (string) $destino;
        $valido = $destino !== ''
            && $destino[0] === '/'
            && !str_starts_with($destino, '//')
            && !str_contains($destino, '\\')
            && !preg_match('/[\r\n\t]/', $destino);
        return $valido ? $destino : View::url('INTRANET', '');
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}
