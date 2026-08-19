<?php

namespace Database\Factories;

use App\Enums\TipoChequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incidente>
 */
class IncidenteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'tipo' => TipoChequeo::Disponibilidad,
            'abierto_at' => now()->subHour(),
            'cerrado_at' => null,
            'ultimo_mensaje' => 'Contesta 500.',
        ];
    }

    public function de(TipoChequeo $tipo): static
    {
        return $this->state(fn () => ['tipo' => $tipo]);
    }

    public function cerrado(): static
    {
        return $this->state(fn () => ['cerrado_at' => now()]);
    }

    public function avisado(): static
    {
        return $this->state(fn () => ['avisado_at' => now()->subHour()]);
    }
}
