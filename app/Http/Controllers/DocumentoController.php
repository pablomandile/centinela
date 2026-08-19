<?php

namespace App\Http\Controllers;

use App\Enums\FormatoDocumento;
use App\Http\Requests\SubirDocumentosRequest;
use App\Models\Documento;
use App\Models\Proyecto;
use App\Services\DocumentoService;
use App\Services\MarkdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    public function __construct(
        private readonly DocumentoService $documentos,
        private readonly MarkdownService $markdown,
    ) {}

    /**
     * El buscador global sobre todos los documentos.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Documento::class);

        $termino = (string) $request->query('q', '');

        $documentos = Documento::with('proyecto')
            ->buscar($termino)
            ->ordenados()
            ->get()
            ->map(fn (Documento $documento) => [
                'slug' => $documento->slug,
                'titulo' => $documento->titulo,
                'formato' => $documento->formato->value,
                'tamano' => $documento->tamanoLegible(),
                'proyecto' => $documento->proyecto->nombre,
                'proyectoSlug' => $documento->proyecto->slug,
                'fragmento' => $documento->fragmento($termino),
                'actualizado' => $documento->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('documentos/Index', [
            'q' => $termino,
            'documentos' => $documentos->values(),
            // Agrupado por proyecto para el listado sin búsqueda; el front decide
            // cómo mostrarlo según haya término o no.
            'porProyecto' => $documentos->groupBy('proyecto'),
        ]);
    }

    /**
     * El visor. Los markdown se leen adentro; los PDF se abren en el visor del
     * sistema, que en el celular anda mejor que cualquier cosa embebida.
     */
    public function show(Proyecto $proyecto, Documento $documento): Response|StreamedResponse
    {
        Gate::authorize('view', $documento);

        if (! $documento->formato->seLeeAdentro()) {
            return $this->servir($documento, descargar: false);
        }

        $renderizado = $this->markdown->renderizar((string) $documento->texto);

        return Inertia::render('documentos/Show', [
            'documento' => [
                'slug' => $documento->slug,
                'titulo' => $documento->titulo,
                'nombre_original' => $documento->nombre_original,
                'tamano' => $documento->tamanoLegible(),
                'actualizado' => $documento->updated_at?->toIso8601String(),
            ],
            'proyecto' => [
                'slug' => $proyecto->slug,
                'nombre' => $proyecto->nombre,
            ],
            'html' => $renderizado['html'],
            'indice' => $renderizado['indice'],
        ]);
    }

    public function store(SubirDocumentosRequest $request, Proyecto $proyecto): RedirectResponse
    {
        Gate::authorize('create', Documento::class);

        $cuantos = 0;

        foreach ($request->file('archivos') as $archivo) {
            $this->documentos->guardar($proyecto, $archivo);
            $cuantos++;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $cuantos === 1 ? 'Documento guardado.' : "{$cuantos} documentos guardados.",
        ]);

        return back();
    }

    /**
     * Que el documento pertenezca al proyecto de la URL lo garantiza el
     * `scopeBindings()` de las rutas, no un chequeo acá.
     */
    public function destroy(Proyecto $proyecto, Documento $documento): RedirectResponse
    {
        Gate::authorize('delete', $documento);

        $this->documentos->eliminar($documento);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento eliminado.']);

        return back();
    }

    /**
     * El archivo original, para bajar.
     */
    public function descargar(Proyecto $proyecto, Documento $documento): StreamedResponse
    {
        Gate::authorize('view', $documento);

        return $this->servir($documento, descargar: true);
    }

    /**
     * Sirve el archivo desde el disco privado.
     *
     * Nunca hay URL pública: adivinar un slug no puede alcanzar para leer la
     * documentación de un proyecto. Es el patrón de `AdjuntoController::mostrar` de
     * huella.
     */
    private function servir(Documento $documento, bool $descargar): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($documento->ruta), 404);

        return Storage::disk('local')->response(
            $documento->ruta,
            $documento->nombre_original,
            [
                'Content-Type' => $documento->formato->mime(),
                // Los PDF se abren en el visor del navegador salvo que se pida bajar.
                'Content-Disposition' => ($descargar || $documento->formato === FormatoDocumento::Md ? 'attachment' : 'inline')
                    .'; filename="'.$documento->nombre_original.'"',
            ],
        );
    }
}
