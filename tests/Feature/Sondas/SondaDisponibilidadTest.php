<?php

use App\Enums\EstadoChequeo;
use App\Models\Proyecto;
use App\Sondas\SondaDisponibilidad;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Ninguna sonda tiene permitido salir a internet en los tests: si una URL no
    // está fakeada, el test falla en vez de pegarle a un sitio real.
    Http::preventStrayRequests();
});

it('da ok cuando el sitio contesta 200', function () {
    Http::fake(['ejemplo.test*' => Http::response('<html>hola</html>')]);

    $resultado = app(SondaDisponibilidad::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->codigoHttp)->toBe(200)
        ->and($resultado->mensaje)->toBe('Contesta 200.');
});

it('no toma como caída una raíz que redirige al login', function () {
    /*
     * La mitad de los proyectos contesta 302 a /login en la raíz. Tratar eso como
     * caída es el falso negativo más fácil de cometer en todo Centinela, y el
     * motivo por el que el skill de deploy insiste con el `curl -L`.
     */
    Http::fake([
        'https://ejemplo.test' => Http::response('', 302, ['Location' => 'https://ejemplo.test/login']),
        'https://ejemplo.test/login' => Http::response('<html>login</html>'),
    ]);

    $resultado = app(SondaDisponibilidad::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->codigoHttp)->toBe(200);
});

it('falla cuando el sitio contesta 500', function () {
    Http::fake(['ejemplo.test*' => Http::response('Server Error', 500)]);

    $resultado = app(SondaDisponibilidad::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toBe('Contesta 500.');
});

it('falla cuando el sitio no contesta', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

    $resultado = app(SondaDisponibilidad::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('No contesta')
        ->and($resultado->codigoHttp)->toBeNull();
});

it('falla si contesta 200 pero sin la palabra clave', function () {
    // Un 200 no alcanza: una pantalla de error de PHP o un "sitio en
    // mantenimiento" del hosting también contestan 200.
    Http::fake(['ejemplo.test*' => Http::response('<html>Fatal error</html>')]);

    $resultado = app(SondaDisponibilidad::class)->ejecutar(
        proyecto(['palabra_clave' => 'Iniciar sesión']),
    );

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('Iniciar sesión');
});

it('da ok si la palabra clave está', function () {
    Http::fake(['ejemplo.test*' => Http::response('<html>Iniciar sesión</html>')]);

    $resultado = app(SondaDisponibilidad::class)->ejecutar(
        proyecto(['palabra_clave' => 'Iniciar sesión']),
    );

    expect($resultado->estado)->toBe(EstadoChequeo::Ok);
});

it('advierte cuando tarda más de lo aceptable', function () {
    // El umbral en -1 hace que cualquier latencia lo pase: medir un tiempo real
    // dentro de un test sería inestable.
    config()->set('centinela.umbrales.latencia_advertencia', -1);
    Http::fake(['ejemplo.test*' => Http::response('ok')]);

    $resultado = app(SondaDisponibilidad::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($resultado->mensaje)->toContain('tarda');
});

it('se identifica con su propio User-Agent', function () {
    // Para que en los logs del server quede claro quién pegó.
    Http::fake(['ejemplo.test*' => Http::response('ok')]);

    app(SondaDisponibilidad::class)->ejecutar(proyecto());

    Http::assertSent(fn (Request $pedido) => str_contains($pedido->header('User-Agent')[0], 'Centinela'));
});

it('aplica a todos los proyectos', function () {
    expect(app(SondaDisponibilidad::class)->aplicaA(proyecto()))->toBeTrue()
        ->and(app(SondaDisponibilidad::class)->aplicaA(Proyecto::factory()->estatico()->create()))->toBeTrue();
});
