<?php
/** Crear (sin ?id) o editar (?id=N) una cuenta y sus accesos. Perfil ADMIN de INTRANET. */
require dirname(__DIR__, 3) . '/comun/src/bootstrap.php';

use Intranet\Auth;
use Intranet\Portal;
use Intranet\Usuarios;
use Intranet\View;

Auth::requirePerfil('INTRANET', ['ADMIN']);
$admin = Auth::usuario();

$idTexto = (string) ($_GET['id'] ?? '');
$id = ctype_digit($idTexto) ? (int) $idTexto : 0;
$u = null;
if ($idTexto !== '') {
    $u = $id > 0 ? Usuarios::obtener($id) : null;
    if ($u === null) {
        View::error('Usuario no encontrado', 'La cuenta que busca no existe.', 404);
    }
}
$accesos = $u ? Usuarios::accesos($id) : [];

View::render(dirname(__DIR__, 2) . '/views/admin_usuario_editar.php', [
    'titulo'        => $u ? 'Editar usuario' : 'Nuevo usuario',
    'subtitulo'     => $u ? $u['Nombre'] . ' · ' . $u['Email'] : 'Cree la cuenta y asigne su perfil en cada sistema',
    'menu'          => Portal::menu('admin', Portal::sistemas($admin['id']), $admin),
    'u'             => $u,
    'esPropia'      => $u !== null && $id === (int) $admin['id'],
    'accesos'       => $accesos,
    'sistemas'      => Usuarios::sistemasConPerfiles($accesos),
    'cargos'        => Usuarios::catalogo('Cargos', $u['CargoId'] ?? null),
    'departamentos' => Usuarios::catalogo('Departamentos', $u['DepartamentoId'] ?? null),
    'actividad'     => $u ? Usuarios::actividad($id) : [],
    'scripts'       => [View::url('INTRANET', 'assets/admin_usuario.js')],
]);
