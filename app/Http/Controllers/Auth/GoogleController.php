<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AccesoNoHabilitado;
use App\Http\Controllers\Controller;
use App\Services\IngresoConGoogleService;
use App\Support\CuentaDeGoogle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as RespuestaDeSocialite;
use Throwable;

/**
 * Ingreso con Google, la puerta principal de Centinela.
 *
 * Las dos rutas devuelven 404 si no hay credenciales configuradas, en vez de
 * explotar con un error de Socialite: así el código puede estar desplegado antes
 * de que exista el proyecto en Google Cloud.
 */
class GoogleController extends Controller
{
    public function __construct(private readonly IngresoConGoogleService $ingreso) {}

    /**
     * Manda a la pantalla de Google.
     */
    public function redirigir(): RespuestaDeSocialite|RedirectResponse
    {
        abort_unless(IngresoConGoogleService::configurado(), 404);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Vuelta de Google: resuelve la cuenta e inicia la sesión.
     */
    public function volver(Request $request): RedirectResponse
    {
        abort_unless(IngresoConGoogleService::configurado(), 404);

        // El usuario canceló en la pantalla de Google. No es un error: vuelve al
        // login sin ningún cartel rojo.
        if ($request->has('error')) {
            return redirect()->route('login');
        }

        try {
            $usuario = $this->ingreso->resolver(
                CuentaDeGoogle::desdeSocialite(Socialite::driver('google')->user()),
            );
        } catch (AccesoNoHabilitado) {
            // Caso esperado, no una falla: alguien probó entrar. No va al log
            // como warning y el mensaje no confirma ni desmiente nada sobre la
            // cuenta.
            return redirect()->route('login')->with(
                'error',
                'Esa cuenta no tiene acceso a Centinela.',
            );
        } catch (Throwable $e) {
            // El detalle va al log y no a la pantalla: los mensajes de Socialite
            // traen partes de la respuesta de Google, que no le dicen nada al
            // usuario y pueden incluir datos de la cuenta.
            Log::warning('Falló el ingreso con Google.', ['excepcion' => $e->getMessage()]);

            return redirect()->route('login')->with(
                'error',
                'No pudimos entrar con Google. Probá de nuevo en un rato.',
            );
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
