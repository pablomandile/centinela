<?php

use App\Models\Documento;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create(['slug' => 'huella', 'nombre' => 'Huella']);
});

it('convierte un markdown a PDF', function () {
    Documento::factory()->for($this->proyecto)->create([
        'slug' => 'arquitectura',
        'titulo' => 'Arquitectura',
        'texto' => "# Arquitectura\n\n## Capas\n\nUn párrafo con acentos: configuración.",
    ]);

    $respuesta = $this->actingAs($this->admin)
        ->get('/proyectos/huella/documentos/arquitectura/pdf');

    $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');

    // Un PDF vacío pesa unos pocos cientos de bytes: esto comprueba que además
    // tiene contenido, no solo que DomPDF no explotó.
    expect(strlen($respuesta->getContent()))->toBeGreaterThan(2000);
});

it('no ofrece convertir a PDF algo que ya es un PDF', function () {
    Documento::factory()->for($this->proyecto)->pdf()->create(['slug' => 'ficha']);

    $this->actingAs($this->admin)
        ->get('/proyectos/huella/documentos/ficha/pdf')
        ->assertNotFound();
});

it('arma el dossier con todos los markdown del proyecto', function () {
    Documento::factory()->for($this->proyecto)->create(['titulo' => 'Uno', 'slug' => 'uno']);
    Documento::factory()->for($this->proyecto)->create(['titulo' => 'Dos', 'slug' => 'dos']);
    Documento::factory()->for($this->proyecto)->pdf()->create(['titulo' => 'Tres', 'slug' => 'tres']);

    $respuesta = $this->actingAs($this->admin)->get('/proyectos/huella/dossier');

    $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(strlen($respuesta->getContent()))->toBeGreaterThan(3000);
});

it('no arma dossier si no hay ningún markdown', function () {
    // Los PDF ya subidos no se pueden unir —DomPDF no concatena—, así que un dossier
    // de un proyecto que solo tiene PDF sería una portada y nada más.
    Documento::factory()->for($this->proyecto)->pdf()->create(['slug' => 'ficha']);

    $this->actingAs($this->admin)->get('/proyectos/huella/dossier')->assertNotFound();
});

it('sin sesión no se generan PDFs', function () {
    Documento::factory()->for($this->proyecto)->create(['slug' => 'uno']);

    $this->get('/proyectos/huella/documentos/uno/pdf')->assertRedirect(route('login'));
    $this->get('/proyectos/huella/dossier')->assertRedirect(route('login'));
});
