<?php

namespace App\Console\Commands;

use App\Models\Chequeo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Recorta el historial de chequeos.
 *
 * Doce proyectos por seis sondas cada quince minutos son cientos de miles de filas
 * por año. En hosting compartido una tabla que crece sin techo no es un problema de
 * prolijidad: es la cuota de disco.
 *
 * Los incidentes **no** se podan: son pocos y son justamente la historia que se
 * quiere conservar.
 */
class PodarChequeos extends Command
{
    protected $signature = 'centinela:podar {--dias= : Días de historial a conservar}';

    protected $description = 'Borra los chequeos más viejos que el período de retención.';

    public function handle(): int
    {
        /*
         * `?:` no sirve acá: `--dias=0` llega como el string '0', que es falsy, y
         * caería en el default de 90 en silencio en vez de ser rechazado.
         */
        $pedidos = $this->option('dias');

        $dias = $pedidos === null
            ? (int) Config::get('centinela.umbrales.retencion_dias', 90)
            : (int) $pedidos;

        if ($dias < 1) {
            $this->components->error('El período de retención tiene que ser de al menos un día.');

            return self::FAILURE;
        }

        $corte = now()->subDays($dias);

        // Por lotes: un DELETE de cientos de miles de filas en MySQL compartido
        // puede tardar lo suficiente para que el cron del minuto siguiente lo
        // encuentre a medio camino.
        $borrados = 0;

        do {
            $lote = Chequeo::where('ejecutado_at', '<', $corte)->limit(2000)->delete();
            $borrados += $lote;
        } while ($lote > 0);

        $this->components->info("Borrados {$borrados} chequeos anteriores al {$corte->toDateString()}.");

        return self::SUCCESS;
    }
}
