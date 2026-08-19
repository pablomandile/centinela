<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;

/**
 * Todas las sondas, y el único lugar que decide cuál aplica a qué proyecto.
 *
 * Es la pieza que evita el flujo repetido: hasta ahora cada verificación vivía
 * suelta en un skill distinto y se corría a mano. Sumar una sonda nueva es
 * escribir su clase y agregarla al constructor; nada más cambia.
 */
class RegistroDeSondas
{
    /** @var list<Sonda> */
    private array $sondas;

    public function __construct(
        SondaDisponibilidad $disponibilidad,
        SondaCertificado $certificado,
        SondaRedireccionHttps $redireccion,
        SondaCacheInertia $cacheInertia,
        SondaCabecerasPwa $cabecerasPwa,
        SondaBundle $bundle,
    ) {
        // El orden es el de la pantalla de detalle: primero lo que dice si el
        // sitio está en pie, después las auditorías.
        $this->sondas = [
            $disponibilidad,
            $certificado,
            $redireccion,
            $cacheInertia,
            $cabecerasPwa,
            $bundle,
        ];
    }

    /**
     * @return list<Sonda>
     */
    public function todas(): array
    {
        return $this->sondas;
    }

    /**
     * Las que tienen sentido para este proyecto.
     *
     * @return list<Sonda>
     */
    public function para(Proyecto $proyecto): array
    {
        return array_values(array_filter(
            $this->sondas,
            fn (Sonda $sonda) => $sonda->aplicaA($proyecto),
        ));
    }

    public function porTipo(TipoChequeo $tipo): ?Sonda
    {
        foreach ($this->sondas as $sonda) {
            if ($sonda->tipo() === $tipo) {
                return $sonda;
            }
        }

        return null;
    }
}
