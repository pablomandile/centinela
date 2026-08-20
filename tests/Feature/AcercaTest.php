<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('pide sesión', function () {
    $this->get('/acerca')->assertRedirect(route('login'));
});

it('muestra la página', function () {
    $this->actingAs(User::factory()->create())
        ->get('/acerca')
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina->component('Acerca'));
});
