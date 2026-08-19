<?php

namespace App\Console\Commands;

use App\Models\Proyecto;
use App\Services\DetectorDePerfil;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le pregunta a cada sitio qué sabe hacer y ajusta sus tres banderas.
 *
 * **Por defecto solo informa.** Guardar requiere `--aplicar`: un error en la
 * detección que reescriba en silencio las banderas de los dieciséis proyectos
 * cambiaría qué se audita en cada uno, y eso se notaría recién cuando algo dejara
 * de avisar.
 */
class DetectarPerfiles extends Command
{
    protected $signature = 'centinela:detectar-perfil
        {slug? : Un proyecto en particular}
        {--aplicar : Guarda las banderas detectadas}
        {--inactivos : Incluye los proyectos inactivos}';

    protected $description = 'Detecta qué usa cada sitio (Inertia, PWA, bundle) y ajusta sus banderas.';

    /** @var list<string> */
    private const BANDERAS = ['usa_inertia', 'es_pwa', 'tiene_bundle'];

    public function handle(DetectorDePerfil $detector): int
    {
        $proyectos = $this->proyectos();

        if ($proyectos->isEmpty()) {
            $this->components->warn('No hay proyectos que revisar.');

            return self::SUCCESS;
        }

        $filas = [];
        $cambios = 0;

        foreach ($proyectos as $proyecto) {
            $deteccion = $detector->detectar($proyecto);
            $banderas = $deteccion['banderas'];

            if ($banderas === null) {
                $filas[] = [$proyecto->slug, $this->comoEsta($proyecto), '—', $deteccion['motivo']];

                continue;
            }

            $distintas = array_filter(
                self::BANDERAS,
                fn (string $bandera) => $proyecto->{$bandera} !== $banderas[$bandera],
            );

            if ($distintas !== [] && $this->option('aplicar')) {
                $proyecto->update($banderas);
                $cambios++;
            }

            $filas[] = [
                $proyecto->slug,
                $this->comoEsta($proyecto),
                $distintas === []
                    ? '<fg=green>=</>'
                    : '<fg=yellow>'.$this->comoQuedaria($banderas).'</>',
                $deteccion['motivo'],
            ];
        }

        $this->table(['Proyecto', 'Cargado', 'Detectado', 'Señales'], $filas);

        if (! $this->option('aplicar')) {
            $this->components->info('Solo informe. Con --aplicar se guardan las banderas detectadas.');

            return self::SUCCESS;
        }

        $this->components->info($cambios === 0 ? 'No hubo cambios.' : "Actualizados {$cambios} proyectos.");

        return self::SUCCESS;
    }

    private function comoEsta(Proyecto $proyecto): string
    {
        return $this->comoQuedaria([
            'usa_inertia' => $proyecto->usa_inertia,
            'es_pwa' => $proyecto->es_pwa,
            'tiene_bundle' => $proyecto->tiene_bundle,
        ]);
    }

    /**
     * Tres letras en vez de tres columnas: la tabla entra en una terminal angosta.
     *
     * @param  array{usa_inertia: bool, es_pwa: bool, tiene_bundle: bool}  $banderas
     */
    private function comoQuedaria(array $banderas): string
    {
        return ($banderas['usa_inertia'] ? 'I' : '·')
            .($banderas['es_pwa'] ? 'P' : '·')
            .($banderas['tiene_bundle'] ? 'B' : '·');
    }

    /**
     * @return Collection<int, Proyecto>
     */
    private function proyectos()
    {
        $slug = $this->argument('slug');

        if (filled($slug)) {
            return Proyecto::where('slug', $slug)->get();
        }

        $consulta = Proyecto::query()->ordenados();

        if (! $this->option('inactivos')) {
            $consulta->activos();
        }

        return $consulta->get();
    }
}
