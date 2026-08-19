<?php

namespace App\Providers;

use App\Services\IngresoConGoogleService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

/**
 * Fortify recortado a lo único que Centinela usa: el login.
 *
 * Todas las features están apagadas en `config/fortify.php` —no hay registro,
 * reset, verificación de email, 2FA ni llaves de acceso—, así que acá quedó una
 * sola vista. Si algún día se enciende una feature hay que volver a registrar su
 * vista y traer su página del starter kit.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            // Sin credenciales de Google el botón no se muestra y la app queda
            // con el login por email y contraseña, que es la puerta de
            // emergencia.
            'googleHabilitado' => IngresoConGoogleService::configurado(),
            'status' => $request->session()->get('status'),
            // El error del ingreso con Google se muestra en la pantalla y no como
            // toast: el Toaster vive en el layout de la app, no en el de auth, y
            // un toast acá no se vería nunca.
            'error' => $request->session()->get('error'),
        ]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
