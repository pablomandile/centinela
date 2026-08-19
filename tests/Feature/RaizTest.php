<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaizTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_lleva_al_tablero()
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_a_quien_no_entro_lo_manda_al_login()
    {
        // Centinela no tiene landing: la raíz redirige al tablero y el
        // middleware `auth` de ahí desvía al login.
        $this->get('/')->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
