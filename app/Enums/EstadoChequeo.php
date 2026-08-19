<?php

namespace App\Enums;

/**
 * El resultado de una sonda.
 *
 * Tres estados y no dos: la mayoría de lo que Centinela detecta no es "está
 * caído" sino "esto va a doler dentro de un rato" —un certificado a diez días,
 * un service worker que el CDN va a servir viejo una semana—. Meter eso en la
 * misma bolsa que una caída haría que el rojo deje de significar algo.
 */
enum EstadoChequeo: string
{
    case Ok = 'ok';
    case Advertencia = 'advertencia';
    case Falla = 'falla';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ok => 'Bien',
            self::Advertencia => 'Atención',
            self::Falla => 'Falla',
        };
    }

    /**
     * Solo las fallas abren incidentes y mandan mail.
     */
    public function esFalla(): bool
    {
        return $this === self::Falla;
    }

    /**
     * Para el semáforo del tablero: cuanto más alto, más urgente.
     */
    public function gravedad(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Advertencia => 1,
            self::Falla => 2,
        };
    }
}
