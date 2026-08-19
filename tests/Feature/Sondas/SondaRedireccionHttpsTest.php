<?php

use App\Enums\EstadoChequeo;
use App\Sondas\SondaRedireccionHttps;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('da ok cuando http redirige a https', function () {
    Http::fake(['http://ejemplo.test/' => Http::response('', 301, ['Location' => 'https://ejemplo.test/'])]);

    $resultado = app(SondaRedireccionHttps::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->mensaje)->toContain('301');
});

it('falla cuando sirve el sitio en http plano', function () {
    /*
     * En http plano no funciona WebCrypto —secretos depende de eso—, las PWA no se
     * pueden instalar y la sesión viaja sin cifrar. Y se rompe solo cuando alguien
     * toca un .htaccess.
     */
    Http::fake(['http://ejemplo.test/' => Http::response('<html>el sitio</html>', 200)]);

    $resultado = app(SondaRedireccionHttps::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('http plano');
});

it('advierte cuando redirige a otro http', function () {
    Http::fake(['http://ejemplo.test/' => Http::response('', 301, ['Location' => 'http://otro.test/'])]);

    expect(app(SondaRedireccionHttps::class)->ejecutar(proyecto())->estado)
        ->toBe(EstadoChequeo::Advertencia);
});

it('advierte, sin fallar, si el puerto 80 no contesta', function () {
    // Puede estar cerrado a propósito, y eso también resuelve el problema.
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $resultado = app(SondaRedireccionHttps::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('puerto 80');
});

it('no sigue el redirect, porque lo que mide es la respuesta cruda', function () {
    Http::fake(['http://ejemplo.test/' => Http::response('', 301, ['Location' => 'https://ejemplo.test/'])]);

    app(SondaRedireccionHttps::class)->ejecutar(proyecto());

    // Un solo pedido: si siguiera el redirect habría dos y el 301 se perdería.
    Http::assertSentCount(1);
});
