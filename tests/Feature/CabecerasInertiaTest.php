<?php

use App\Http\Middleware\HandleInertiaRequests;

/**
 * La versión del asset, o Inertia contesta 409 en vez de la página y el test
 * parece roto sin estarlo.
 */
function versionDeInertia(): string
{
    return (string) app(HandleInertiaRequests::class)->version(request());
}

it('tiene un manifest de build, o los tests de abajo pasan por el motivo equivocado', function () {
    // Sin public/build/manifest.json la versión es '' y el 409 nunca aparece:
    // el test del XHR pasaría sin haber ejercitado nada. Corré `npm run build`.
    expect(versionDeInertia())->not->toBe('');
});

it('prohíbe guardar la respuesta XHR de Inertia', function () {
    $respuesta = $this->get('/login', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => versionDeInertia(),
    ]);

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('application/json');
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('deja cacheable el documento HTML, para no perder el bfcache', function () {
    // `no-store` acá desactivaría el back/forward cache de Chrome y cada "atrás"
    // sería una ida completa a la red. No da ningún síntoma que lo delate, así
    // que lo cuida este test.
    $respuesta = $this->get('/login');

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('text/html');
    expect($respuesta->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('declara el Vary que distingue las dos respuestas', function () {
    $respuesta = $this->get('/login');

    expect($respuesta->headers->get('Vary'))->toContain('X-Inertia');
});
