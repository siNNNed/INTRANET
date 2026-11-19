<?php
declare(strict_types=1);

namespace Intranet;

use PDO;
use RuntimeException;

/**
 * Conexiones a SQL Server, una por base: Intranet (seguridad común) y una por sistema.
 * El nombre lógico de cada base está en config.php → db.bases:
 *   Db::fila('SELECT ... WHERE Email = ?', [$email]);                 // base INTRANET
 *   Db::fila('SELECT ... FROM egr.Egresos WHERE ...', [$id], 'EGRESOS');
 * Todas las consultas van con parámetros. Nunca concatenar datos del usuario en el SQL.
 */
final class Db
{
    /** @var array<string, PDO> */
    private static array $conexiones = [];

    public static function pdo(string $base = 'INTRANET'): PDO
    {
        if (!isset(self::$conexiones[$base])) {
            $nombre = Config::get('db.bases.' . $base);
            if (!is_string($nombre) || $nombre === '') {
                throw new RuntimeException("La base '$base' no está definida en config.php (db.bases).");
            }
            $dsn = 'sqlsrv:Server=' . Config::get('db.servidor', 'localhost') . ';Database=' . $nombre;
            $opciones = (string) Config::get('db.opciones', '');
            if ($opciones !== '') {
                $dsn .= ';' . $opciones;
            }
            self::$conexiones[$base] = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
            ]);
        }
        return self::$conexiones[$base];
    }

    /** Primera fila o null. */
    public static function fila(string $sql, array $parametros = [], string $base = 'INTRANET'): ?array
    {
        $stmt = self::pdo($base)->prepare($sql);
        $stmt->execute($parametros);
        $fila = $stmt->fetch();
        $stmt->closeCursor();
        return $fila === false ? null : $fila;
    }

    /** Todas las filas. */
    public static function todas(string $sql, array $parametros = [], string $base = 'INTRANET'): array
    {
        $stmt = self::pdo($base)->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    /** Primera columna de la primera fila o null. */
    public static function valor(string $sql, array $parametros = [], string $base = 'INTRANET'): mixed
    {
        $stmt = self::pdo($base)->prepare($sql);
        $stmt->execute($parametros);
        $valor = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $valor === false ? null : $valor;
    }

    /** INSERT/UPDATE/DELETE. Devuelve las filas afectadas. */
    public static function ejecutar(string $sql, array $parametros = [], string $base = 'INTRANET'): int
    {
        $stmt = self::pdo($base)->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->rowCount();
    }
}
