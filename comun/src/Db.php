<?php
declare(strict_types=1);

namespace Intranet;

use PDO;

/**
 * Conexión única a SQL Server. Todas las consultas van con parámetros:
 *   Db::fila('SELECT ... WHERE Email = ?', [$email]);
 * Nunca concatenar datos del usuario en el SQL.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                (string) Config::get('db.dsn'),
                (string) Config::get('db.user'),
                (string) Config::get('db.pass'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
                ]
            );
        }
        return self::$pdo;
    }

    /** Primera fila o null. */
    public static function fila(string $sql, array $parametros = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametros);
        $fila = $stmt->fetch();
        $stmt->closeCursor();
        return $fila === false ? null : $fila;
    }

    /** Todas las filas. */
    public static function todas(string $sql, array $parametros = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    /** Primera columna de la primera fila o null. */
    public static function valor(string $sql, array $parametros = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametros);
        $valor = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $valor === false ? null : $valor;
    }

    /** INSERT/UPDATE/DELETE. Devuelve las filas afectadas. */
    public static function ejecutar(string $sql, array $parametros = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->rowCount();
    }
}
