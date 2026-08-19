<?php

use App\Models\Proyecto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * Un sitio de Inertia con PWA y build.
 */
function sitioCompleto(): void
{
    Http::fake(function (Request $pedido) {
        if (str_contains($pedido->url(), 'manifest')) {
            return Http::response(json_encode(['name' => 'App', 'icons' => []]), 200);
        }

        if ($pedido->hasHeader('X-Inertia')) {
            return Http::response('', 409, ['X-Inertia-Location' => 'https://ejemplo.test/login']);
        }

        return Http::response('<html><script src="/build/assets/app-abc.js"></script></html>');
    });
}

it('detecta Inertia, PWA y bundle, sin guardar nada por defecto', function () {
    sitioCompleto();
    $proyecto = proyecto(['usa_inertia' => false, 'es_pwa' => false, 'tiene_bundle' => false]);

    $this->artisan('centinela:detectar-perfil')
        ->expectsOutputToContain('Solo informe')
        ->assertSuccessful();

    // Nada cambió: guardar en silencio las banderas de dieciséis proyectos
    // cambiaría qué se audita en cada uno.
    $proyecto->refresh();
    expect($proyecto->usa_inertia)->toBeFalse()
        ->and($proyecto->es_pwa)->toBeFalse();
});

it('guarda las banderas con --aplicar', function () {
    sitioCompleto();
    $proyecto = proyecto(['usa_inertia' => false, 'es_pwa' => false, 'tiene_bundle' => false]);

    $this->artisan('centinela:detectar-perfil --aplicar')
        ->expectsOutputToContain('Actualizados 1')
        ->assertSuccessful();

    $proyecto->refresh();
    expect($proyecto->usa_inertia)->toBeTrue()
        ->and($proyecto->es_pwa)->toBeTrue()
        ->and($proyecto->tiene_bundle)->toBeTrue();
});

it('reconoce Inertia 2, que no manda la versión en la cabecera', function () {
    // La señal es el X-Inertia-Location del 409, no el X-Inertia-Version: ese
    // header en la respuesta lo agregó inertia-laravel 3.
    Http::fake(function (Request $pedido) {
        if (str_contains($pedido->url(), 'manifest')) {
            return Http::response('', 404);
        }

        if ($pedido->hasHeader('X-Inertia')) {
            return Http::response('', 409, ['X-Inertia-Location' => 'https://ejemplo.test/login']);
        }

        return Http::response('<html><div data-page="{&quot;component&quot;:&quot;Home&quot;}"></div></html>');
    });

    $proyecto = proyecto(['usa_inertia' => false, 'es_pwa' => true, 'tiene_bundle' => true]);

    $this->artisan('centinela:detectar-perfil --aplicar')->assertSuccessful();

    $proyecto->refresh();
    expect($proyecto->usa_inertia)->toBeTrue()
        ->and($proyecto->es_pwa)->toBeFalse()
        ->and($proyecto->tiene_bundle)->toBeFalse();
});

it('no da por PWA un manifest que en realidad es el HTML del sitio', function () {
    /*
     * Pasó con localia: el hosting contesta la home con 200 para cualquier ruta.
     */
    Http::fake(['*' => Http::response('<!doctype html><html>la home</html>')]);
    $proyecto = proyecto(['es_pwa' => true]);

    $this->artisan('centinela:detectar-perfil --aplicar')->assertSuccessful();

    expect($proyecto->refresh()->es_pwa)->toBeFalse();
});

it('informa cuando el sitio no contesta, sin tocar sus banderas', function () {
    Http::fake(fn () => throw new ConnectionException('no resuelve'));
    $proyecto = proyecto(['usa_inertia' => true]);

    $this->artisan('centinela:detectar-perfil --aplicar')
        ->expectsOutputToContain('No hubo cambios')
        ->assertSuccessful();

    expect($proyecto->refresh()->usa_inertia)->toBeTrue();
});

it('puede mirar un solo proyecto', function () {
    sitioCompleto();
    $uno = proyecto(['usa_inertia' => false]);
    $otro = Proyecto::factory()->create(['slug' => 'otro', 'usa_inertia' => false]);

    $this->artisan('centinela:detectar-perfil '.$uno->slug.' --aplicar')->assertSuccessful();

    expect($uno->refresh()->usa_inertia)->toBeTrue()
        ->and($otro->refresh()->usa_inertia)->toBeFalse();
});
