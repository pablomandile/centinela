<?php

use App\Enums\FormatoDocumento;
use App\Enums\RolUsuario;
use App\Models\Documento;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Disco falso: los archivos de los tests no ensucian storage/app/private.
    Storage::fake('local');

    $this->admin = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create(['slug' => 'huella', 'nombre' => 'Huella']);
});

function archivoMd(string $nombre = 'README.md', ?string $contenido = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $nombre,
        $contenido ?? "# Historial de mascotas\n\nUn párrafo sobre el diario.",
    );
}

it('sube varios documentos de una', function () {
    // La documentación de un proyecto son cinco o seis archivos: subirlos de a uno
    // con el celular en la mano es lo que garantiza que no se suban nunca.
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [archivoMd('README.md'), archivoMd('ARCHITECTURE.md')],
        ])
        ->assertRedirect();

    expect($this->proyecto->documentos()->count())->toBe(2);
});

it('toma el título del primer encabezado del markdown', function () {
    // "ARCHITECTURE.md" describe mucho peor el contenido que su propio título.
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd('ARCHITECTURE.md')]]);

    $documento = Documento::sole();

    expect($documento->titulo)->toBe('Historial de mascotas')
        ->and($documento->slug)->toBe('historial-de-mascotas')
        ->and($documento->nombre_original)->toBe('ARCHITECTURE.md')
        ->and($documento->formato)->toBe(FormatoDocumento::Md);
});

it('cae al nombre del archivo si el markdown no tiene encabezado', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [archivoMd('DEUDA_TECNICA.md', 'Sin encabezado, solo texto.')],
        ]);

    expect(Documento::sole()->titulo)->toBe('DEUDA_TECNICA');
});

it('guarda el archivo en el disco privado', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]]);

    Storage::disk('local')->assertExists(Documento::sole()->ruta);
});

it('resubir el mismo archivo no crea otra fila', function () {
    // Sin esto, después de tres semanas de "por si acaso lo subo de nuevo" la lista
    // tiene cinco copias de README.md y ninguna dice cuál es la buena.
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]]);
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]]);

    expect(Documento::count())->toBe(1);
});

it('resubir el archivo cambiado actualiza la fila y borra el viejo', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]]);

    $rutaVieja = Documento::sole()->ruta;

    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [archivoMd('README.md', "# Historial de mascotas\n\nAhora dice otra cosa.")],
        ]);

    $documento = Documento::sole();

    expect(Documento::count())->toBe(1)
        ->and($documento->texto)->toContain('otra cosa')
        ->and($documento->ruta)->not->toBe($rutaVieja);

    // El archivo viejo no queda ocupando la cuota del hosting.
    Storage::disk('local')->assertMissing($rutaVieja);
});

it('no acepta cualquier archivo', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [UploadedFile::fake()->create('backup.zip', 10)],
        ])
        ->assertSessionHasErrors('archivos.0');

    expect(Documento::count())->toBe(0);
});

it('no acepta archivos de más de 10 MB', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [UploadedFile::fake()->create('gigante.pdf', 11 * 1024)],
        ])
        ->assertSessionHasErrors('archivos.0');
});

it('de un PDF no extrae texto', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [UploadedFile::fake()->create('especificacion.pdf', 200)],
        ]);

    $documento = Documento::sole();

    expect($documento->formato)->toBe(FormatoDocumento::Pdf)
        ->and($documento->texto)->toBeNull();
});

it('muestra el markdown renderizado con su índice', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [archivoMd('README.md', "# Título\n\n## Primera\n\ntexto\n\n## Segunda\n\nmás")],
        ]);

    $this->actingAs($this->admin)
        ->get('/proyectos/huella/documentos/titulo')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('documentos/Show')
            ->has('indice', 2)
            ->where('indice.0.ancla', 'primera')
            ->where('html', fn (string $html) => str_contains($html, '<h2 id="primera">')),
        );
});

it('sirve el PDF para verlo en el visor del navegador', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', [
            'archivos' => [UploadedFile::fake()->create('ficha.pdf', 50)],
        ]);

    $respuesta = $this->actingAs($this->admin)->get('/proyectos/huella/documentos/ficha');

    $respuesta->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($respuesta->headers->get('Content-Disposition'))->toContain('inline');
});

it('el original se baja como adjunto', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]]);

    $respuesta = $this->actingAs($this->admin)
        ->get('/proyectos/huella/documentos/historial-de-mascotas/descargar');

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Disposition'))->toContain('attachment');
});

it('borra la fila y el archivo', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]]);

    $ruta = Documento::sole()->ruta;

    $this->actingAs($this->admin)
        ->delete('/proyectos/huella/documentos/historial-de-mascotas')
        ->assertRedirect();

    expect(Documento::count())->toBe(0);
    // El archivo se borra de verdad aunque la fila tenga soft delete: un documento
    // "borrado" que sigue ocupando disco es lo que no se quiere.
    Storage::disk('local')->assertMissing($ruta);
});

it('no sirve el documento de otro proyecto', function () {
    /*
     * Los slugs son únicos **por proyecto**, así que dos proyectos pueden tener los
     * dos un "readme". Sin el scopeBindings de las rutas, esta URL mostraría el
     * documento del otro proyecto.
     */
    $otro = Proyecto::factory()->create(['slug' => 'movieboxd']);
    Documento::factory()->for($otro)->create(['slug' => 'readme']);

    $this->actingAs($this->admin)
        ->get('/proyectos/huella/documentos/readme')
        ->assertNotFound();
});

it('un lector lee pero no sube ni borra', function () {
    $lector = User::factory()->create(['rol' => RolUsuario::Lector]);
    $documento = Documento::factory()->for($this->proyecto)->create(['slug' => 'readme']);
    Storage::disk('local')->put($documento->ruta, '# hola');

    $this->actingAs($lector)->get('/proyectos/huella/documentos/readme')->assertOk();
    $this->actingAs($lector)->get('/documentos')->assertOk();

    $this->actingAs($lector)
        ->post('/proyectos/huella/documentos', ['archivos' => [archivoMd()]])
        ->assertForbidden();

    $this->actingAs($lector)
        ->delete('/proyectos/huella/documentos/readme')
        ->assertForbidden();

    expect(Documento::count())->toBe(1);
});

it('sin sesión no se ve ningún documento', function () {
    Documento::factory()->for($this->proyecto)->create(['slug' => 'readme']);

    $this->get('/documentos')->assertRedirect(route('login'));
    $this->get('/proyectos/huella/documentos/readme')->assertRedirect(route('login'));
});
