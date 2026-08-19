<?php

namespace App\Services;

use App\Enums\FormatoDocumento;
use App\Models\Documento;
use App\Models\Proyecto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guardar, reemplazar y borrar documentos.
 *
 * Dos decisiones que valen la pena:
 *
 * 1. **Los archivos van al disco privado** y se sirven por controlador después de
 *    autorizar. Nunca por URL pública: adivinar un id no puede alcanzar para leer
 *    la documentación de un proyecto.
 * 2. **El hash decide si es el mismo archivo.** Resubir algo idéntico no crea otra
 *    fila; resubirlo cambiado actualiza la que había y borra el archivo viejo. Sin
 *    eso, después de tres semanas de "por si acaso lo subo de nuevo" la lista tiene
 *    cinco copias de README.md y ninguna dice cuál es la buena.
 */
class DocumentoService
{
    public function guardar(Proyecto $proyecto, UploadedFile $archivo): Documento
    {
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $formato = FormatoDocumento::desdeExtension($extension);
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $hash = hash('sha256', $contenido);

        $existente = $proyecto->documentos()
            ->where('nombre_original', $archivo->getClientOriginalName())
            ->first();

        // Exactamente el mismo archivo: no hay nada que hacer.
        if ($existente !== null && $existente->hash === $hash) {
            return $existente;
        }

        $ruta = $archivo->storeAs(
            "documentos/{$proyecto->id}",
            Str::ulid().'.'.($extension ?: 'md'),
            'local',
        );

        $texto = $formato === FormatoDocumento::Md ? $contenido : null;

        $datos = [
            'formato' => $formato,
            'ruta' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'tamano' => $archivo->getSize(),
            'hash' => $hash,
            'texto' => $texto,
        ];

        if ($existente !== null) {
            $viejo = $existente->ruta;
            $existente->update([
                ...$datos,
                // El título no se toca al reemplazar: puede haber sido editado o
                // haber cambiado el encabezado, y la URL del documento depende del
                // slug que se derivó de él.
                'texto_normalizado' => Documento::textoParaBuscar($existente->titulo, $texto),
            ]);
            Storage::disk('local')->delete($viejo);

            return $existente;
        }

        $titulo = $this->titulo($archivo->getClientOriginalName(), $texto);

        return $proyecto->documentos()->create([
            ...$datos,
            'titulo' => $titulo,
            'slug' => $this->slugLibre($proyecto, $titulo),
            'texto_normalizado' => Documento::textoParaBuscar($titulo, $texto),
            'orden' => (int) $proyecto->documentos()->max('orden') + 1,
        ]);
    }

    public function eliminar(Documento $documento): void
    {
        // El archivo se borra de verdad aunque la fila tenga soft delete: un
        // documento "borrado" que sigue ocupando disco en un plan compartido con
        // cuota es exactamente lo que no se quiere.
        Storage::disk('local')->delete($documento->ruta);

        $documento->delete();
    }

    /**
     * El título: el primer encabezado del markdown, o el nombre del archivo.
     *
     * Los `.md` de estos proyectos casi siempre arrancan con un `# Título` que
     * describe mucho mejor el contenido que "ARCHITECTURE.md".
     */
    private function titulo(string $nombreOriginal, ?string $texto): string
    {
        if (filled($texto) && preg_match('/^\s*#\s+(.+)$/m', $texto, $coincidencia) === 1) {
            return Str::limit(trim($coincidencia[1]), 110, '');
        }

        return Str::limit(pathinfo($nombreOriginal, PATHINFO_FILENAME), 110, '');
    }

    /**
     * Un slug que no choque con otro documento del mismo proyecto.
     */
    private function slugLibre(Proyecto $proyecto, string $titulo): string
    {
        $base = Str::slug($titulo) ?: 'documento';
        $slug = $base;
        $sufijo = 2;

        while ($proyecto->documentos()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
