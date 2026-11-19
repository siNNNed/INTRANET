<?php
declare(strict_types=1);

namespace Intranet;

/** Datos y menú del portal de la intranet. */
final class Portal
{
    /** Todos los sistemas activos con la marca TienePerfil para el usuario. */
    public static function sistemas(int $usuarioId): array
    {
        return Db::todas(
            'SELECT SistemaId, SistemaCodigo, Sistema, Descripcion, Url, Icono, Orden, PerfilCodigo, Perfil, TienePerfil
               FROM seg.fn_PortalUsuario(?)
              ORDER BY Orden, Sistema',
            [$usuarioId]
        );
    }

    /** ¿El usuario es administrador de la intranet? */
    public static function esAdmin(array $sistemas): bool
    {
        foreach ($sistemas as $s) {
            if ($s['SistemaCodigo'] === 'INTRANET' && $s['PerfilCodigo'] === 'ADMIN') {
                return true;
            }
        }
        return false;
    }

    /**
     * Menú lateral del portal.
     * Cada ítem: ['texto','url','icono','activo'?,'bloqueado'?]; las secciones: ['seccion'=>'Texto'].
     */
    public static function menu(string $activo, array $sistemas, ?array $usuario): array
    {
        $menu = [['texto' => 'Portada', 'url' => View::url('INTRANET', ''), 'icono' => 'home', 'activo' => $activo === 'portada']];

        $otros = array_filter($sistemas, static fn ($s) => $s['SistemaCodigo'] !== 'INTRANET');
        if ($otros) {
            $menu[] = ['seccion' => 'Sistemas'];
            foreach ($otros as $s) {
                $menu[] = self::itemSistema($s);
            }
        }

        if (self::esAdmin($sistemas)) {
            $menu[] = ['seccion' => 'Administración'];
            $menu[] = ['texto' => 'Usuarios y accesos', 'url' => View::url('INTRANET', 'admin/'), 'icono' => 'users', 'activo' => $activo === 'admin'];
        } else {
            foreach ($sistemas as $s) {
                if ($s['SistemaCodigo'] === 'INTRANET') {
                    $menu[] = ['seccion' => 'Administración'];
                    $menu[] = self::itemSistema($s, 'Administración');
                }
            }
        }
        return $menu;
    }

    /** Enlace a un sistema: su URL si tiene perfil, o la página "sin perfil". */
    public static function urlSistema(array $s): string
    {
        return (int) $s['TienePerfil'] === 1
            ? rtrim((string) $s['Url'], '/') . '/'
            : View::url('INTRANET', 'sin_perfil.php') . '?s=' . rawurlencode((string) $s['SistemaCodigo']);
    }

    private static function itemSistema(array $s, ?string $texto = null): array
    {
        return [
            'texto'     => $texto ?? $s['Sistema'],
            'url'       => self::urlSistema($s),
            'icono'     => $s['Icono'] ?: 'layers',
            'bloqueado' => (int) $s['TienePerfil'] !== 1,
        ];
    }

    /** Últimos comunicados vigentes. */
    public static function comunicados(int $cantidad = 3): array
    {
        return Db::todas(
            'SELECT TOP (' . max(1, min(20, $cantidad)) . ') ComunicadoId, Categoria, Titulo, Resumen, PublicadoEn
               FROM seg.Comunicados
              WHERE Activo = 1
                AND PublicadoEn <= SYSDATETIME()
                AND (VigenteHasta IS NULL OR VigenteHasta >= CAST(SYSDATETIME() AS DATE))
              ORDER BY Destacado DESC, PublicadoEn DESC'
        );
    }
}
