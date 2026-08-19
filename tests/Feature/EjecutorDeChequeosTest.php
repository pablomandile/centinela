<?php

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use App\Mail\AvisoDeIncidente;
use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use App\Services\EjecutorDeChequeos;
use App\Sondas\Resultado;
use App\Sondas\SondaDisponibilidad;
use App\Sondas\Soporte\Certificado;
use App\Sondas\Soporte\LectorDeCertificado;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
 * El ejecutor es lo que convierte chequeos en avisos, y ahí están las tres reglas
 * que hacen que los mails sirvan: dos fallos seguidos para abrir, uno solo abierto
 * por par (proyecto, tipo), y las advertencias no despiertan a nadie.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    config()->set('centinela.avisos_a', 'avisos@ejemplo.test');

    /*
     * El código lo decide una propiedad del test y no un `Http::fake()` por caso:
     * `fake()` **acumula** stubs en vez de reemplazarlos, así que un segundo
     * `fake()` sobre la misma URL nunca se aplica y el sitio "no se recupera"
     * nunca. Con una sola respuesta que lee el estado actual, la misma URL puede
     * cambiar de respuesta a lo largo del test, que es lo que pasa en la realidad.
     */
    $this->codigo = 200;

    Http::fake(function (Request $pedido) {
        // El puerto 80 redirige, como en todos los proyectos reales. Si contestara
        // el sitio en plano, la sonda de redirección fallaría con razón.
        if (str_starts_with($pedido->url(), 'http://')) {
            return Http::response('', 301, ['Location' => 'https://ejemplo.test/']);
        }

        return Http::response(test()->codigo === 200 ? 'ok' : 'error', test()->codigo);
    });
});

function elSitioContesta(int $codigo): void
{
    test()->codigo = $codigo;
}

function chequearDisponibilidad(Proyecto $proyecto): Chequeo
{
    return app(EjecutorDeChequeos::class)
        ->correr($proyecto, TipoChequeo::Disponibilidad, forzar: true)
        ->first();
}

it('guarda el resultado de cada sonda', function () {
    elSitioContesta(200);
    $proyecto = proyecto();

    $chequeo = chequearDisponibilidad($proyecto);

    expect($chequeo->estado)->toBe(EstadoChequeo::Ok)
        ->and($chequeo->codigo_http)->toBe(200)
        ->and($chequeo->latencia_ms)->not->toBeNull()
        ->and($chequeo->detalle)->toHaveKey('redirects')
        ->and($proyecto->chequeos()->count())->toBe(1);
});

it('no abre incidente ni avisa con un solo fallo', function () {
    // Un hipo de red no puede mandar un mail a las tres de la mañana.
    elSitioContesta(500);
    $proyecto = proyecto();

    chequearDisponibilidad($proyecto);

    expect($proyecto->incidentes()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('abre incidente y avisa al segundo fallo seguido', function () {
    elSitioContesta(500);
    $proyecto = proyecto();

    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);

    $incidente = $proyecto->incidentes()->sole();

    expect($incidente->tipo)->toBe(TipoChequeo::Disponibilidad)
        ->and($incidente->estaAbierto())->toBeTrue()
        ->and($incidente->ultimo_mensaje)->toContain('500')
        ->and($incidente->avisado_at)->not->toBeNull();

    Mail::assertSent(AvisoDeIncidente::class, fn (AvisoDeIncidente $mail) => $mail->seRecupero === false);
});

it('no abre un segundo incidente ni repite el mail mientras sigue caído', function () {
    // Sin esto serían 96 mails por día por proyecto caído.
    elSitioContesta(500);
    $proyecto = proyecto();

    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);

    expect($proyecto->incidentes()->count())->toBe(1);
    Mail::assertSentCount(1);
});

it('cierra el incidente y avisa cuando se recupera', function () {
    elSitioContesta(500);
    $proyecto = proyecto();
    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);

    elSitioContesta(200);
    chequearDisponibilidad($proyecto);

    $incidente = $proyecto->incidentes()->sole();

    expect($incidente->estaAbierto())->toBeFalse()
        ->and($incidente->avisado_cierre_at)->not->toBeNull();

    Mail::assertSent(AvisoDeIncidente::class, fn (AvisoDeIncidente $mail) => $mail->seRecupero === true);
    Mail::assertSentCount(2);
});

it('no avisa la recuperación si nunca avisó la caída', function () {
    // Un "ya está" sin el mail de caída no se entiende.
    $proyecto = proyecto();
    Incidente::factory()->for($proyecto)->create(['avisado_at' => null]);

    elSitioContesta(200);
    chequearDisponibilidad($proyecto);

    expect($proyecto->incidentes()->sole()->estaAbierto())->toBeFalse();
    Mail::assertNothingSent();
});

it('una advertencia no abre incidente', function () {
    // Un sitio lento o un certificado a 15 días se ven en el tablero y no
    // despiertan a nadie.
    config()->set('centinela.umbrales.latencia_advertencia', -1);
    elSitioContesta(200);
    $proyecto = proyecto();

    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);

    expect($proyecto->chequeos()->first()->estado)->toBe(EstadoChequeo::Advertencia)
        ->and($proyecto->incidentes()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('una advertencia cierra un incidente abierto', function () {
    // La falla se resolvió, aunque quede algo por mirar.
    $proyecto = proyecto();
    Incidente::factory()->for($proyecto)->avisado()->create();
    config()->set('centinela.umbrales.latencia_advertencia', -1);
    elSitioContesta(200);

    chequearDisponibilidad($proyecto);

    expect($proyecto->incidentes()->sole()->estaAbierto())->toBeFalse();
});

it('abre el incidente igual cuando no hay a quién avisarle', function () {
    config()->set('centinela.avisos_a', null);
    elSitioContesta(500);
    $proyecto = proyecto();

    chequearDisponibilidad($proyecto);
    chequearDisponibilidad($proyecto);

    $incidente = $proyecto->incidentes()->sole();

    expect($incidente->estaAbierto())->toBeTrue()
        // Sin avisar, así que si mañana se configura el destino, el próximo
        // chequeo lo vuelve a intentar.
        ->and($incidente->avisado_at)->toBeNull();
    Mail::assertNothingSent();
});

it('registra como falla una sonda que explota, sin cortar la corrida', function () {
    // Extiende la sonda real en vez de implementar la interfaz: el registro pide
    // los tipos concretos en el constructor, y eso es a propósito —así el
    // container verifica que estén todas—.
    $rota = new class extends SondaDisponibilidad
    {
        public function ejecutar(Proyecto $proyecto): Resultado
        {
            throw new RuntimeException('se rompió algo raro');
        }
    };

    app()->instance(SondaDisponibilidad::class, $rota);

    $chequeo = chequearDisponibilidad(proyecto());

    // Que una sonda falle no puede tumbar la corrida de los otros once proyectos.
    expect($chequeo->estado)->toBe(EstadoChequeo::Falla)
        ->and($chequeo->mensaje)->toContain('se rompió algo raro');
});

it('solo corre las sondas que aplican al proyecto', function () {
    elSitioContesta(200);
    // Un estático: ni Inertia, ni PWA, ni bundle.
    $proyecto = Proyecto::factory()->estatico()->create(['url' => 'https://ejemplo.test']);

    // El certificado se saca del medio para no abrir un socket real en el test.
    app()->instance(LectorDeCertificado::class, new class extends LectorDeCertificado
    {
        public function leer(string $host, int $puerto = 443): ?Certificado
        {
            return new Certificado(now()->addDays(60));
        }
    });

    app(EjecutorDeChequeos::class)->correr($proyecto, forzar: true);

    expect($proyecto->chequeos()->pluck('tipo')->map->value->sort()->values()->all())
        ->toBe(['certificado', 'disponibilidad', 'redireccion_https'])
        // Y que ninguno haya quedado en falla: si una sonda explota, el ejecutor la
        // registra como falla y este test pasaría igual mirando solo los tipos.
        ->and($proyecto->chequeos()->pluck('estado')->map->value->unique()->all())
        ->toBe(['ok']);
});

it('respeta el intervalo cuando no se fuerza', function () {
    elSitioContesta(200);
    $proyecto = proyecto(['intervalo_minutos' => 15]);

    app(EjecutorDeChequeos::class)->correr($proyecto, TipoChequeo::Disponibilidad);
    app(EjecutorDeChequeos::class)->correr($proyecto, TipoChequeo::Disponibilidad);

    // El segundo no corre: el cron pasa cada cinco minutos y el intervalo es 15.
    expect($proyecto->chequeos()->count())->toBe(1);
});
