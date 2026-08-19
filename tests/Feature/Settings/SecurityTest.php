<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Seguridad: solo la contraseña.
 *
 * Los tests de 2FA y llaves de acceso que traía el starter kit se borraron: sus
 * features están apagadas para siempre en `config/fortify.php`, así que siempre
 * se saltaban y un test que nunca corre solo hace ruido.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('tieneContrasena', true)
                ->has('passwordRules'),
            );
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }

    public function test_quien_entro_con_google_puede_definir_una_contrasena_sin_dar_la_anterior()
    {
        /*
         * Es la puerta de emergencia por si Google falla, y sin esto no existiría:
         * un usuario creado por Google no tiene contraseña, así que exigirle la
         * "actual" le cierra la única forma de definir una.
         */
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-123']);

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('tieneContrasena', false));

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'password' => 'la-de-emergencia',
                'password_confirmation' => 'la-de-emergencia',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('la-de-emergencia', $user->refresh()->password));
    }
}
