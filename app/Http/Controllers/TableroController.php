<?php

namespace App\Http\Controllers;

use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Collection as ColeccionDeModelos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero: una tarjeta por proyecto con su semáforo.
 *
 * Tres consultas en total, no una por proyecto: doce proyectos por seis sondas
 * serían setenta y dos consultas por pantalla si cada tarjeta preguntara sola.
 */
class TableroController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Proyecto::class);

        $proyectos = Proyecto::query()->ordenados()->get();
        $ultimos = $this->ultimosChequeos();
        $abiertos = $this->incidentesAbiertos();

        return Inertia::render('Tablero', [
            'proyectos' => $proyectos->map(fn (Proyecto $proyecto) => [
                'slug' => $proyecto->slug,
                'nombre' => $proyecto->nombre,
                'url' => $proyecto->url,
                'activo' => $proyecto->activo,
                'tecnologia' => $proyecto->etiquetaTecnica(),
                'chequeos' => $ultimos->get($proyecto->id, collect())
                    ->map(fn (Chequeo $chequeo) => $this->comoSeVe($chequeo))
                    ->values(),
                'incidentes' => $abiertos->get($proyecto->id, 0),
            ])->values(),
            'resumen' => [
                'proyectos' => $proyectos->where('activo', true)->count(),
                'incidentes' => $abiertos->sum(),
                'sinChequear' => $proyectos->filter(
                    fn (Proyecto $proyecto) => $proyecto->activo && $ultimos->get($proyecto->id) === null,
                )->count(),
            ],
        ]);
    }

    /**
     * El último chequeo de cada tipo de cada proyecto, en una consulta.
     *
     * @return Collection<int|string, ColeccionDeModelos<int, Chequeo>>
     */
    private function ultimosChequeos(): Collection
    {
        return Chequeo::whereIn('id', function ($query) {
            // El id máximo por (proyecto, tipo): el id crece con el tiempo, así que
            // sirve de desempate cuando dos chequeos caen en el mismo segundo.
            $query->selectRaw('MAX(id)')->from('chequeos')->groupBy('proyecto_id', 'tipo');
        })->get()->groupBy('proyecto_id');
    }

    /**
     * Cuántos incidentes abiertos tiene cada proyecto.
     *
     * @return Collection<int, int>
     */
    private function incidentesAbiertos(): Collection
    {
        return Incidente::abiertos()
            ->selectRaw('proyecto_id, COUNT(*) as cuantos')
            ->groupBy('proyecto_id')
            ->pluck('cuantos', 'proyecto_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function comoSeVe(Chequeo $chequeo): array
    {
        return [
            'tipo' => $chequeo->tipo->value,
            'etiqueta' => $chequeo->tipo->etiqueta(),
            'estado' => $chequeo->estado->value,
            'mensaje' => $chequeo->mensaje,
            'latencia' => $chequeo->latencia_ms,
            // ISO 8601 y en UTC: la conversión a hora local la hace dayjs en el
            // navegador, así la app se lee bien desde cualquier zona.
            'cuando' => $chequeo->ejecutado_at->toIso8601String(),
        ];
    }
}
