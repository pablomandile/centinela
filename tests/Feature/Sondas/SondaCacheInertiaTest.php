<?php

use App\Enums\EstadoChequeo;
use App\Sondas\SondaCacheInertia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * La sonda del skill `inertia-json-crudo`.
 *
 * El caso que más importa es el segundo: `no-cache` en el JSON parece razonable
 * —es lo que manda Laravel por default— y es exactamente el bug. Si algún día
 * alguien "simplifica" la sonda para aceptar `no-cache`, este test lo frena.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * Responde como una app de Inertia, con el Cache-Control que se le pida.
 *
 * @param  array<string, string>  $cabecerasXhr
 */
function inertiaResponde(string $cacheXhr, string $cacheHtml = 'no-cache, private', bool $mandaVersionEnCabecera = true): void
{
    Http::fake(function (Request $pedido) use ($cacheXhr, $cacheHtml, $mandaVersionEnCabecera) {
        $version = 'abc123';
        $esXhr = $pedido->hasHeader('X-Inertia');
        $conVersion = $pedido->hasHeader('X-Inertia-Version');

        if ($esXhr && ! $conVersion) {
            // El 409 de Inertia cuando la versión no coincide.
            return Http::response('', 409, array_filter([
                'X-Inertia-Location' => 'https://ejemplo.test/login',
                'X-Inertia-Version' => $mandaVersionEnCabecera ? $version : null,
            ]));
        }

        if ($esXhr) {
            return Http::response(
                ['component' => 'Home', 'version' => $version],
                200,
                ['Content-Type' => 'application/json', 'Cache-Control' => $cacheXhr, 'X-Inertia' => 'true'],
            );
        }

        return Http::response(
            '<div id="app" data-page="{&quot;component&quot;:&quot;Home&quot;,&quot;version&quot;:&quot;'.$version.'&quot;}"></div>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => $cacheHtml],
        );
    });
}

it('da ok cuando el JSON no se puede guardar y el HTML sí', function () {
    inertiaResponde(cacheXhr: 'no-store, private');

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->detalle['version'])->toBe('abc123');
});

it('falla cuando el JSON sale con no-cache en vez de no-store', function () {
    /*
     * `no-cache` **permite guardar** y solo obliga a revalidar, y una navegación de
     * historial —restaurar una pestaña descartada— saltea la revalidación. Es el
     * bug entero.
     */
    inertiaResponde(cacheXhr: 'no-cache, private');

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('JSON crudo');
});

it('falla cuando el JSON sale sin Cache-Control', function () {
    inertiaResponde(cacheXhr: '');

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('sin Cache-Control');
});

it('advierte cuando el HTML también sale con no-store', function () {
    // Eso desactiva el back/forward cache de Chrome y no da ningún síntoma que lo
    // delate: cada "atrás" pasa a ser una ida completa a la red.
    inertiaResponde(cacheXhr: 'no-store, private', cacheHtml: 'no-store, private');

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('back/forward cache');
});

it('saca la versión del data-page cuando el server no la manda en la cabecera', function () {
    /*
     * inertia-laravel 2 no manda `X-Inertia-Version` en la respuesta —ese header lo
     * agregó la 3— y la mitad de los proyectos de la cuenta sigue en 2. Sin este
     * respaldo, la sonda contestaba "no parece Inertia" en sitios que sí lo son.
     */
    inertiaResponde(cacheXhr: 'no-store, private', mandaVersionEnCabecera: false);

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->detalle['version'])->toBe('abc123');
});

it('advierte cuando el sitio no contesta como Inertia', function () {
    Http::fake(['ejemplo.test*' => Http::response('<html>un sitio cualquiera</html>')]);

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('detectar-perfil');
});

it('falla cuando el sitio no contesta', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(app(SondaCacheInertia::class)->ejecutar(proyecto())->estado)
        ->toBe(EstadoChequeo::Falla);
});

it('registra si el CDN se comió el Vary con brotli', function () {
    inertiaResponde(cacheXhr: 'no-store, private');

    $resultado = app(SondaCacheInertia::class)->ejecutar(proyecto());

    // El dato no cambia el veredicto, pero es el que explica por qué el `no-store`
    // hace falta: sin `Vary`, las dos respuestas comparten clave de caché.
    expect($resultado->detalle)->toHaveKey('vary_con_brotli');
});

it('solo aplica a los proyectos que usan Inertia', function () {
    expect(app(SondaCacheInertia::class)->aplicaA(proyecto(['usa_inertia' => true])))->toBeTrue()
        ->and(app(SondaCacheInertia::class)->aplicaA(proyecto(['usa_inertia' => false])))->toBeFalse();
});
