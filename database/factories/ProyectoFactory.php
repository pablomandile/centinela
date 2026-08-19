<?php

namespace Database\Factories;

use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Proyecto>
 */
class ProyectoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un número y no palabras al azar: `words()` está tipado `array|string` y
        // el slug tiene que ser único sí o sí (es la clave de ruta).
        $nombre = 'Proyecto '.fake()->unique()->numberBetween(1000, 9999);

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'url' => 'https://'.Str::slug($nombre).'.pablomandile.com.ar',
            'usa_inertia' => true,
            'es_pwa' => false,
            'tiene_bundle' => true,
            'activo' => true,
            'intervalo_minutos' => 15,
            'orden' => 0,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function estatico(): static
    {
        return $this->state(fn () => [
            'usa_inertia' => false,
            'es_pwa' => false,
            'tiene_bundle' => false,
        ]);
    }

    public function pwa(): static
    {
        return $this->state(fn () => ['es_pwa' => true]);
    }
}
