<?php
declare(strict_types=1);

namespace Intranet;

use RuntimeException;

/** Error esperado del inicio de sesión. $codigo se traduce a un mensaje en login.php. */
final class LoginError extends RuntimeException
{
    public function __construct(public readonly string $codigo, string $detalle = '', public readonly ?string $email = null)
    {
        parent::__construct($detalle !== '' ? $detalle : $codigo);
    }
}
