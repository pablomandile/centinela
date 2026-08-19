<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un incidente abierto por proyecto y tipo, como máximo.
     *
     * La unicidad **no** se declara acá: sería `unique(proyecto_id, tipo,
     * cerrado_at)` y MySQL admite múltiples NULL en un índice único, así que dos
     * incidentes abiertos pasarían igual. La garantiza `EjecutorDeChequeos` dentro
     * de una transacción con `lockForUpdate`.
     */
    public function up(): void
    {
        Schema::create('incidentes', function (Blueprint $table) {
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

            $table->timestamp('abierto_at');
            $table->timestamp('cerrado_at')->nullable();

            $table->string('ultimo_mensaje')->nullable();

            // Cuándo salió el mail. Separado de `abierto_at` porque el incidente
            // existe aunque el mail no se haya podido mandar, y porque sin
            // CENTINELA_AVISOS_A no se manda ninguno.
            $table->timestamp('avisado_at')->nullable();
            $table->timestamp('avisado_cierre_at')->nullable();

            $table->timestamps();

            $table->index(['proyecto_id', 'tipo', 'cerrado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentes');
    }
};
