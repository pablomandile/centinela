<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

/**
 * Quién puede ver y tocar la documentación.
 *
 * Hoy solo hay un admin, pero la distinción ya existe: un lector podrá **leer**
 * documentación sin poder subir, borrar ni ver el tablero de salud. Ese es el día
 * para el que el rol existe desde la primera migración.
 */
class DocumentoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function view(User $usuario, Documento $documento): bool
    {
        return true;
    }

    public function create(User $usuario): bool
    {
        return $usuario->esAdmin();
    }

    public function update(User $usuario, Documento $documento): bool
    {
        return $usuario->esAdmin();
    }

    public function delete(User $usuario, Documento $documento): bool
    {
        return $usuario->esAdmin();
    }
}
