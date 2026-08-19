<?php

use App\Enums\EstadoChequeo;
use App\Sondas\SondaCertificado;
use App\Sondas\Soporte\Certificado;
use App\Sondas\Soporte\LectorDeCertificado;

/*
 * El lector de certificados abre un socket TLS, así que `Http::fake()` no lo puede
 * interceptar. Por eso está en su propia clase inyectable: acá se la cambia por una
 * que devuelve la fecha que hace falta, sin salir a la red ni depender de cuándo
 * vence el certificado de un sitio real.
 */

function conCertificadoQueVenceEn(?int $dias): void
{
    app()->instance(LectorDeCertificado::class, new class($dias) extends LectorDeCertificado
    {
        public function __construct(private readonly ?int $dias) {}

        public function leer(string $host, int $puerto = 443): ?Certificado
        {
            return $this->dias === null
                ? null
                // `now()` y no `Carbon::now()`: la app usa fechas inmutables, así que
                // esto prueba el mismo tipo que llega en producción.
                : new Certificado(now()->addDays($this->dias)->addHours(2), 'Let\'s Encrypt', $host);
        }
    });
}

it('da ok cuando le queda tiempo de sobra', function () {
    conCertificadoQueVenceEn(60);

    $resultado = app(SondaCertificado::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Ok)
        ->and($resultado->detalle['dias'])->toBe(60);
});

it('advierte a las tres semanas', function () {
    // Los de Let's Encrypt duran 90 días: avisar a los 21 deja tiempo para
    // arreglarlo sin apuro si la renovación automática falló.
    conCertificadoQueVenceEn(20);

    expect(app(SondaCertificado::class)->ejecutar(proyecto())->estado)
        ->toBe(EstadoChequeo::Advertencia);
});

it('falla en la última semana', function () {
    conCertificadoQueVenceEn(5);

    $resultado = app(SondaCertificado::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('vence en 5 días');
});

it('falla y lo dice en pasado si ya venció', function () {
    conCertificadoQueVenceEn(-3);

    $resultado = app(SondaCertificado::class)->ejecutar(proyecto());

    expect($resultado->estado)->toBe(EstadoChequeo::Falla)
        ->and($resultado->mensaje)->toContain('venció hace');
});

it('falla cuando no se puede leer el certificado', function () {
    conCertificadoQueVenceEn(null);

    expect(app(SondaCertificado::class)->ejecutar(proyecto())->estado)
        ->toBe(EstadoChequeo::Falla);
});

it('cuenta los días para abajo y no redondeando', function () {
    // Un certificado que vence en 6 días y 20 horas le quedan 6, no 7: redondear
    // para arriba es justo lo que no se quiere en un aviso de vencimiento.
    $certificado = new Certificado(now()->addDays(6)->addHours(20));

    expect($certificado->diasQueLeQuedan())->toBe(6);
});

it('no aplica a un proyecto que no es https', function () {
    expect(app(SondaCertificado::class)->aplicaA(proyecto(['url' => 'http://viejo.test'])))->toBeFalse()
        ->and(app(SondaCertificado::class)->aplicaA(proyecto()))->toBeTrue();
});
