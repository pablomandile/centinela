<?php

use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;

it('borra los chequeos viejos y deja los recientes', function () {
    $proyecto = Proyecto::factory()->create();

    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subDays(120)]);
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subDays(91)]);
    $reciente = Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subDay()]);

    $this->artisan('centinela:podar')
        ->expectsOutputToContain('Borrados 2 chequeos')
        ->assertSuccessful();

    expect(Chequeo::pluck('id')->all())->toBe([$reciente->id]);
});

it('acepta otro período de retención', function () {
    $proyecto = Proyecto::factory()->create();
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subDays(10)]);
    Chequeo::factory()->for($proyecto)->create(['ejecutado_at' => now()->subDays(2)]);

    $this->artisan('centinela:podar --dias=7')->assertSuccessful();

    expect(Chequeo::count())->toBe(1);
});

it('no acepta un período de menos de un día', function () {
    // Con 0 borraría el chequeo que se acaba de guardar.
    Chequeo::factory()->create();

    $this->artisan('centinela:podar --dias=0')->assertFailed();

    expect(Chequeo::count())->toBe(1);
});

it('no toca los incidentes', function () {
    // Son pocos y son justamente la historia que se quiere conservar.
    $proyecto = Proyecto::factory()->create();
    $incidente = Incidente::factory()->for($proyecto)->cerrado()->create([
        'abierto_at' => now()->subDays(200),
        'cerrado_at' => now()->subDays(199),
    ]);

    $this->artisan('centinela:podar')->assertSuccessful();

    expect($incidente->fresh())->not->toBeNull();
});
