<?php
declare(strict_types=1);

namespace Intranet;

use Throwable;

/** Registro de auditoría en seg.Bitacora. Nunca interrumpe la petición si falla. */
final class Bitacora
{
    public static function registrar(
        string $accion,
        ?string $sistema = null,
        ?string $entidad = null,
        ?int $entidadId = null,
        array $detalle = [],
        ?int $usuarioId = null
    ): void {
        try {
            $usuarioId ??= Auth::usuario()['id'] ?? null;
            Db::ejecutar(
                'INSERT INTO seg.Bitacora (SistemaId, UsuarioId, Accion, Entidad, EntidadId, Detalle, IP)
                 VALUES ((SELECT SistemaId FROM seg.Sistemas WHERE Codigo = ?), ?, ?, ?, ?, ?, ?)',
                [
                    $sistema,
                    $usuarioId,
                    substr($accion, 0, 40),
                    $entidad,
                    $entidadId,
                    $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
                    Http::ip(),
                ]
            );
        } catch (Throwable $e) {
            error_log('Bitacora: ' . $e->getMessage());
        }
    }
}
