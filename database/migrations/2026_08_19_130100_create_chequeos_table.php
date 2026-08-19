<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El historial de chequeos.
     *
     * Es la tabla que crece: doce proyectos por seis sondas cada quince minutos
     * son cientos de miles de filas por año. `centinela:podar` la recorta a 90
     * días, y en hosting compartido eso no es prolijidad, es no llenar el disco.
     */
    public function up(): void
    {
        Schema::create('chequeos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained()->cascadeOnDelete();

            $table->enum('tipo', [
                'disponibilidad',
                'certificado',
                'redireccion_https',
                'cache_inertia',
                'cabeceras_pwa',
                'bundle',
            ]);

            $table->enum('estado', ['ok', 'advertencia', 'falla']);

            $table->unsignedSmallInteger('codigo_http')->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();

            // Lo que vio la sonda: cadena de redirects, cabeceras, días que le
            // quedan al certificado, hash del bundle. Es lo que después permite
            // explicar el veredicto sin volver a pegarle al sitio.
            $table->json('detalle')->nullable();

            $table->string('mensaje')->nullable();
            $table->timestamp('ejecutado_at');

            // El índice que usan el tablero (último por tipo) y la poda.
            $table->index(['proyecto_id', 'tipo', 'ejecutado_at']);
            $table->index('ejecutado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chequeos');
    }
};
