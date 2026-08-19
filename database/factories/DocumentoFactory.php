<?php

namespace Database\Factories;

use App\Enums\FormatoDocumento;
use App\Models\Documento;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titulo = 'Documento '.fake()->unique()->numberBetween(1000, 9999);
        $texto = "# {$titulo}\n\nUn párrafo cualquiera.";

        return [
            'proyecto_id' => Proyecto::factory(),
            'titulo' => $titulo,
            'slug' => Str::slug($titulo),
            'formato' => FormatoDocumento::Md,
            'ruta' => 'documentos/1/'.Str::ulid().'.md',
            'nombre_original' => Str::slug($titulo).'.md',
            'tamano' => strlen($texto),
            'hash' => hash('sha256', $texto),
            'texto' => $texto,
            'texto_normalizado' => Documento::textoParaBuscar($titulo, $texto),
            'orden' => 0,
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn (array $atributos) => [
            'formato' => FormatoDocumento::Pdf,
            'ruta' => 'documentos/1/'.Str::ulid().'.pdf',
            'nombre_original' => Str::slug($atributos['titulo']).'.pdf',
            // De los PDF no se extrae texto: harían falta un parser y lo que se
            // quiere de ellos es poder abrirlos.
            'texto' => null,
            'texto_normalizado' => Documento::textoParaBuscar($atributos['titulo'], null),
        ]);
    }
}
