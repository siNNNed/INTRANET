<?php
declare(strict_types=1);

namespace Intranet;

/** Rutas, íconos y render de vistas con el layout común. */
final class View
{
    /** URL dentro de un sistema: View::url('EGRESOS', 'api/x.php') → /egresos/api/x.php */
    public static function url(string $sistema, string $ruta = ''): string
    {
        $base = rtrim((string) Config::get('rutas.' . $sistema, '/'), '/');
        return $base . '/' . ltrim($ruta, '/');
    }

    /** URL de un recurso común: View::asset('css/intranet.css') */
    public static function asset(string $ruta): string
    {
        return self::url('ASSETS', $ruta);
    }

    /**
     * Renderiza una vista dentro del layout.
     * Variables que entiende el layout: titulo, subtitulo, menu, acciones (HTML confiable),
     * marca (texto bajo el logo), sinCabecera (bool).
     */
    public static function render(string $vista, array $datos = [], bool $conLayout = true): void
    {
        $datos['usuario'] ??= Auth::usuario();
        $datos['csrf'] = Csrf::token();
        extract($datos, EXTR_SKIP);

        ob_start();
        require $vista;
        $contenido = ob_get_clean();

        if ($conLayout) {
            require INTRANET_ROOT . '/comun/views/layout.php';
        } else {
            echo $contenido;
        }
    }

    /** Página de error simple, sin consultas a la base. */
    public static function error(string $titulo, string $mensaje, int $http = 500, ?string $detalle = null): never
    {
        if (!headers_sent()) {
            http_response_code($http);
        }
        require INTRANET_ROOT . '/comun/views/error.php';
        exit;
    }

    /** Ícono SVG de línea (decorativo). */
    public static function icono(string $nombre, int $tam = 18, string $clase = ''): string
    {
        $trazos = self::ICONOS[$nombre] ?? self::ICONOS['dot'];
        $clase = $clase !== '' ? ' class="' . e($clase) . '"' : '';
        return '<svg' . $clase . ' width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $trazos . '</svg>';
    }

    private const ICONOS = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'plus'     => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
        'wallet'   => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M16 14.5h2"/>',
        'chart'    => '<path d="M5 20V11M11 20V5M17 20v-6"/><path d="M3 20h18"/>',
        'db'       => '<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14.8c2 .7 3.2 2.5 3.5 5.2"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'undo'     => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'logout'   => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
        'back'     => '<path d="M15 6l-6 6 6 6"/>',
        'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        'layers'   => '<path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/>',
        'megaphone'=> '<path d="M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16.5 8.5a5 5 0 0 1 0 7"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7"/><path d="m17 6 3 3"/><path d="m14.5 8.5 2 2"/>',
        'dot'      => '<circle cx="12" cy="12" r="3"/>',
    ];
}
