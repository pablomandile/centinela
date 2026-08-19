<?php

namespace App\Http\Controllers;

use App\Enums\FormatoDocumento;
use App\Models\Documento;
use App\Models\Proyecto;
use App\Services\MarkdownService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Los markdown, en PDF.
 *
 * Para qué: llevar la documentación a una reunión, mandarla por mail o guardarla
 * en algún lado que no sea Centinela.
 *
 * **Los PDF que ya están subidos no se concatenan** en el dossier: DomPDF no une
 * PDFs y sumar `fpdi` para eso no se justifica. Se listan con su enlace al final.
 */
class PdfController extends Controller
{
    public function __construct(private readonly MarkdownService $markdown) {}

    /**
     * Un documento suelto.
     */
    public function documento(Proyecto $proyecto, Documento $documento): Response
    {
        Gate::authorize('view', $documento);
        abort_unless($documento->formato->seLeeAdentro(), 404, 'Ese documento ya es un PDF.');

        $pdf = Pdf::loadView('pdf.documento', [
            'proyecto' => $proyecto,
            'documento' => $documento,
            'html' => $this->markdown->aHtml((string) $documento->texto),
        ]);

        return $pdf->download(Str::slug("{$proyecto->slug} {$documento->slug}").'.pdf');
    }

    /**
     * Todos los markdown de un proyecto en un solo PDF, con portada e índice.
     */
    public function dossier(Proyecto $proyecto): Response
    {
        Gate::authorize('view', $proyecto);

        $documentos = $proyecto->documentos()->ordenados()->get();
        $markdown = $documentos->where('formato', FormatoDocumento::Md);

        abort_if($markdown->isEmpty(), 404, 'Este proyecto no tiene documentos en markdown.');

        $pdf = Pdf::loadView('pdf.dossier', [
            'proyecto' => $proyecto,
            'secciones' => $markdown->map(fn (Documento $documento) => [
                'titulo' => $documento->titulo,
                'nombre' => $documento->nombre_original,
                'html' => $this->markdown->aHtml((string) $documento->texto),
            ])->values(),
            // Los PDF subidos no se pueden unir, así que van listados al final para
            // que el dossier no mienta sobre lo que contiene.
            'adjuntos' => $documentos->where('formato', FormatoDocumento::Pdf)
                ->map(fn (Documento $documento) => $documento->nombre_original)
                ->values(),
            'generado' => now(),
        ]);

        return $pdf->download(Str::slug("dossier {$proyecto->slug}").'.pdf');
    }
}
