<?php

namespace App\Http\Controllers;

use App\Enums\TipoChequeo;
use App\Http\Requests\GuardarProyectoRequest;
use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use App\Services\DetectorDePerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProyectoController extends Controller
{
    /**
     * El listado con los formularios de alta y edición.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Proyecto::class);

        return Inertia::render('proyectos/Index', [
            'proyectos' => Proyecto::query()->ordenados()->get()->map(fn (Proyecto $proyecto) => [
                'slug' => $proyecto->slug,
                'nombre' => $proyecto->nombre,
                'url' => $proyecto->url,
                'repo_url' => $proyecto->repo_url,
                'usa_inertia' => $proyecto->usa_inertia,
                'es_pwa' => $proyecto->es_pwa,
                'tiene_bundle' => $proyecto->tiene_bundle,
                'activo' => $proyecto->activo,
                'palabra_clave' => $proyecto->palabra_clave,
                'intervalo_minutos' => $proyecto->intervalo_minutos,
                'notas' => $proyecto->notas,
                'tecnologia' => $proyecto->etiquetaTecnica(),
            ])->values(),
        ]);
    }

    /**
     * El detalle: estado de cada sonda, historial e incidentes.
     */
    public function show(Proyecto $proyecto): Response
    {
        Gate::authorize('view', $proyecto);

        return Inertia::render('proyectos/Show', [
            'proyecto' => [
                'slug' => $proyecto->slug,
                'nombre' => $proyecto->nombre,
                'url' => $proyecto->url,
                'repo_url' => $proyecto->repo_url,
                'activo' => $proyecto->activo,
                'tecnologia' => $proyecto->etiquetaTecnica(),
                'notas' => $proyecto->notas,
                'intervalo_minutos' => $proyecto->intervalo_minutos,
            ],
            'chequeos' => $proyecto->ultimosChequeos()
                ->map(fn (Chequeo $chequeo) => [
                    'tipo' => $chequeo->tipo->value,
                    'etiqueta' => $chequeo->tipo->etiqueta(),
                    'descripcion' => $chequeo->tipo->descripcion(),
                    'estado' => $chequeo->estado->value,
                    'mensaje' => $chequeo->mensaje,
                    'codigo' => $chequeo->codigo_http,
                    'latencia' => $chequeo->latencia_ms,
                    'cuando' => $chequeo->ejecutado_at->toIso8601String(),
                    'detalle' => $chequeo->detalle,
                ])
                ->sortBy(fn (array $chequeo) => array_search($chequeo['tipo'], array_column(TipoChequeo::cases(), 'value'), true))
                ->values(),
            'latencias' => $this->latencias($proyecto),
            'documentos' => $proyecto->documentos()->ordenados()->get()->map(fn ($documento) => [
                'slug' => $documento->slug,
                'titulo' => $documento->titulo,
                'formato' => $documento->formato->value,
                'nombre_original' => $documento->nombre_original,
                'tamano' => $documento->tamanoLegible(),
                'actualizado' => $documento->updated_at?->toIso8601String(),
            ]),
            'incidentes' => $proyecto->incidentes()
                ->latest('abierto_at')
                ->limit(20)
                ->get()
                ->map(fn (Incidente $incidente) => [
                    'id' => $incidente->id,
                    'tipo' => $incidente->tipo->etiqueta(),
                    'abierto' => $incidente->abierto_at->toIso8601String(),
                    'cerrado' => $incidente->cerrado_at?->toIso8601String(),
                    'duracion' => $incidente->duracion(),
                    'mensaje' => $incidente->ultimo_mensaje,
                ]),
        ]);
    }

    public function store(GuardarProyectoRequest $request): RedirectResponse
    {
        Gate::authorize('create', Proyecto::class);

        $proyecto = Proyecto::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "«{$proyecto->nombre}» quedó cargado."]);

        return to_route('proyectos.index');
    }

    public function update(GuardarProyectoRequest $request, Proyecto $proyecto): RedirectResponse
    {
        Gate::authorize('update', $proyecto);

        $proyecto->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proyecto actualizado.']);

        return to_route('proyectos.index');
    }

    /**
     * Baja lógica: los chequeos y los documentos siguen colgados del proyecto.
     */
    public function destroy(Proyecto $proyecto): RedirectResponse
    {
        Gate::authorize('delete', $proyecto);

        $proyecto->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "«{$proyecto->nombre}» se quitó de la lista."]);

        return to_route('proyectos.index');
    }

    /**
     * Le pregunta al sitio qué usa y guarda las banderas.
     *
     * Acá sí se aplica sin confirmar, al contrario que el comando: es de a un
     * proyecto y con el usuario mirando la pantalla.
     */
    public function detectar(Proyecto $proyecto, DetectorDePerfil $detector): RedirectResponse
    {
        Gate::authorize('update', $proyecto);

        $deteccion = $detector->detectar($proyecto);

        if ($deteccion['banderas'] === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $deteccion['motivo']]);

            return back();
        }

        $proyecto->update($deteccion['banderas']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$proyecto->etiquetaTecnica()} ({$deteccion['motivo']}).",
        ]);

        return back();
    }

    /**
     * Las latencias de las últimas 24 horas para el gráfico.
     *
     * Solo de la sonda de disponibilidad: es la única que corre seguido, y mezclar
     * en un gráfico series que se miden cada 15 minutos con otras que se miden una
     * vez por día no dice nada.
     *
     * @return list<array<string, mixed>>
     */
    private function latencias(Proyecto $proyecto): array
    {
        $puntos = $proyecto->chequeos()
            ->where('tipo', TipoChequeo::Disponibilidad)
            ->where('ejecutado_at', '>=', now()->subDay())
            ->orderBy('ejecutado_at')
            ->get(['latencia_ms', 'estado', 'ejecutado_at'])
            ->map(fn (Chequeo $chequeo) => [
                'cuando' => $chequeo->ejecutado_at->toIso8601String(),
                'latencia' => $chequeo->latencia_ms,
                'estado' => $chequeo->estado->value,
            ]);

        // `array_values` y no `->values()->all()`: el gráfico espera una lista, las
        // claves de la colección vienen del id de cada fila, y esta forma es la que
        // el análisis estático puede verificar.
        return array_values($puntos->all());
    }
}
