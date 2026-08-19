<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La cuenta de Google es válida pero su email no está en la allowlist.
 *
 * Tiene su propia clase para que el controlador la distinga de una falla real:
 * esto es un caso esperado —alguien probó entrar— y no merece un warning en el
 * log ni el mensaje genérico de error.
 */
class AccesoNoHabilitado extends RuntimeException
{
    public static function para(string $email): self
    {
        return new self("El email [{$email}] no está habilitado.");
    }
}
