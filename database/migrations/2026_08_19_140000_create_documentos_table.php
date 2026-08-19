<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained()->cascadeOnDelete();

            $table->string('titulo');
            $table->string('slug', 120);

            $table->enum('formato', ['md', 'pdf']);

            // Ruta dentro del disco **privado**. Nunca se sirve por URL pública:
            // va por controlador después de autorizar, como los adjuntos de huella.
            $table->string('ruta');

            $table->string('nombre_original');
            $table->unsignedInteger('tamano');

            // sha256 del archivo. Sirve para dos cosas: resubir el mismo archivo es
            // un no-op, y resubirlo cambiado actualiza la fila en vez de duplicarla.
            $table->string('hash', 64);

            /*
             * El texto plano del markdown, para el buscador.
             *
             * La búsqueda va con LIKE sobre esto y **no** con FULLTEXT: los tests
             * corren en sqlite, que no lo soporta, y el volumen real son unos pocos
             * MB. De los PDF no se extrae texto: haría falta un parser y lo que se
             * quiere de ellos es poder abrirlos.
             */
            $table->longText('texto')->nullable();

            /*
             * El título y el texto juntos, en minúsculas y sin acentos.
             *
             * Existe para que la búsqueda funcione **igual** en los dos motores:
             * MySQL con utf8mb4_unicode_ci ignora los acentos por collation y sqlite
             * —el de los tests— no, así que sin esta columna "documentacion"
             * encontraría "documentación" en producción y no en los tests, o al
             * revés. Es la misma familia de trampa que `Rule::unique` sobre un
             * `date`.
             */
            $table->longText('texto_normalizado')->nullable();

            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // El slug es la URL del documento dentro de su proyecto.
            $table->unique(['proyecto_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
