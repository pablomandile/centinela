<?php

namespace Database\Factories;

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use App\Models\Chequeo;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chequeo>
 */
class ChequeoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'tipo' => TipoChequeo::Disponibilidad,
            'estado' => EstadoChequeo::Ok,
            'codigo_http' => 200,
            'latencia_ms' => fake()->numberBetween(120, 900),
            'mensaje' => 'Contesta 200.',
            'ejecutado_at' => now(),
        ];
    }

    public function falla(?string $mensaje = null): static
    {
        return $this->state(fn () => [
            'estado' => EstadoChequeo::Falla,
            'codigo_http' => 500,
            'mensaje' => $mensaje ?? 'Contesta 500.',
        ]);
    }

    public function advertencia(?string $mensaje = null): static
    {
        return $this->state(fn () => [
            'estado' => EstadoChequeo::Advertencia,
            'mensaje' => $mensaje ?? 'Tarda más de lo esperable.',
        ]);
    }

    public function de(TipoChequeo $tipo): static
    {
        return $this->state(fn () => ['tipo' => $tipo]);
    }
}
