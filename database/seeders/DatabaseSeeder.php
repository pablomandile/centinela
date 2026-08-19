<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * No crea usuarios: el único que existe lo crea el ingreso con Google contra la
 * allowlist. Un usuario de prueba con contraseña conocida en una app que se
 * despliega sería una puerta abierta.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ProyectosSeeder::class);
    }
}
