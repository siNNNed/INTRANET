<?php
declare(strict_types=1);

namespace Intranet;

use PDOException;
use Throwable;

/**
 * Administración de cuentas de la intranet y de sus accesos por sistema.
 * La usan admin/index.php, admin/usuario.php y api/usuarios/guardar.php.
 */
final class Usuarios
{
    /** Filtros permitidos del listado (lista blanca). */
    public const ESTADOS = ['activos', 'inactivos', 'todos'];

    /**
     * Listado con filtros: q (nombre, correo o RUT), sistema (código), perfil (código o NINGUNO), estado.
     * Devuelve las filas con la clave 'accesos' (lista de accesos del usuario).
     */
    public static function listar(array $f): array
    {
        $where = [];
        $p = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . strtr($q, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_', '[' => '\\[']) . '%';
            $where[] = "(u.Nombre LIKE ? ESCAPE '\\' OR u.Email LIKE ? ESCAPE '\\' OR u.Rut LIKE ? ESCAPE '\\')";
            array_push($p, $like, $like, $like);
        }

        $estado = in_array($f['estado'] ?? '', self::ESTADOS, true) ? $f['estado'] : 'activos';
        if ($estado === 'activos') {
            $where[] = 'u.Activo = 1';
        } elseif ($estado === 'inactivos') {
            $where[] = 'u.Activo = 0';
        }

        $sistema = (string) ($f['sistema'] ?? '');
        $perfil  = (string) ($f['perfil'] ?? '');
        $subAcceso = 'SELECT 1 FROM seg.UsuarioPerfiles x
                         JOIN seg.Sistemas xs ON xs.SistemaId = x.SistemaId
                         JOIN seg.Perfiles xp ON xp.PerfilId = x.PerfilId
                        WHERE x.UsuarioId = u.UsuarioId';
        if ($perfil === 'NINGUNO') {
            $where[] = "NOT EXISTS ($subAcceso" . ($sistema !== '' ? ' AND xs.Codigo = ?' : '') . ')';
            if ($sistema !== '') {
                $p[] = $sistema;
            }
        } elseif ($perfil !== '' || $sistema !== '') {
            $cond = $subAcceso;
            if ($sistema !== '') {
                $cond .= ' AND xs.Codigo = ?';
                $p[] = $sistema;
            }
            if ($perfil !== '') {
                $cond .= ' AND xp.Codigo = ?';
                $p[] = $perfil;
            }
            $where[] = "EXISTS ($cond)";
        }

        $filas = Db::todas(
            'SELECT u.UsuarioId, u.Nombre, u.Email, u.Rut, u.MetodoAcceso, u.Activo, u.UltimoAcceso,
                    c.Nombre AS Cargo
               FROM seg.Usuarios u
               LEFT JOIN seg.Cargos c ON c.CargoId = u.CargoId'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY u.Nombre',
            $p
        );

        $accesos = [];
        foreach (Db::todas(
            'SELECT up.UsuarioId, s.Nombre AS Sistema, s.Codigo AS SistemaCodigo, p.Nombre AS Perfil, up.VigenteHasta,
                    CASE WHEN up.VigenteHasta < CAST(SYSDATETIME() AS DATE) THEN 1 ELSE 0 END AS Vencido
               FROM seg.UsuarioPerfiles up
               JOIN seg.Sistemas s ON s.SistemaId = up.SistemaId
               JOIN seg.Perfiles p ON p.PerfilId = up.PerfilId
              ORDER BY s.Orden'
        ) as $a) {
            $accesos[(int) $a['UsuarioId']][] = $a;
        }
        foreach ($filas as &$fila) {
            $fila['accesos'] = $accesos[(int) $fila['UsuarioId']] ?? [];
        }
        return $filas;
    }

    public static function obtener(int $id): ?array
    {
        return Db::fila(
            'SELECT UsuarioId, Nombre, Email, Rut, MetodoAcceso, GoogleSub, CargoId, DepartamentoId, Activo,
                    UltimoAcceso, BloqueadoHasta, CreadoEn,
                    CASE WHEN BloqueadoHasta > SYSDATETIME() THEN 1 ELSE 0 END AS Bloqueado
               FROM seg.Usuarios WHERE UsuarioId = ?',
            [$id]
        );
    }

    /** Accesos actuales: [SistemaId => ['PerfilId', 'VigenteHasta' (AAAA-MM-DD o null)]]. */
    public static function accesos(int $id): array
    {
        $r = [];
        foreach (Db::todas('SELECT SistemaId, PerfilId, CONVERT(char(10), VigenteHasta, 23) AS VigenteHasta FROM seg.UsuarioPerfiles WHERE UsuarioId = ?', [$id]) as $a) {
            $r[(int) $a['SistemaId']] = ['PerfilId' => (int) $a['PerfilId'], 'VigenteHasta' => $a['VigenteHasta']];
        }
        return $r;
    }

    /** Sistemas activos con sus perfiles activos (más el perfil actual del usuario aunque esté inactivo). */
    public static function sistemasConPerfiles(array $accesosActuales = []): array
    {
        $sistemas = [];
        foreach (Db::todas('SELECT SistemaId, Codigo, Nombre, Descripcion FROM seg.Sistemas WHERE Activo = 1 ORDER BY Orden, Nombre') as $s) {
            $s['perfiles'] = [];
            $sistemas[(int) $s['SistemaId']] = $s;
        }
        foreach (Db::todas('SELECT PerfilId, SistemaId, Codigo, Nombre, Activo FROM seg.Perfiles ORDER BY PerfilId') as $p) {
            $sid = (int) $p['SistemaId'];
            $actual = ($accesosActuales[$sid]['PerfilId'] ?? 0) === (int) $p['PerfilId'];
            if (isset($sistemas[$sid]) && ((int) $p['Activo'] === 1 || $actual)) {
                $sistemas[$sid]['perfiles'][(int) $p['PerfilId']] = $p;
            }
        }
        return $sistemas;
    }

    /** Opciones activas de un catálogo (seg.Cargos o seg.Departamentos), más el valor actual. */
    public static function catalogo(string $tabla, ?int $actual = null): array
    {
        $tablas = ['Cargos' => 'CargoId', 'Departamentos' => 'DepartamentoId'];   // lista blanca
        $col = $tablas[$tabla] ?? throw new \InvalidArgumentException($tabla);
        return Db::todas("SELECT $col AS Id, Nombre FROM seg.$tabla WHERE Activo = 1 OR $col = ? ORDER BY Nombre", [$actual ?? 0]);
    }

    /** Actividad reciente de la cuenta, en texto. */
    public static function actividad(int $id, int $cantidad = 8): array
    {
        $filas = Db::todas(
            "SELECT TOP (" . max(1, min(50, $cantidad)) . ") b.Accion, b.Detalle, b.Fecha, b.UsuarioId, a.Nombre AS Actor, s.Nombre AS Sistema
               FROM seg.Bitacora b
               LEFT JOIN seg.Usuarios a ON a.UsuarioId = b.UsuarioId
               LEFT JOIN seg.Sistemas s ON s.SistemaId = b.SistemaId
              WHERE (b.Entidad = 'Usuario' AND b.EntidadId = ?)
                 OR (b.UsuarioId = ? AND b.Accion IN ('SIN_PERFIL', 'SIN_PERMISO'))
              ORDER BY b.Fecha DESC, b.BitacoraId DESC",
            [$id, $id]
        );
        $r = [];
        foreach ($filas as $f) {
            $d = json_decode((string) ($f['Detalle'] ?? ''), true) ?: [];
            $actor = $f['Actor'] ?? 'El sistema';
            $texto = match ($f['Accion']) {
                'LOGIN_OK'          => ($d['metodo'] ?? '') === 'LOCAL' ? 'Ingresó con contraseña' : 'Ingresó con Google',
                'LOGIN_FALLIDO'     => 'Intento de ingreso fallido',
                'SIN_PERFIL'        => 'Intentó entrar a ' . ($f['Sistema'] ?? 'un sistema') . ' sin perfil',
                'SIN_PERMISO'       => 'Intentó una acción sin permiso en ' . ($f['Sistema'] ?? 'un sistema'),
                'USUARIO_CREADO'    => $actor . ' creó la cuenta',
                'USUARIO_EDITADO'   => $actor . ' editó los datos de la cuenta',
                'ACCESO_ASIGNADO'   => $actor . ' le asignó ' . ($d['perfil'] ?? 'un perfil') . ' en ' . ($d['sistema'] ?? 'un sistema'),
                'ACCESO_MODIFICADO' => $actor . ' cambió su acceso en ' . ($d['sistema'] ?? 'un sistema') . ' a ' . ($d['perfil'] ?? 'otro perfil'),
                'ACCESO_QUITADO'    => $actor . ' le quitó el acceso a ' . ($d['sistema'] ?? 'un sistema'),
                default             => $f['Accion'],
            };
            if (!empty($d['vigente'])) {
                $texto .= ' (hasta ' . date('d-m-Y', strtotime((string) $d['vigente'])) . ')';
            }
            $r[] = ['texto' => $texto, 'fecha' => $f['Fecha']];
        }
        return $r;
    }

    /**
     * Valida y guarda una cuenta con sus accesos, en una transacción.
     * $e: id, nombre, email, rut, cargo, departamento, activo, metodo, password, password2, accesos[SistemaId][perfil|vigente].
     * Devuelve ['id' => int, 'creado' => bool]. Errores de validación: Json::error (400) o conflicto (409).
     */
    public static function guardar(array $e, int $adminId): array
    {
        $id       = (int) ($e['id'] ?? 0);
        $actual   = $id > 0 ? self::obtener($id) : null;
        if ($id > 0 && $actual === null) {
            Json::error('NO_EXISTE', 'El usuario no existe.', 404);
        }
        $esPropia = $id === $adminId;

        $nombre = preg_replace('/\s+/u', ' ', trim((string) ($e['nombre'] ?? ''))) ?? '';
        $email  = mb_strtolower(trim((string) ($e['email'] ?? '')));
        $rutIn  = trim((string) ($e['rut'] ?? ''));
        $cargo  = (string) ($e['cargo'] ?? '');
        $depto  = (string) ($e['departamento'] ?? '');
        $activo = (string) ($e['activo'] ?? '0') === '1' ? 1 : 0;
        $metodo = (string) ($e['metodo'] ?? '');
        $pass   = (string) ($e['password'] ?? '');
        $pass2  = (string) ($e['password2'] ?? '');
        $entrada = is_array($e['accesos'] ?? null) ? $e['accesos'] : [];

        $campos = [];
        if (!Validator::texto($nombre, 3, 100)) {
            $campos['nombre'] = 'Ingrese el nombre completo (3 a 100 caracteres).';
        }
        if (!in_array($metodo, ['GOOGLE', 'LOCAL'], true)) {
            $campos['metodo'] = 'Elija la forma de ingreso.';
            $metodo = 'GOOGLE';
        }
        $dominios = Parametros::dominiosGoogle();
        if (!Validator::email($email)) {
            $campos['email'] = 'Ingrese un correo válido.';
        } elseif ($metodo === 'GOOGLE' && !Validator::emailDeDominio($email, $dominios)) {
            $campos['email'] = 'Para ingresar con Google, el correo debe terminar en @' . implode(' o @', $dominios) . '.';
        }
        $rut = null;
        if ($rutIn !== '') {
            if (!Validator::rut($rutIn)) {
                $campos['rut'] = 'El RUT no es válido. Revise el dígito verificador.';
            } else {
                $rut = Validator::normalizarRut($rutIn);
            }
        }
        $cargoId = self::idDeCatalogo('Cargos', $cargo, $actual['CargoId'] ?? null, $campos, 'cargo');
        $deptoId = self::idDeCatalogo('Departamentos', $depto, $actual['DepartamentoId'] ?? null, $campos, 'departamento');

        // Contraseña: obligatoria al crear una cuenta LOCAL o al pasar de GOOGLE a LOCAL.
        $pideClave = $metodo === 'LOCAL' && ($actual === null || $actual['MetodoAcceso'] !== 'LOCAL');
        $hash = null;
        if ($metodo === 'LOCAL' && ($pass !== '' || $pideClave)) {
            if (strlen($pass) > 200 || !Validator::password($pass)) {
                $campos['password'] = 'Mínimo 10 caracteres, con letras y números.';
            } elseif (!hash_equals($pass, $pass2)) {
                $campos['password2'] = 'Las contraseñas no coinciden.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
            }
        }

        // Accesos por sistema
        $actuales = $id > 0 ? self::accesos($id) : [];
        $sistemas = self::sistemasConPerfiles($actuales);
        $hoy = date('Y-m-d');
        $nuevos = [];   // SistemaId => ['PerfilId', 'VigenteHasta'] o null (sin acceso)
        foreach ($sistemas as $sid => $s) {
            $perfilIn  = (string) ($entrada[$sid]['perfil'] ?? '');
            $vigenteIn = trim((string) ($entrada[$sid]['vigente'] ?? ''));
            $clave = "accesos[$sid]";
            if ($perfilIn === '') {
                $nuevos[$sid] = null;
                continue;
            }
            if (!ctype_digit($perfilIn) || !isset($s['perfiles'][(int) $perfilIn])) {
                $campos[$clave . '[perfil]'] = 'Elija un perfil de la lista.';
                continue;
            }
            $vigente = null;
            if ($vigenteIn !== '') {
                if (!Validator::fecha($vigenteIn)) {
                    $campos[$clave . '[vigente]'] = 'Fecha no válida.';
                    continue;
                }
                $sinCambio = ($actuales[$sid]['VigenteHasta'] ?? null) === $vigenteIn;
                if ($vigenteIn < $hoy && !$sinCambio) {
                    $campos[$clave . '[vigente]'] = 'Debe ser hoy o una fecha posterior.';
                    continue;
                }
                $vigente = $vigenteIn;
            }
            $nuevos[$sid] = ['PerfilId' => (int) $perfilIn, 'VigenteHasta' => $vigente];
        }

        // Protecciones de la propia cuenta: que el administrador no se deje fuera.
        if ($esPropia) {
            if ($activo !== 1) {
                $campos['activo'] = 'No puede desactivar su propia cuenta.';
            }
            if ($metodo !== $actual['MetodoAcceso']) {
                $campos['metodo'] = 'No puede cambiar la forma de ingreso de su propia cuenta.';
            }
            foreach ($sistemas as $sid => $s) {
                if ($s['Codigo'] !== 'INTRANET') {
                    continue;
                }
                $n = $nuevos[$sid] ?? null;
                $esAdmin = $n !== null && ($s['perfiles'][$n['PerfilId']]['Codigo'] ?? '') === 'ADMIN';
                if (!$esAdmin) {
                    $campos["accesos[$sid][perfil]"] = 'No puede quitarse el perfil de administrador de la intranet.';
                } elseif ($n['VigenteHasta'] !== null) {
                    $campos["accesos[$sid][vigente]"] = 'Su propio acceso de administrador no puede vencer.';
                }
            }
        }

        if ($campos) {
            Json::error('VALIDACION', 'Revise los campos marcados.', 400, $campos);
        }

        // Correo y RUT únicos (también los protege la base).
        if (Db::valor('SELECT 1 FROM seg.Usuarios WHERE Email = ? AND UsuarioId <> ?', [$email, $id]) !== null) {
            Json::error('DUPLICADO', 'Revise los campos marcados.', 409, ['email' => 'Ya existe una cuenta con este correo.']);
        }
        if ($rut !== null && Db::valor('SELECT 1 FROM seg.Usuarios WHERE Rut = ? AND UsuarioId <> ?', [$rut, $id]) !== null) {
            Json::error('DUPLICADO', 'Revise los campos marcados.', 409, ['rut' => 'Ya existe una cuenta con este RUT.']);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($actual === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO seg.Usuarios (Nombre, Email, Rut, MetodoAcceso, PasswordHash, CargoId, DepartamentoId, Activo, DebeCambiarPassword, CreadoPor)
                     OUTPUT INSERTED.UsuarioId
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
                );
                $stmt->execute([$nombre, $email, $rut, $metodo, $metodo === 'LOCAL' ? $hash : null, $cargoId, $deptoId, $activo, $adminId]);
                $id = (int) $stmt->fetchColumn();
                $stmt->closeCursor();
                Bitacora::registrar('USUARIO_CREADO', 'INTRANET', 'Usuario', $id, ['email' => $email, 'metodo' => $metodo]);
            } else {
                $cambios = [];
                foreach (['Nombre' => $nombre, 'Email' => $email, 'Rut' => $rut, 'MetodoAcceso' => $metodo] as $col => $valor) {
                    if ((string) ($actual[$col] ?? '') !== (string) ($valor ?? '')) {
                        $cambios[] = $col;
                    }
                }
                foreach (['CargoId' => $cargoId, 'DepartamentoId' => $deptoId, 'Activo' => $activo] as $col => $valor) {
                    if ((int) ($actual[$col] ?? 0) !== (int) ($valor ?? 0)) {
                        $cambios[] = $col;
                    }
                }
                if ($hash !== null) {
                    $cambios[] = 'Contraseña';
                }
                $cambioCorreo = $actual['Email'] !== $email;
                Db::ejecutar(
                    'UPDATE seg.Usuarios
                        SET Nombre = ?, Email = ?, Rut = ?, MetodoAcceso = ?, CargoId = ?, DepartamentoId = ?, Activo = ?,
                            PasswordHash = CASE WHEN ? = \'GOOGLE\' THEN NULL WHEN ? IS NOT NULL THEN ? ELSE PasswordHash END,
                            IntentosFallidos = CASE WHEN ? IS NOT NULL OR ? = \'GOOGLE\' THEN 0 ELSE IntentosFallidos END,
                            BloqueadoHasta   = CASE WHEN ? IS NOT NULL OR ? = \'GOOGLE\' THEN NULL ELSE BloqueadoHasta END,
                            GoogleSub        = CASE WHEN ? = 1 THEN NULL ELSE GoogleSub END,
                            ActualizadoEn = SYSDATETIME()
                      WHERE UsuarioId = ?',
                    [$nombre, $email, $rut, $metodo, $cargoId, $deptoId, $activo,
                     $metodo, $hash, $hash,
                     $hash, $metodo,
                     $hash, $metodo,
                     $cambioCorreo ? 1 : 0,
                     $id]
                );
                if ($cambios) {
                    Bitacora::registrar('USUARIO_EDITADO', 'INTRANET', 'Usuario', $id, ['campos' => $cambios]);
                }
            }

            foreach ($nuevos as $sid => $n) {
                $antes = $actuales[$sid] ?? null;
                $nombreSistema = $sistemas[$sid]['Nombre'];
                if ($n === null) {
                    if ($antes !== null) {
                        Db::ejecutar('DELETE FROM seg.UsuarioPerfiles WHERE UsuarioId = ? AND SistemaId = ?', [$id, $sid]);
                        Bitacora::registrar('ACCESO_QUITADO', 'INTRANET', 'Usuario', $id, ['sistema' => $nombreSistema]);
                    }
                    continue;
                }
                $detalle = ['sistema' => $nombreSistema, 'perfil' => $sistemas[$sid]['perfiles'][$n['PerfilId']]['Nombre'], 'vigente' => $n['VigenteHasta']];
                if ($antes === null) {
                    Db::ejecutar(
                        'INSERT INTO seg.UsuarioPerfiles (UsuarioId, SistemaId, PerfilId, VigenteHasta, AsignadoPor) VALUES (?, ?, ?, ?, ?)',
                        [$id, $sid, $n['PerfilId'], $n['VigenteHasta'], $adminId]
                    );
                    Bitacora::registrar('ACCESO_ASIGNADO', 'INTRANET', 'Usuario', $id, $detalle);
                } elseif ($antes['PerfilId'] !== $n['PerfilId'] || $antes['VigenteHasta'] !== $n['VigenteHasta']) {
                    Db::ejecutar(
                        'UPDATE seg.UsuarioPerfiles SET PerfilId = ?, VigenteHasta = ?, AsignadoPor = ?, AsignadoEn = SYSDATETIME()
                          WHERE UsuarioId = ? AND SistemaId = ?',
                        [$n['PerfilId'], $n['VigenteHasta'], $adminId, $id, $sid]
                    );
                    Bitacora::registrar('ACCESO_MODIFICADO', 'INTRANET', 'Usuario', $id, $detalle);
                }
            }

            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if (in_array((int) ($ex->errorInfo[1] ?? 0), [2601, 2627], true)) {   // clave única
                Json::error('DUPLICADO', 'Ya existe una cuenta con ese correo o RUT.', 409);
            }
            throw $ex;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }

        return ['id' => $id, 'creado' => $actual === null];
    }

    /** Valida un id de catálogo ('' = ninguno). Acepta el valor actual aunque esté inactivo. */
    private static function idDeCatalogo(string $tabla, string $valor, mixed $actual, array &$campos, string $campo): ?int
    {
        if ($valor === '') {
            return null;
        }
        if (!ctype_digit($valor)) {
            $campos[$campo] = 'Elija una opción de la lista.';
            return null;
        }
        $ids = array_map(static fn ($f) => (int) $f['Id'], self::catalogo($tabla, $actual !== null ? (int) $actual : null));
        if (!in_array((int) $valor, $ids, true)) {
            $campos[$campo] = 'Elija una opción de la lista.';
            return null;
        }
        return (int) $valor;
    }
}
