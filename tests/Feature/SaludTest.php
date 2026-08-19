<?php

use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;

it('contesta sin sesión, porque la mira un monitor externo', function () {
    // Centinela no puede avisar que se cayó estando caído: para eso está esto.
    $this->get('/salud')
        ->assertOk()
        ->assertJson(['ok' => true, 'proyectos' => 0, 'incidentes_abiertos' => 0]);
});

it('informa cuántos minutos pasaron desde el último chequeo', function () {
    /*
     * Es el dato que de verdad importa: si el scheduler se murió, la app sigue
     * contestando 200 y un monitor que solo mire el código no lo vería.
     */
    $proyecto = Proyecto::factory()->create();
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subMinutes(7)]);

    $this->get('/salud')->assertJson(['minutos_desde_el_ultimo_chequeo' => 7]);
});

it('cuenta los incidentes abiertos', function () {
    $proyecto = Proyecto::factory()->create();
    Incidente::factory()->for($proyecto)->create();
    Incidente::factory()->for($proyecto)->cerrado()->create();

    $this->get('/salud')->assertJson(['incidentes_abiertos' => 1]);
});

it('no filtra nada sensible', function () {
    $proyecto = Proyecto::factory()->create([
        'nombre' => 'Huella',
        'url' => 'https://huella.pablomandile.com.ar',
        'notas' => 'una nota privada',
    ]);
    Chequeo::factory()->for($proyecto)->falla('el detalle del error')->create();

    $respuesta = $this->get('/salud');

    // Es público: ni nombres, ni URLs, ni mensajes de error.
    $respuesta->assertDontSee('Huella')
        ->assertDontSee('pablomandile')
        ->assertDontSee('el detalle del error')
        ->assertDontSee('una nota privada');
});
