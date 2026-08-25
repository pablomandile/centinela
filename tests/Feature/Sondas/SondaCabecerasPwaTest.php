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

/**
 * El mismo /sw.js contestando dos cosas distintas: lo que entrega el borde y lo
 * que contesta el origen. Se separan por el query string, que es justamente el
 * único modo de saltear al nodo (un `Cache-Control: no-cache` en el pedido no
 * alcanza: medido contra Hostinger, el nodo devuelve su copia igual).
 */
function swDelBordeYDelOrigen(string $cacheDelBorde, string $cacheDelOrigen, string $age = '514886'): void
{
    Http::fake([
        'ejemplo.test/manifest.webmanifest' => Http::response(
            json_encode(['name' => 'App', 'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512'],
            ]]),
            200,
            ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache'],
        ),
        'ejemplo.test/sw.js?*' => Http::response('fetch', 200, ['Cache-Control' => $cacheDelOrigen]),
        'ejemplo.test/sw.js' => Http::response('fetch', 200, [
            'Cache-Control' => $cacheDelBorde,
            'x-hcdn-cache-status' => 'HIT',
            'Age' => $age,
        ]),
        'ejemplo.test/icons/*' => Http::response('png'),
    ]);
}

it('no manda a arreglar el .htaccess cuando el origen ya está bien y lo viejo es la copia del CDN', function () {
    /*
     * El caso de mantreando: el .htaccess ya estaba corregido y un nodo del CDN
     * seguía entregando la copia de seis días antes. Mirando solo la cabecera,
     * esto es idéntico a un origen mal configurado, y manda a tocar un archivo
     * que ya está bien.
     */
    swDelBordeYDelOrigen(
        cacheDelBorde: 'public, max-age=604800',
        cacheDelOrigen: 'no-cache, must-revalidate, max-age=0',
    );

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('ya sale bien del origen')
        // 604800 - 514886 = 89914 s, o sea 25 horas.
        ->and($resultado->mensaje)->toContain('vence en 25 horas')
        ->and($resultado->mensaje)->not->toContain('lo va a servir viejo por días');

    Http::assertSent(fn ($pedido) => str_contains($pedido->url(), 'centinela='));
});

it('sigue avisando cuando el CDN entrega una copia vieja y el origen también está mal', function () {
    swDelBordeYDelOrigen(
        cacheDelBorde: 'public, max-age=604800',
        cacheDelOrigen: 'public, max-age=604800',
    );

    $resultado = app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('lo va a servir viejo por días')
        ->and($resultado->mensaje)->not->toContain('ya sale bien del origen');
});

it('no le pregunta al origen si la respuesta no salió de ninguna caché', function () {
    /*
     * El pedido de más solo se justifica cuando hay una copia intermedia que
     * pueda estar mintiendo. Sin `Age` ni `x-hcdn-cache-status` la cabecera es la
     * del origen y no hay nada que confirmar: en hosting compartido las ráfagas
     * de pedidos se estrangulan, así que este no es un detalle de estilo.
     *
     * `Http::preventStrayRequests()` ya haría fallar el test si preguntara —no
     * hay stub con query—, pero se afirma explícito para que se lea la intención.
     */
    pwaResponde(cacheSw: 'public, max-age=604800');

    app(SondaCabecerasPwa::class)->ejecutar(proyecto(['es_pwa' => true]));

    Http::assertNotSent(fn ($pedido) => str_contains($pedido->url(), 'centinela='));
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
