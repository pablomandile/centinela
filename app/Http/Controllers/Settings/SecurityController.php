<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seguridad de la cuenta: solo la contraseña.
 *
 * El 2FA y las llaves de acceso son features de Fortify que Centinela tiene
 * apagadas (ver `config/fortify.php`), así que sus secciones no están en esta
 * pantalla ni sus props acá.
 */
class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            // Quien entró con Google no tiene contraseña: no se le pide la
            // anterior y la pantalla habla de "definir", no de "cambiar".
            'tieneContrasena' => filled(request()->user()?->password),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contraseña actualizada.']);

        return back();
    }
}
