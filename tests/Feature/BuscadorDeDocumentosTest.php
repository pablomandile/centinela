<?php

use App\Models\Documento;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->usuario = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create(['nombre' => 'Huella', 'slug' => 'huella']);
});

function documentoCon(string $titulo, string $texto): Documento
{
    return Documento::factory()->for(test()->proyecto)->create([
        'titulo' => $titulo,
        'slug' => Str::slug($titulo),
        'texto' => $texto,
        'texto_normalizado' => Documento::textoParaBuscar($titulo, $texto),
    ]);
}

it('lista todo sin término de búsqueda', function () {
    documentoCon('Arquitectura', 'texto uno');
    documentoCon('Reglas de negocio', 'texto dos');

    $this->actingAs($this->usuario)
        ->get('/documentos')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('documentos/Index')
            ->has('documentos', 2)
            ->where('q', ''),
        );
});

it('busca en el título y en el contenido', function () {
    documentoCon('Arquitectura', 'habla de las migraciones');
    documentoCon('Reglas de negocio', 'habla de los cobros');

    $this->actingAs($this->usuario)
        ->get('/documentos?q=cobros')
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('documentos', 1)
            ->where('documentos.0.titulo', 'Reglas de negocio'),
        );

    $this->actingAs($this->usuario)
        ->get('/documentos?q=Arquitectura')
        ->assertInertia(fn (Assert $pagina) => $pagina->has('documentos', 1));
});

it('encuentra sin escribir los acentos', function () {
    /*
     * Es como uno escribe cuando busca rápido. Y funciona igual en los dos motores:
     * MySQL ignoraría los acentos por collation, sqlite no, así que la comparación
     * va contra `texto_normalizado` en vez de depender de eso.
     */
    documentoCon('Notas', 'la documentación del proyecto');

    $this->actingAs($this->usuario)
        ->get('/documentos?q=documentacion')
        ->assertInertia(fn (Assert $pagina) => $pagina->has('documentos', 1));
});

it('devuelve un fragmento con el contexto de lo encontrado', function () {
    // Un resultado sin contexto obliga a abrir cada documento para ver si era.
    documentoCon('Notas', str_repeat('bla ', 40).'el cron de hPanel no soporta cd'.str_repeat(' bla', 40));

    $this->actingAs($this->usuario)
        ->get('/documentos?q=hPanel')
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('documentos.0.fragmento', fn (?string $fragmento) => $fragmento !== null
                && str_contains($fragmento, 'hPanel')
                && str_starts_with($fragmento, '…')),
        );
});

it('no devuelve nada cuando no hay coincidencias', function () {
    documentoCon('Arquitectura', 'texto');

    $this->actingAs($this->usuario)
        ->get('/documentos?q=inexistente')
        ->assertInertia(fn (Assert $pagina) => $pagina->has('documentos', 0));
});
