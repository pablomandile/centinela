<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioDeGoogle;

/*
 * Ingreso con Google, la puerta de Centinela.
 *
 * La regla que más importa acá no es la de huella —una cuenta por email— sino la
 * allowlist: Centinela no tiene registro, así que una cuenta de Google válida
 * cuyo email no esté habilitado **no entra y no crea usuario**. Sin eso,
 * cualquiera con una cuenta de Google vería el tablero.
 */

/** Configura las credenciales, como si estuvieran en el .env. */
function conCredencialesDeGoogle(): void
{
    config()->set('services.google.client_id', 'id-de-prueba');
    config()->set('services.google.client_secret', 'secreto-de-prueba');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
}

/**
 * @param  list<string>  $emails
 */
function conAllowlist(array $emails = ['pablo@gmail.com']): void
{
    config()->set('centinela.emails_habilitados', $emails);
}

/**
 * Lo que devuelve Socialite tras el intercambio con Google.
 *
 * @param  array<string, mixed>  $crudo
 */
function respuestaDeGoogle(
    string $id = '1234567890',
    string $email = 'pablo@gmail.com',
    ?string $nombre = 'Pablo Mandile',
    array $crudo = ['verified_email' => true],
): UsuarioDeGoogle {
    $usuario = new UsuarioDeGoogle;
    $usuario->id = $id;
    $usuario->email = $email;
    $usuario->name = $nombre;
    $usuario->user = $crudo;

    return $usuario;
}

/** Deja a Socialite devolviendo esa respuesta, sin salir a la red. */
function socialiteDevuelve(UsuarioDeGoogle $usuario): void
{
    $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $driver->shouldReceive('user')->andReturn($usuario);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

it('esconde la opción mientras no haya credenciales', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    // El front decide con este prop si muestra el botón.
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('googleHabilitado', false));

    // Y las rutas no existen: ofrecer un botón que lleva a un 500 es peor que no
    // ofrecerlo. Es lo que permite desplegar antes del trámite en Google Cloud.
    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();
});

it('ofrece la opción cuando está configurada', function () {
    conCredencialesDeGoogle();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('googleHabilitado', true));

    $this->get(route('google.redirect'))
        ->assertRedirectContains('accounts.google.com');
});

it('crea la cuenta la primera vez, sin contraseña y como admin', function () {
    conCredencialesDeGoogle();
    conAllowlist(['pablo@gmail.com']);
    socialiteDevuelve(respuestaDeGoogle());

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $usuario = User::sole();

    expect($usuario->email)->toBe('pablo@gmail.com')
        ->and($usuario->name)->toBe('Pablo Mandile')
        ->and($usuario->google_id)->toBe('1234567890')
        // Nunca eligió una contraseña: guardarle una al azar la haría figurar
        // como que puede entrar con email y clave cuando no puede.
        ->and($usuario->password)->toBeNull()
        ->and($usuario->esAdmin())->toBeTrue();

    $this->assertAuthenticatedAs($usuario);
});

it('no deja entrar a un email que no está en la allowlist', function () {
    conCredencialesDeGoogle();
    conAllowlist(['pablo@gmail.com']);

    // Cuenta de Google impecable: id, nombre y email verificado. Lo único que le
    // falta es estar habilitada.
    socialiteDevuelve(respuestaDeGoogle(email: 'cualquiera@gmail.com'));

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Esa cuenta no tiene acceso a Centinela.');

    // Y no queda rastro: sin esto, el primer curioso que pruebe entrar aparecería
    // como usuario en la base.
    expect(User::count())->toBe(0);
    $this->assertGuest();
});

it('no le da acceso a un usuario que existía pero salió de la allowlist', function () {
    conCredencialesDeGoogle();
    conAllowlist(['otro@gmail.com']);

    $viejo = User::factory()->create([
        'email' => 'pablo@gmail.com',
        'google_id' => '1234567890',
    ]);

    socialiteDevuelve(respuestaDeGoogle(email: 'pablo@gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    // La allowlist se evalúa en cada ingreso, no solo al crear la cuenta: sacar
    // un email del .env tiene que alcanzar para cerrarle la puerta.
    $this->assertGuest();
    expect($viejo->fresh())->not->toBeNull();
});

it('compara la allowlist sin distinguir mayúsculas ni espacios', function () {
    conCredencialesDeGoogle();
    conAllowlist(['pablo@gmail.com']);
    socialiteDevuelve(respuestaDeGoogle(email: 'Pablo@Gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

it('vincula la cuenta al usuario que ya existía con ese email', function () {
    conCredencialesDeGoogle();
    conAllowlist(['pablo@gmail.com']);

    $existente = User::factory()->create([
        'email' => 'pablo@gmail.com',
        'password' => Hash::make('la-de-siempre'),
    ]);

    socialiteDevuelve(respuestaDeGoogle(email: 'pablo@gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    expect(User::count())->toBe(1);

    $existente->refresh();
    expect($existente->google_id)->toBe('1234567890')
        // Y no perdió la contraseña: sigue siendo la puerta de emergencia.
        ->and(Hash::check('la-de-siempre', $existente->password))->toBeTrue();

    $this->assertAuthenticatedAs($existente);
});

it('reconoce la cuenta por su id de Google aunque haya cambiado el email', function () {
    conCredencialesDeGoogle();
    conAllowlist(['viejo@gmail.com', 'nuevo@gmail.com']);

    $usuario = User::factory()->create([
        'email' => 'viejo@gmail.com',
        'google_id' => '1234567890',
    ]);

    socialiteDevuelve(respuestaDeGoogle(email: 'nuevo@gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    // El id de Google es estable; el email lo cambia el usuario cuando quiere.
    expect(User::count())->toBe(1)
        ->and($usuario->refresh()->email)->toBe('nuevo@gmail.com');
});

it('rechaza una cuenta de Google con el email sin verificar', function () {
    conCredencialesDeGoogle();
    conAllowlist(['ajeno@gmail.com']);

    // Sin esta validación, cualquiera podría reclamar un email habilitado con
    // solo declararlo en una cuenta de Google propia.
    socialiteDevuelve(respuestaDeGoogle(
        email: 'ajeno@gmail.com',
        crudo: ['verified_email' => false],
    ));

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(User::count())->toBe(0);
    $this->assertGuest();
});

it('rechaza una respuesta sin email', function () {
    conCredencialesDeGoogle();
    socialiteDevuelve(respuestaDeGoogle(email: ''));

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(User::count())->toBe(0);
});

it('vuelve al login sin cartel de error si el usuario canceló en Google', function () {
    conCredencialesDeGoogle();

    // Cancelar no es un error: no hay nada que avisar.
    $this->get(route('google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionMissing('error');

    expect(User::count())->toBe(0);
});

it('no filtra el detalle técnico cuando Google falla', function () {
    conCredencialesDeGoogle();

    $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $driver->shouldReceive('user')->andThrow(
        new RuntimeException('Client error: 401 con el token AIzaSyDdI0hCZtE6vy'),
    );
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    // El mensaje de Socialite trae partes de la respuesta de Google: al usuario
    // se le muestra uno propio.
    expect(session('error'))->toBe('No pudimos entrar con Google. Probá de nuevo en un rato.');
});

it('no rehace el flujo si ya hay una sesión abierta', function () {
    conCredencialesDeGoogle();

    $this->actingAs(User::factory()->create())
        ->get(route('google.redirect'))
        ->assertRedirect(route('dashboard'));
});
