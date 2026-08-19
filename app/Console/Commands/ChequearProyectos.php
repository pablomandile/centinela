<?php

namespace App\Console\Commands;

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use App\Models\Chequeo;
use App\Models\Proyecto;
use App\Services\EjecutorDeChequeos;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * El comando que corre el scheduler cada cinco minutos, y el que se usa a mano
 * para ver qué está pasando con un proyecto.
 *
 * No decide cada cuánto se chequea cada cosa: eso lo sabe `Proyecto::toca()`, así
 * que el cron puede ser uno solo y el intervalo se cambia desde la app.
 */
class ChequearProyectos extends Command
{
    protected $signature = 'centinela:chequear
        {--p|proyecto=* : Slug del proyecto (se puede repetir). Por defecto, todos los activos}
        {--t|tipo= : Un solo tipo de chequeo (disponibilidad, certificado, redireccion_https, cache_inertia, cabeceras_pwa, bundle)}
        {--f|forzar : Corre aunque todavía no le toque por intervalo}
        {--inactivos : Incluye los proyectos marcados como inactivos}';

    protected $description = 'Chequea los proyectos y abre o cierra incidentes.';

    public function handle(EjecutorDeChequeos $ejecutor): int
    {
        $tipo = $this->tipo();

        if ($tipo === false) {
            return self::FAILURE;
        }

        $proyectos = $this->proyectos();

        if ($proyectos->isEmpty()) {
            $this->components->warn('No hay proyectos para chequear.');

            return self::SUCCESS;
        }

        $filas = [];

        foreach ($proyectos as $proyecto) {
            $chequeos = $ejecutor->correr($proyecto, $tipo, (bool) $this->option('forzar'));

            foreach ($chequeos as $chequeo) {
                $filas[] = $this->fila($proyecto, $chequeo);
            }
        }

        if ($filas === []) {
            $this->components->info('Nada que correr todavía: a ninguna sonda le toca. Con --forzar corren igual.');

            return self::SUCCESS;
        }

        $this->table(['Proyecto', 'Chequeo', 'Estado', 'ms', 'Qué pasó'], $filas);

        return self::SUCCESS;
    }

    /**
     * El tipo pedido, null si se piden todos, o false si el nombre no existe.
     */
    private function tipo(): TipoChequeo|null|false
    {
        $pedido = $this->option('tipo');

        if (blank($pedido)) {
            return null;
        }

        $tipo = TipoChequeo::tryFrom((string) $pedido);

        if ($tipo === null) {
            $this->components->error("No existe el chequeo «{$pedido}».");
            $this->line('  Hay: '.implode(', ', array_column(TipoChequeo::cases(), 'value')));

            return false;
        }

        return $tipo;
    }

    /**
     * @return Collection<int, Proyecto>
     */
    private function proyectos()
    {
        $slugs = (array) $this->option('proyecto');

        $consulta = Proyecto::query()->ordenados();

        if ($slugs !== []) {
            // Pedido explícito: se corre aunque esté inactivo. Si alguien nombra un
            // proyecto, quiere ver ese proyecto.
            return $consulta->whereIn('slug', $slugs)->get();
        }

        if (! $this->option('inactivos')) {
            $consulta->activos();
        }

        return $consulta->get();
    }

    /**
     * @return array<int, string>
     */
    private function fila(Proyecto $proyecto, Chequeo $chequeo): array
    {
        $color = match ($chequeo->estado) {
            EstadoChequeo::Ok => 'green',
            EstadoChequeo::Advertencia => 'yellow',
            EstadoChequeo::Falla => 'red',
        };

        return [
            $proyecto->slug,
            $chequeo->tipo->value,
            "<fg={$color}>{$chequeo->estado->value}</>",
            (string) ($chequeo->latencia_ms ?? '—'),
            (string) $chequeo->mensaje,
        ];
    }
}
