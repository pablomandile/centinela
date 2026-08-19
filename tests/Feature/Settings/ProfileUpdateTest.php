<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_borrar_la_cuenta_no_existe()
    {
        /*
         * Centinela no tiene "eliminar mi cuenta". Borrar el único usuario no
         * borra nada más —los proyectos y los documentos quedan— y el próximo
         * ingreso con Google lo recrea, así que el botón solo servía para
         * confundir. Además no podía funcionar: pedía la contraseña actual, y
         * quien entra con Google no tiene ninguna.
         */
        $user = User::factory()->create();

        // 405 y no 404: la URI existe para GET y PATCH, pero no para DELETE.
        $this->actingAs($user)->delete('/settings/profile')->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }
}
