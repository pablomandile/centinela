<?php

use App\Enums\EstadoChequeo;
use App\Sondas\SondaBundle;
use Illuminate\Support\Facades\Http;

/*
 * El chequeo del deploy a medio camino: la página carga, el server contesta 200 y
 * el JS que pide ya no está en disco. La pantalla queda en blanco y la sonda de
 * disponibilidad no lo ve.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

function paginaCon(string $asset, int $codigoDelAsset = 200): void
{
    Http::fake([
        'ejemplo.test' => Http::response('<html><script src="'.$asset.'"></script></html>'),
        'ejemplo.test/build/*' => Http::response('console.log(1)', $codigoDelAsset),
    ]);
}

it('da ok cuando el bundle que pide la página existe', function () {
    paginaCon('/build/assets/app-A1b2C3.js');

    $resultado = app(SondaBundle::class)->ejecutar(proyecto(['tiene_bundle' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->detalle['asset'])->toBe('app-A1b2C3.js');
});

it('falla cuando el bundle que pide la página no está', function () {
    paginaCon('/build/assets/app-A1b2C3.js', codigoDelAsset: 404);

    $resultado = app(SondaBundle::class)->ejecutar(proyecto(['tiene_bundle' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('Deploy a medio camino');
});

it('prefiere el app-* cuando la página referencia varios', function () {
    // El de la aplicación es el que importa; los chunks de página se piden después.
    Http::fake([
        'ejemplo.test' => Http::response(
            '<html><script src="/build/assets/Dashboard-XYZ.js"></script><script src="/build/assets/app-A1b2C3.js"></script></html>',
        ),
        'ejemplo.test/build/*' => Http::response('js'),
    ]);

    $resultado = app(SondaBundle::class)->ejecutar(proyecto(['tiene_bundle' => true]));

    expect($resultado->detalle['asset'])->toBe('app-A1b2C3.js');
});

it('advierte cuando la página no referencia ningún build', function () {
    // Lo más probable es que la bandera del proyecto esté mal, así que no es falla.
    Http::fake(['ejemplo.test*' => Http::response('<html>sin assets</html>')]);

    $resultado = app(SondaBundle::class)->ejecutar(proyecto(['tiene_bundle' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('perfil');
});

it('solo aplica a los proyectos que compilan assets', function () {
    expect(app(SondaBundle::class)->aplicaA(proyecto(['tiene_bundle' => true])))->toBeTrue()
        ->and(app(SondaBundle::class)->aplicaA(proyecto(['tiene_bundle' => false])))->toBeFalse();
});
