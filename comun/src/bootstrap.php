<?php
/**
 * Punto de arranque común de la intranet.
 * Toda página o endpoint de cualquier sistema empieza con:
 *   require dirname(__DIR__, 2) . '/comun/src/bootstrap.php';
 */

declare(strict_types=1);

define('INTRANET_ROOT', dirname(__DIR__, 2));

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Intranet\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $archivo = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

$archivoConfig = INTRANET_ROOT . '/config/config.php';
if (!is_file($archivoConfig)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Falta config/config.php. Copie config/config.example.php como config.php y complete los datos.');
}
Intranet\Config::cargar(require $archivoConfig);

date_default_timezone_set('America/Santiago');
mb_internal_encoding('UTF-8');

$desarrollo = Intranet\Config::get('app.entorno') === 'desarrollo';
error_reporting(E_ALL);
ini_set('display_errors', $desarrollo ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler([Intranet\Errores::class, 'manejar']);

/** Escapa texto para HTML. Usar siempre al imprimir datos. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

Intranet\Auth::iniciarSesion();
