<?php
declare(strict_types=1);

namespace Intranet;

/**
 * Token CSRF por sesión. Las páginas lo publican en <meta name="csrf-token">
 * e intranet.js lo envía en la cabecera X-CSRF-Token de cada petición AJAX.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verificar(): void
    {
        $enviado = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
        $esperado = (string) ($_SESSION['csrf'] ?? '');
        if ($esperado === '' || $enviado === '' || !hash_equals($esperado, $enviado)) {
            if (Http::esAjax()) {
                Json::error('CSRF', 'La página expiró. Recárguela e intente nuevamente.', 403);
            }
            http_response_code(403);
            View::error('La página expiró', 'Recargue la página e intente nuevamente.', 403);
        }
    }
}
