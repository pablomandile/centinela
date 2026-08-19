<?php

namespace App\Policies;

use App\Models\Proyecto;
use App\Models\User;

/**
 * El tablero de salud es del admin.
 *
 * Hoy el admin es el único usuario que existe, pero la pregunta se hace igual:
 * el día que haya un lector invitado para ver la documentación de un proyecto, no
 * tiene por qué ver si el sitio se cayó ni poder editar su URL.
 */
class ProyectoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->esAdmin();
    }

    public function view(User $usuario, Proyecto $proyecto): bool
    {
        return $usuario->esAdmin();
    }

    public function create(User $usuario): bool
    {
        return $usuario->esAdmin();
    }

    public function update(User $usuario, Proyecto $proyecto): bool
    {
        return $usuario->esAdmin();
    }

    public function delete(User $usuario, Proyecto $proyecto): bool
    {
        return $usuario->esAdmin();
    }
}
