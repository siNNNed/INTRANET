<?php
/**
 * Administración de la intranet (perfil ADMIN del sistema INTRANET).
 * Por ahora muestra los usuarios y sus accesos en modo lectura;
 * la creación y edición se construye en la siguiente etapa.
 */
require dirname(__DIR__, 3) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Db;
use Intranet\Portal;
use Intranet\View;

Auth::requirePerfil('INTRANET', ['ADMIN']);
$usuario = Auth::usuario();

$filas = Db::todas(
    "SELECT u.UsuarioId, u.Nombre, u.Email, u.MetodoAcceso, u.Activo, u.UltimoAcceso,
            c.Nombre AS Cargo,
            STRING_AGG(CASE WHEN s.SistemaId IS NOT NULL THEN CONCAT(s.Nombre, ': ', p.Nombre) END, ' | ') WITHIN GROUP (ORDER BY s.Orden) AS Accesos
       FROM seg.Usuarios u
       LEFT JOIN seg.Cargos c           ON c.CargoId = u.CargoId
       LEFT JOIN seg.UsuarioPerfiles up ON up.UsuarioId = u.UsuarioId
       LEFT JOIN seg.Sistemas s         ON s.SistemaId = up.SistemaId
       LEFT JOIN seg.Perfiles p         ON p.PerfilId = up.PerfilId
      GROUP BY u.UsuarioId, u.Nombre, u.Email, u.MetodoAcceso, u.Activo, u.UltimoAcceso, c.Nombre
      ORDER BY u.Nombre"
);

View::render(dirname(__DIR__, 2) . '/views/admin_usuarios.php', [
    'titulo'    => 'Usuarios y accesos',
    'subtitulo' => 'Cuentas de la intranet y su perfil en cada sistema',
    'menu'      => Portal::menu('admin', Portal::sistemas($usuario['id']), $usuario),
    'filas'     => $filas,
]);
