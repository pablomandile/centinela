<?php

use App\Enums\RolUsuario;
use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function datosDeProyecto(array $cambios = []): array
{
    return [
        'nombre' => 'Docbrainer',
        'url' => 'https://docbrainer.pablomandile.com.ar',
        'usa_inertia' => true,
        'es_pwa' => false,
        'tiene_bundle' => true,
        'activo' => true,
        'intervalo_minutos' => 15,
        ...$cambios,
    ];
}

it('lista los proyectos para editar', function () {
    Proyecto::factory()->create(['nombre' => 'Huella']);

    $this->actingAs($this->admin)
        ->get('/proyectos')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('proyectos/Index')
            ->has('proyectos', 1)
            ->where('proyectos.0.tecnologia', 'Laravel + Inertia'),
        );
});

it('da de alta un proyecto y le deriva el slug', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos', datosDeProyecto())
        ->assertRedirect('/proyectos');

    expect(Proyecto::sole()->slug)->toBe('docbrainer');
});

it('exige una URL con esquema', function () {
    // Sin esquema, `parse_url` no encuentra el host y el chequeo del certificado no
    // sabe a qué conectarse.
    $this->actingAs($this->admin)
        ->post('/proyectos', datosDeProyecto(['url' => 'docbrainer.pablomandile.com.ar']))
        ->assertSessionHasErrors('url');

    expect(Proyecto::count())->toBe(0);
});

it('no acepta un intervalo menor al del scheduler', function () {
    $this->actingAs($this->admin)
        ->post('/proyectos', datosDeProyecto(['intervalo_minutos' => 2]))
        ->assertSessionHasErrors('intervalo_minutos');
});

it('no acepta dos proyectos con el mismo identificador', function () {
    Proyecto::factory()->create(['slug' => 'docbrainer']);

    $this->actingAs($this->admin)
        ->post('/proyectos', datosDeProyecto(['slug' => 'docbrainer']))
        ->assertSessionHasErrors('slug');
});

it('deja editar sin quejarse del propio slug', function () {
    $proyecto = Proyecto::factory()->create(['slug' => 'huella']);

    $this->actingAs($this->admin)
        ->put("/proyectos/{$proyecto->slug}", datosDeProyecto([
            'nombre' => 'Huella',
            'slug' => 'huella',
            'palabra_clave' => 'Iniciar sesión',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/proyectos');

    expect($proyecto->refresh()->palabra_clave)->toBe('Iniciar sesión');
});

it('muestra el detalle con sus chequeos e incidentes', function () {
    $proyecto = Proyecto::factory()->create(['slug' => 'huella']);
    Chequeo::factory()->for($proyecto)->create();
    Incidente::factory()->for($proyecto)->create();

    $this->actingAs($this->admin)
        ->get('/proyectos/huella')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('proyectos/Show')
            ->has('chequeos', 1)
            ->has('incidentes', 1)
            ->has('latencias', 1),
        );
});

it('la baja es lógica y no se lleva los chequeos', function () {
    $proyecto = Proyecto::factory()->create(['slug' => 'huella']);
    Chequeo::factory()->for($proyecto)->create();

    $this->actingAs($this->admin)
        ->delete('/proyectos/huella')
        ->assertRedirect('/proyectos');

    expect(Proyecto::count())->toBe(0)
        ->and(Proyecto::withTrashed()->count())->toBe(1)
        ->and(Chequeo::count())->toBe(1);
});

it('detecta las banderas desde el detalle y las guarda', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $pedido) {
        if (str_contains($pedido->url(), 'manifest')) {
            return Http::response(json_encode(['name' => 'App']), 200);
        }

        if ($pedido->hasHeader('X-Inertia')) {
            return Http::response('', 409, ['X-Inertia-Location' => 'https://ejemplo.test/login']);
        }

        return Http::response('<html><script src="/build/assets/app-x.js"></script></html>');
    });

    $proyecto = proyecto(['slug' => 'huella', 'usa_inertia' => false, 'es_pwa' => false, 'tiene_bundle' => false]);

    $this->actingAs($this->admin)
        ->post('/proyectos/huella/detectar')
        ->assertRedirect();

    $proyecto->refresh();
    expect($proyecto->usa_inertia)->toBeTrue()
        ->and($proyecto->es_pwa)->toBeTrue()
        ->and($proyecto->tiene_bundle)->toBeTrue();
});

it('avisa sin tocar nada si el sitio no contesta al detectar', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('no resuelve'));

    $proyecto = proyecto(['slug' => 'huella', 'usa_inertia' => true]);

    $this->actingAs($this->admin)->post('/proyectos/huella/detectar')->assertRedirect();

    expect($proyecto->refresh()->usa_inertia)->toBeTrue();
});

it('un lector no puede ver ni tocar proyectos', function () {
    $lector = User::factory()->create(['rol' => RolUsuario::Lector]);
    $proyecto = Proyecto::factory()->create(['slug' => 'huella']);

    $this->actingAs($lector)->get('/proyectos')->assertForbidden();
    $this->actingAs($lector)->get('/proyectos/huella')->assertForbidden();
    $this->actingAs($lector)->post('/proyectos', datosDeProyecto())->assertForbidden();
    $this->actingAs($lector)->delete('/proyectos/huella')->assertForbidden();

    expect($proyecto->fresh())->not->toBeNull();
});

it('sin sesión no se ve nada', function () {
    Proyecto::factory()->create(['slug' => 'huella']);

    $this->get('/proyectos')->assertRedirect(route('login'));
    $this->get('/proyectos/huella')->assertRedirect(route('login'));
});
