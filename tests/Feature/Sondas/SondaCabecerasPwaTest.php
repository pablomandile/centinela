<?php

use App\Enums\EstadoChequeo;
use App\Sondas\SondaCabecerasPwa;
use Illuminate\Support\Facades\Http;

/*
 * La sonda del skill `adaptar-a-pwa`.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * @param  array<string, mixed>  $manifest
 * @param  array<string, string>  $cabecerasManifest
 */
function pwaResponde(
    array $manifest = ['name' => 'App', 'icons' => [
        ['src' => '/icons/icon-192.png', 'sizes' => '192x192'],
        ['src' => '/icons/icon-512.png', 'sizes' => '512x512'],
    ]],
    array $cabecerasManifest = ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0'],
    string $cacheSw = 'no-cache, must-revalidate, max-age=0',
    int $codigoSw = 200,
    int $codigoIconos = 200,
): void {
    Http::fake([
        // El cuerpo va como string a propósito: `Http::response()` con un array
        // sobrescribe el Content-Type a application/json y no se podría probar el
        // MIME del manifest.
        'ejemplo.test/manifest.webmanifest' => Http::response(json_encode($manifest), 200, $cabecerasManifest),
        'ejemplo.test/sw.js' => Http::response('self.addEventListener("fetch", () => {});', $codigoSw, ['Cache-Control' => $cacheSw]),
        'ejemplo.test/icons/*' => Http::response('png', $codigoIconos),
    ]);
}

it('da ok cuando el manifest, el sw y los íconos están y no quedan cacheados', function () {
    pwaResponde();

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Ok);
});

it('advierte cuando el service worker sale con la caché de siete días del CDN', function () {
    /*
     * Es la trampa del skill: el navegador se entera de que hay un service worker
     * nuevo pegándole a esa URL, así que si el borde devuelve el viejo, ninguna
     * actualización llega hasta que expire. Y como el sw controla las demás cachés,
     * congela todo lo demás.
     */
    pwaResponde(cacheSw: 'public, max-age=604800');

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('service worker');
});

it('advierte cuando el manifest se sirve con el MIME equivocado', function () {
    pwaResponde(cabecerasManifest: ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-cache']);

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('manifest+json');
});

it('advierte cuando faltan los tamaños que Chrome exige', function () {
    pwaResponde(manifest: ['name' => 'App', 'icons' => [['src' => '/icons/icon-64.png', 'sizes' => '64x64']]]);

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('192x192');
});

it('falla cuando un ícono declarado no existe', function () {
    // El síntoma real es "no aparece el botón de instalar", sin ningún error.
    pwaResponde(codigoIconos: 404);

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('icon-192');
});

it('falla cuando no hay service worker', function () {
    pwaResponde(codigoSw: 404);

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('/sw.js');
});

it('no toma por manifest un HTML que contesta 200', function () {
    /*
     * Pasó de verdad con localia: el hosting devuelve la home entera con 200 para
     * cualquier ruta, así que mirar solo el código la daba por instalable.
     */
    Http::fake([
        'ejemplo.test/manifest.webmanifest' => Http::response('<!doctype html><html>...</html>', 200, ['Content-Type' => 'text/html']),
        'ejemplo.test/manifest.json' => Http::response('<!doctype html><html>...</html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('manifest válido');
});

it('acepta el manifest en manifest.json si no está el webmanifest', function () {
    Http::fake([
        'ejemplo.test/manifest.webmanifest' => Http::response('', 404),
        'ejemplo.test/manifest.json' => Http::response(
            json_encode(['name' => 'App', 'icons' => [['src' => '/i-192.png', 'sizes' => '192x192'], ['src' => '/i-512.png', 'sizes' => '512x512']]]),
            200,
            ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache'],
        ),
        'ejemplo.test/sw.js' => Http::response('fetch', 200, ['Cache-Control' => 'no-cache']),
        'ejemplo.test/i-*' => Http::response('png'),
    ]);

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->detalle['manifest']['ruta'])->toBe('/manifest.json');
});

it('solo aplica a los proyectos instalables', function () {
    expect(app(SondaCabecerasPwa::class)->aplicaA(proyecto(['es_pwa' => true])))->toBeTrue()
        ->and(app(SondaCabecerasPwa::class)->aplicaA(proyecto(['es_pwa' => false])))->toBeFalse();
});
