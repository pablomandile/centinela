<?php

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use App\Models\Chequeo;
use App\Models\Proyecto;

it('deriva el slug del nombre cuando no se le pasa uno', function () {
    $proyecto = Proyecto::create([
        'nombre' => 'Hoy Trasnoche',
        'url' => 'https://hoytrasnoche.pablomandile.com.ar',
    ]);

    expect($proyecto->slug)->toBe('hoy-trasnoche');
});

it('respeta el slug que se le pase', function () {
    $proyecto = Proyecto::create([
        'nombre' => 'Hoy Trasnoche',
        'slug' => 'hoytrasnoche',
        'url' => 'https://hoytrasnoche.pablomandile.com.ar',
    ]);

    expect($proyecto->slug)->toBe('hoytrasnoche');
});

it('sabe armar URLs de su sitio sin duplicar la barra', function () {
    $proyecto = new Proyecto(['url' => 'https://ejemplo.test/']);

    expect($proyecto->urlDe('/sw.js'))->toBe('https://ejemplo.test/sw.js')
        ->and($proyecto->urlDe('sw.js'))->toBe('https://ejemplo.test/sw.js')
        ->and((new Proyecto(['url' => 'https://ejemplo.test']))->urlDe('/sw.js'))
        ->toBe('https://ejemplo.test/sw.js');
});

it('reconoce su host y si es https', function () {
    $proyecto = new Proyecto(['url' => 'https://huella.pablomandile.com.ar']);

    expect($proyecto->host())->toBe('huella.pablomandile.com.ar')
        ->and($proyecto->esHttps())->toBeTrue()
        ->and((new Proyecto(['url' => 'http://viejo.test']))->esHttps())->toBeFalse();
});

it('describe con qué está hecho a partir de sus banderas', function () {
    // El rótulo se deriva y no se guarda: uno guardado se desincroniza el día que
    // la detección cambia una bandera.
    expect((new Proyecto(['usa_inertia' => true, 'es_pwa' => true]))->etiquetaTecnica())
        ->toBe('Laravel + Inertia · PWA')
        ->and((new Proyecto(['usa_inertia' => false, 'tiene_bundle' => true, 'es_pwa' => true]))->etiquetaTecnica())
        ->toBe('SPA · PWA')
        ->and((new Proyecto(['usa_inertia' => false, 'tiene_bundle' => false, 'es_pwa' => false]))->etiquetaTecnica())
        ->toBe('Sin build');
});

it('devuelve el último chequeo de cada tipo en una sola consulta', function () {
    $proyecto = Proyecto::factory()->create();

    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subHour(), 'mensaje' => 'viejo']);
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now(), 'mensaje' => 'nuevo']);
    Chequeo::factory()->for($proyecto)->de(TipoChequeo::Bundle)->create(['mensaje' => 'del bundle']);

    $ultimos = $proyecto->ultimosChequeos();

    expect($ultimos)->toHaveCount(2)
        ->and($ultimos['disponibilidad']->mensaje)->toBe('nuevo')
        ->and($ultimos['bundle']->mensaje)->toBe('del bundle');
});

it('su estado es el peor de sus últimos chequeos', function () {
    $proyecto = Proyecto::factory()->create();

    Chequeo::factory()->for($proyecto)->create();
    Chequeo::factory()->for($proyecto)->de(TipoChequeo::Bundle)->advertencia()->create();

    expect($proyecto->estado())->toBe(EstadoChequeo::Advertencia);

    Chequeo::factory()->for($proyecto)->de(TipoChequeo::Certificado)->falla()->create();

    expect($proyecto->estado())->toBe(EstadoChequeo::Falla);
});

it('sin chequeos no tiene estado, que no es lo mismo que estar bien', function () {
    // Un proyecto recién cargado no está sano: está sin mirar.
    expect(Proyecto::factory()->create()->estado())->toBeNull();
});

it('le toca un chequeo que nunca corrió', function () {
    expect(Proyecto::factory()->create()->toca(TipoChequeo::Disponibilidad))->toBeTrue();
});

it('respeta su intervalo para la disponibilidad', function () {
    $proyecto = Proyecto::factory()->create(['intervalo_minutos' => 15]);
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subMinutes(10)]);

    expect($proyecto->toca(TipoChequeo::Disponibilidad))->toBeFalse();

    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subMinutes(16)]);

    // El más reciente sigue siendo el de hace 10 minutos.
    expect($proyecto->toca(TipoChequeo::Disponibilidad))->toBeFalse()
        ->and($proyecto->toca(TipoChequeo::Disponibilidad, now()->addMinutes(6)))->toBeTrue();
});

it('corre las auditorías una vez por día, no cada cuarto de hora', function () {
    // Solo cambian cuando hubo un deploy: pegarle cada 15 minutos a doce sitios
    // para mirar lo mismo gasta el rate limit sin ganar nada.
    $proyecto = Proyecto::factory()->create(['intervalo_minutos' => 15]);
    Chequeo::factory()->for($proyecto)->de(TipoChequeo::CacheInertia)->create([
        'ejecutado_at' => now()->subHours(3),
    ]);

    expect($proyecto->toca(TipoChequeo::CacheInertia))->toBeFalse()
        ->and($proyecto->toca(TipoChequeo::CacheInertia, now()->addDay()))->toBeTrue();
});

it('los scopes filtran activos y ordenan', function () {
    Proyecto::factory()->create(['nombre' => 'Zeta', 'orden' => 0]);
    Proyecto::factory()->create(['nombre' => 'Alfa', 'orden' => 1]);
    Proyecto::factory()->inactivo()->create(['nombre' => 'Dormido', 'orden' => 2]);

    expect(Proyecto::activos()->ordenados()->pluck('nombre')->all())->toBe(['Zeta', 'Alfa']);
});
