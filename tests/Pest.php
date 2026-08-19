<?php

use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Los tests unitarios también necesitan la app arrancada: los casts de Eloquent
// (fechas, enums) resuelven config del container.
pest()->extend(TestCase::class)->in('Unit');

/**
 * Un proyecto sobre un dominio de prueba, para las sondas.
 *
 * Vive acá y no en cada archivo de test porque las funciones que se declaran en un
 * test de Pest son globales: dos archivos con la misma helper chocan y el error no
 * dice cuál es el otro.
 *
 * @param  array<string, mixed>  $atributos
 */
function proyecto(array $atributos = []): Proyecto
{
    return Proyecto::factory()->create([
        'url' => 'https://ejemplo.test',
        ...$atributos,
    ]);
}
