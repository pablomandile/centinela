<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nombre y email de la cuenta.
 *
 * No tiene `destroy`: ver el porqué en ProfileUpdateTest.
 */
class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Profile');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        // Sin resetear `email_verified_at` al cambiar el email: la verificación
        // está apagada, y quien entra con Google vuelve a entrar igual —se lo
        // reconoce por el `sub`, no por el email—.
        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perfil actualizado.']);

        return to_route('profile.edit');
    }
}
