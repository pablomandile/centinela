<?php

use App\Enums\RolUsuario;
use App\Enums\TipoChequeo;
use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('pide sesión', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('muestra cada proyecto con el último chequeo de cada tipo', function () {
    $proyecto = Proyecto::factory()->create(['nombre' => 'Huella', 'slug' => 'huella']);

    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subHour(), 'mensaje' => 'viejo']);
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now(), 'mensaje' => 'nuevo']);
    Chequeo::factory()->for($proyecto)->de(TipoChequeo::CacheInertia)->falla('el JSON se puede guardar')->create();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('Tablero')
            ->has('proyectos', 1)
            // Dos tipos, no tres chequeos: del de disponibilidad solo el último.
            ->has('proyectos.0.chequeos', 2)
            ->where('proyectos.0.nombre', 'Huella')
            ->where('resumen.proyectos', 1),
        );
});

it('dice cuándo vuelve a chequear, y nada si el proyecto está inactivo', function () {
    $activo = Proyecto::factory()->create(['slug' => 'activo', 'orden' => 0]);
    Chequeo::factory()->for($activo)->create(['ejecutado_at' => now()->subMinutes(5)]);

    Proyecto::factory()->inactivo()->create(['slug' => 'dormido', 'orden' => 1]);

    $pagina = $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('proyectos.0.slug', 'activo')
            // Un proyecto inactivo no tiene próximo chequeo, y eso no es lo mismo
            // que no saberlo: la tarjeta dice "no se chequea".
            ->where('proyectos.1.proximo', null),
        );

    $proximo = $pagina->viewData('page')['props']['proyectos'][0]['proximo'];

    expect($proximo)->not->toBeNull()
        ->and(CarbonImmutable::parse($proximo)->isAfter(now()))->toBeTrue();
});

it('cuenta los incidentes abiertos y no los cerrados', function () {
    $proyecto = Proyecto::factory()->create();
    Incidente::factory()->for($proyecto)->create();
    Incidente::factory()->for($proyecto)->de(TipoChequeo::Bundle)->cerrado()->create();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('proyectos.0.incidentes', 1)
            ->where('resumen.incidentes', 1),
        );
});

it('cuenta los que no se chequearon nunca', function () {
    // Un proyecto sin chequeos no está sano: está sin mirar, y eso se dice.
    Proyecto::factory()->create();
    $chequeado = Proyecto::factory()->create();
    Chequeo::factory()->for($chequeado)->create();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $pagina) => $pagina->where('resumen.sinChequear', 1));
});

it('no cuenta los inactivos en el resumen, pero los lista', function () {
    Proyecto::factory()->create();
    Proyecto::factory()->inactivo()->create();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('proyectos', 2)
            ->where('resumen.proyectos', 1),
        );
});

it('no se lo muestra a un lector', function () {
    // El tablero de salud es del admin: un invitado a ver documentación no tiene
    // por qué saber si un sitio se cayó.
    $lector = User::factory()->create(['rol' => RolUsuario::Lector]);

    $this->actingAs($lector)->get('/dashboard')->assertForbidden();
});

it('manda las fechas en ISO 8601, que es lo que espera dayjs', function () {
    $proyecto = Proyecto::factory()->create();
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('proyectos.0.chequeos.0.cuando', fn (string $cuando) => str_contains($cuando, 'T')),
        );
});
