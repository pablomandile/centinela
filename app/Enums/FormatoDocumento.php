<?php

namespace App\Enums;

/**
 * Los dos formatos en los que existe la documentación de estos proyectos: los
 * `.md` que se escriben con el código y los `.pdf` que llegaron ya armados.
 *
 * Ojo al sumar un caso: `documentos.formato` es una columna ENUM real en MySQL.
 */
enum FormatoDocumento: string
{
    case Md = 'md';
    case Pdf = 'pdf';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Md => 'Markdown',
            self::Pdf => 'PDF',
        };
    }

    /**
     * ¿Se puede leer dentro de Centinela?
     *
     * El markdown se renderiza en pantalla; un PDF se abre o se baja, porque
     * mostrarlo embebido en el celular anda peor que el visor del propio sistema.
     */
    public function seLeeAdentro(): bool
    {
        return $this === self::Md;
    }

    public function mime(): string
    {
        return match ($this) {
            self::Md => 'text/markdown; charset=utf-8',
            self::Pdf => 'application/pdf',
        };
    }

    public static function desdeExtension(string $extension): self
    {
        return match (mb_strtolower($extension)) {
            'pdf' => self::Pdf,
            default => self::Md,
        };
    }
}
