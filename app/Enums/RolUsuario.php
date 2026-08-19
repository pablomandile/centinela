<?php

namespace App\Enums;

/**
 * Centinela es de un solo usuario, pero el rol existe desde la primera
 * migración: así invitar a alguien a ver la documentación de un proyecto es
 * agregar una fila y un caso en la Policy, no rehacer la autorización.
 *
 * Ojo al sumar un caso: `users.rol` es una columna ENUM real en MySQL. Los casos
 * de PHP solos pasan los tests —sqlite no valida ENUM— y revientan en producción
 * con un 500 al primer guardado.
 */
enum RolUsuario: string
{
    case Admin = 'admin';
    case Lector = 'lector';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Lector => 'Lector',
        };
    }

    /**
     * Solo el admin ve el tablero de salud y administra proyectos.
     */
    public function esAdmin(): bool
    {
        return $this === self::Admin;
    }
}
