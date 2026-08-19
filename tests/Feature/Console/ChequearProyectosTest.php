<?php

use App\Enums\TipoChequeo;
use App\Models\Chequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\Certificado;
use App\Sondas\Soporte\LectorDeCertificado;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('<html>ok</html>')]);
    Mail::fake();

    // Sin socket TLS real en los tests.
    app()->instance(LectorDeCertificado::class, new class extends LectorDeCertificado
    {
        public function leer(string $host, int $puerto = 443): ?Certificado
        {
            return new Certificado(now()->addDays(60));
        }
    });
});

it('chequea los proyectos activos y no los inactivos', function () {
    $activo = Proyecto::factory()->estatico()->create();
    $inactivo = Proyecto::factory()->estatico()->inactivo()->create();

    $this->artisan('centinela:chequear')->assertSuccessful();

    expect($activo->chequeos()->count())->toBeGreaterThan(0)
        ->and($inactivo->chequeos()->count())->toBe(0);
});

it('chequea un proyecto inactivo si se lo nombra', function () {
    // Si alguien nombra un proyecto, quiere ver ese proyecto.
    $inactivo = Proyecto::factory()->estatico()->inactivo()->create(['slug' => 'dormido']);

    $this->artisan('centinela:chequear --proyecto=dormido')->assertSuccessful();

    expect($inactivo->chequeos()->count())->toBeGreaterThan(0);
});

it('puede correr una sola sonda', function () {
    $proyecto = Proyecto::factory()->estatico()->create(['slug' => 'uno']);

    $this->artisan('centinela:chequear --proyecto=uno --tipo=disponibilidad')->assertSuccessful();

    expect($proyecto->chequeos()->pluck('tipo')->unique()->all())
        ->toBe([TipoChequeo::Disponibilidad]);
});

it('avisa cuando el tipo de chequeo no existe, sin correr nada', function () {
    Proyecto::factory()->estatico()->create();

    $this->artisan('centinela:chequear --tipo=inventado')
        ->expectsOutputToContain('No existe el chequeo')
        ->assertFailed();

    expect(Chequeo::count())->toBe(0);
});

it('no corre nada si a ninguna sonda le toca todavía', function () {
    $proyecto = Proyecto::factory()->estatico()->create();
    $this->artisan('centinela:chequear')->assertSuccessful();
    $cuantos = $proyecto->chequeos()->count();

    // Sin --forzar y sin que pase el intervalo, la segunda corrida no agrega nada.
    $this->artisan('centinela:chequear')
        ->expectsOutputToContain('Nada que correr')
        ->assertSuccessful();

    expect($proyecto->chequeos()->count())->toBe($cuantos);
});

it('avisa cuando no hay proyectos', function () {
    $this->artisan('centinela:chequear')
        ->expectsOutputToContain('No hay proyectos')
        ->assertSuccessful();
});
