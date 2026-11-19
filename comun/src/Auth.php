<?php
declare(strict_types=1);

namespace Intranet;

/**
 * Sesión única de la intranet y control de acceso por sistema.
 *
 *   $u   = Auth::requireLogin();                         // cualquier usuario activo
 *   $acc = Auth::requireSistema('EGRESOS');              // perfil vigente en el sistema
 *   $acc = Auth::requirePerfil('EGRESOS', ['ADMIN']);    // perfil específico
 */
final class Auth
{
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https  = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $secure = Config::get('sesion.secure');
        if ($secure === null) {
            $secure = $https;            // automático: Secure cuando se entra por HTTPS
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name((string) Config::get('sesion.nombre', 'INTRANET_SID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $minutos = (int) Config::get('sesion.minutos', 30);
        if (isset($_SESSION['usuario'], $_SESSION['ultima_actividad'])
            && time() - (int) $_SESSION['ultima_actividad'] > $minutos * 60) {
            self::cerrarSesion();
            $_SESSION['flash_error'] = 'sesion_expirada';
        }
        $_SESSION['ultima_actividad'] = time();
    }

    /** Usuario en sesión o null. */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    /**
     * Exige sesión y que la cuenta siga activa. Sin sesión: 401 (AJAX) o al login.
     * Si la persona debe cambiar su contraseña, solo la deja en esa página
     * ($paraCambiarClave = true la usan cambiar_clave.php y su endpoint).
     */
    public static function requireLogin(bool $paraCambiarClave = false): array
    {
        $usuario = self::usuario();
        if ($usuario !== null) {
            $activo = Db::valor('SELECT Activo FROM seg.Usuarios WHERE UsuarioId = ?', [$usuario['id']]);
            if ((int) $activo === 1) {
                if (!empty($usuario['debe_cambiar']) && !$paraCambiarClave) {
                    if (Http::esAjax()) {
                        Json::error('DEBE_CAMBIAR_CLAVE', 'Debe cambiar su contraseña antes de continuar.', 403);
                    }
                    Http::redirigir(View::url('INTRANET', 'cambiar_clave.php'));
                }
                return $usuario;
            }
            self::cerrarSesion();
            $_SESSION['flash_error'] = 'no_habilitado';
        }

        if (Http::esAjax()) {
            Json::error('SIN_SESION', 'Su sesión terminó. Vuelva a iniciar sesión.', 401);
        }
        $volver = (string) ($_SERVER['REQUEST_URI'] ?? '');
        Http::redirigir(View::url('INTRANET', 'login.php') . '?next=' . rawurlencode($volver));
    }

    /**
     * Exige perfil vigente en el sistema. Se consulta en cada petición,
     * así un acceso revocado se corta de inmediato.
     * Devuelve ['SistemaId','Sistema','PerfilCodigo','Perfil'].
     */
    public static function requireSistema(string $sistema): array
    {
        $usuario = self::requireLogin();
        $acceso = Db::fila(
            'SELECT SistemaId, Sistema, PerfilCodigo, Perfil
               FROM seg.vw_AccesosUsuario
              WHERE UsuarioId = ? AND SistemaCodigo = ?',
            [$usuario['id'], $sistema]
        );
        if ($acceso !== null) {
            return $acceso;
        }
        self::rechazarSinPerfil($sistema);
    }

    /** Exige uno de los perfiles indicados dentro del sistema. */
    public static function requirePerfil(string $sistema, array $perfiles): array
    {
        $acceso = self::requireSistema($sistema);
        if (in_array($acceso['PerfilCodigo'], $perfiles, true)) {
            return $acceso;
        }
        Bitacora::registrar('SIN_PERMISO', $sistema, null, null, ['perfil' => $acceso['PerfilCodigo'], 'ruta' => $_SERVER['REQUEST_URI'] ?? '']);
        if (Http::esAjax()) {
            Json::error('SIN_PERMISO', 'Su perfil no permite realizar esta acción.', 403);
        }
        View::error('Sin permiso', 'Su perfil en este sistema no permite realizar esta acción.', 403);
    }

    /** Respuesta estándar cuando el usuario no tiene perfil en el sistema. */
    public static function rechazarSinPerfil(string $sistema): never
    {
        $fila = Db::fila('SELECT Nombre FROM seg.Sistemas WHERE Codigo = ?', [$sistema]);
        Bitacora::registrar('SIN_PERFIL', $sistema, null, null, ['ruta' => $_SERVER['REQUEST_URI'] ?? '']);

        if (Http::esAjax()) {
            Json::error('SIN_PERFIL', 'Usted no tiene el perfil para este sistema', 403);
        }
        http_response_code(403);
        $usuario = self::usuario();
        View::render(INTRANET_ROOT . '/comun/views/sin_perfil.php', [
            'titulo'        => 'Sin acceso',
            'subtitulo'     => $fila['Nombre'] ?? $sistema,
            'sistemaNombre' => $fila['Nombre'] ?? $sistema,
            'menu'          => Portal::menu('', $usuario ? Portal::sistemas($usuario['id']) : [], $usuario),
        ]);
        exit;
    }

    /** Deja al usuario con sesión iniciada (después de validar su contraseña). */
    public static function iniciarComo(array $fila): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'           => (int) $fila['UsuarioId'],
            'nombre'       => (string) $fila['Nombre'],
            'email'        => (string) $fila['Email'],
            'debe_cambiar' => (int) ($fila['DebeCambiarPassword'] ?? 0) === 1,
        ];
        $_SESSION['ultima_actividad'] = time();
        unset($_SESSION['csrf']);

        Db::ejecutar(
            'UPDATE seg.Usuarios
                SET UltimoAcceso = SYSDATETIME(), IntentosFallidos = 0, BloqueadoHasta = NULL
              WHERE UsuarioId = ?',
            [$fila['UsuarioId']]
        );
        Bitacora::registrar('LOGIN_OK', 'INTRANET', 'Usuario', (int) $fila['UsuarioId']);
    }

    /** Cierra la sesión y deja una nueva vacía (para mensajes flash). */
    public static function cerrarSesion(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
