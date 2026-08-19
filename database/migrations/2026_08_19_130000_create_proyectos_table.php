<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();

            // La URL canónica, con esquema. Importa que sea la canónica: las rutas
            // absolutas (/build/..., /sw.js, /manifest.webmanifest) resuelven ahí,
            // así que auditar el subpath en vez del subdominio da falsos negativos.
            $table->string('url');

            $table->string('repo_url')->nullable();

            /*
             * Qué sabe hacer el sitio, y por lo tanto qué sondas se le corren.
             *
             * Son tres banderas y no un enum de perfiles porque las tres son
             * independientes de verdad: hoytrasnoche es PHP sin build **y** tiene
             * manifest, y un enum con un caso por combinación crece al cuadrado.
             * Lo descubrió `centinela:detectar-perfil` el primer día que corrió
             * contra los sitios reales.
             *
             * Las llena la detección, que le pregunta al sitio en vez de recordar.
             */
            $table->boolean('usa_inertia')->default(false);
            $table->boolean('es_pwa')->default(false);
            $table->boolean('tiene_bundle')->default(false);

            // Un proyecto inactivo se lista en gris y no genera chequeos ni avisos.
            // Es para los que todavía no están publicados, no para borrarlos.
            $table->boolean('activo')->default(true);

            // Texto que tiene que aparecer en el HTML. Un 200 no alcanza: una
            // pantalla de error de PHP también contesta 200.
            $table->string('palabra_clave')->nullable();

            $table->unsignedSmallInteger('intervalo_minutos')->default(15);
            $table->text('notas')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
