<?php

namespace App\Services;

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use App\Mail\AvisoDeIncidente;
use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use App\Sondas\RegistroDeSondas;
use App\Sondas\Resultado;
use App\Sondas\Sonda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Corre las sondas de un proyecto, guarda el resultado y decide si eso es un
 * incidente.
 *
 * Las tres reglas que hacen que los avisos sirvan:
 *
 * 1. **Un incidente se abre al segundo fallo seguido.** Con uno, cualquier hipo de
 *    red manda un mail a las tres de la mañana y en una semana los avisos se
 *    ignoran solos.
 * 2. **Como máximo un incidente abierto por proyecto y tipo.** La base no lo puede
 *    garantizar —MySQL admite múltiples NULL en un índice único—, así que se
 *    resuelve acá, en una transacción con `lockForUpdate`.
 * 3. **Solo `falla` abre incidentes.** Una advertencia —un certificado a 15 días,
 *    un sitio lento— se ve en el tablero y no despierta a nadie.
 */
class EjecutorDeChequeos
{
    public function __construct(private readonly RegistroDeSondas $sondas) {}

    /**
     * @return Collection<int, Chequeo>
     */
    public function correr(Proyecto $proyecto, ?TipoChequeo $tipo = null, bool $forzar = false): Collection
    {
        $chequeos = collect();

        foreach ($this->sondas->para($proyecto) as $sonda) {
            if ($tipo !== null && $sonda->tipo() !== $tipo) {
                continue;
            }

            if (! $forzar && ! $proyecto->toca($sonda->tipo())) {
                continue;
            }

            $chequeos->push($this->correrUna($proyecto, $sonda));
        }

        return $chequeos;
    }

    private function correrUna(Proyecto $proyecto, Sonda $sonda): Chequeo
    {
        $resultado = $this->resultadoDe($proyecto, $sonda);

        $chequeo = $proyecto->chequeos()->create([
            'tipo' => $sonda->tipo(),
            'estado' => $resultado->estado,
            'codigo_http' => $resultado->codigoHttp,
            'latencia_ms' => $resultado->latenciaMs,
            'detalle' => $resultado->detalle,
            'mensaje' => $resultado->mensaje,
            'ejecutado_at' => now(),
        ]);

        $this->resolverIncidente($proyecto, $chequeo);

        return $chequeo;
    }

    /**
     * Una sonda que explota no puede tumbar la corrida de los otros once
     * proyectos: se registra como falla y se sigue.
     */
    private function resultadoDe(Proyecto $proyecto, Sonda $sonda): Resultado
    {
        try {
            return $sonda->ejecutar($proyecto);
        } catch (Throwable $e) {
            Log::warning('Falló una sonda.', [
                'proyecto' => $proyecto->slug,
                'sonda' => $sonda->tipo()->value,
                'excepcion' => $e->getMessage(),
            ]);

            return Resultado::falla('El chequeo no se pudo completar: '.$e->getMessage());
        }
    }

    /**
     * Abre, actualiza o cierra el incidente del par (proyecto, tipo).
     *
     * El mail se manda **después** de la transacción, no adentro: un SMTP que
     * tarda mantendría la fila bloqueada, y un SMTP que falla haría rollback de un
     * incidente que sí ocurrió.
     */
    private function resolverIncidente(Proyecto $proyecto, Chequeo $chequeo): void
    {
        /** @var array{incidente: Incidente, recuperado: bool}|null $aviso */
        $aviso = DB::transaction(function () use ($proyecto, $chequeo) {
            $abierto = $proyecto->incidentes()
                ->where('tipo', $chequeo->tipo)
                ->whereNull('cerrado_at')
                ->lockForUpdate()
                ->first();

            if ($chequeo->estado->esFalla()) {
                if ($abierto !== null) {
                    // Sigue caído: se actualiza el motivo y no se avisa de nuevo.
                    // Un mail por cada chequeo fallido serían 96 mails por día.
                    $abierto->update(['ultimo_mensaje' => $chequeo->mensaje]);

                    return null;
                }

                if ($this->fallosSeguidos($proyecto, $chequeo->tipo) < $this->umbral()) {
                    return null;
                }

                $incidente = $proyecto->incidentes()->create([
                    'tipo' => $chequeo->tipo,
                    'abierto_at' => $chequeo->ejecutado_at,
                    'ultimo_mensaje' => $chequeo->mensaje,
                ]);

                return ['incidente' => $incidente, 'recuperado' => false];
            }

            if ($abierto === null) {
                return null;
            }

            $abierto->update(['cerrado_at' => $chequeo->ejecutado_at]);

            // Solo se avisa la recuperación si se había avisado la caída: si el
            // mail de caída no salió, el de "ya está" no se entiende.
            return $abierto->avisado_at === null
                ? null
                : ['incidente' => $abierto, 'recuperado' => true];
        });

        if ($aviso !== null) {
            $this->avisar($aviso['incidente'], $aviso['recuperado']);
        }
    }

    /**
     * Cuántos de los últimos chequeos de ese tipo fallaron, de atrás para
     * adelante.
     *
     * Se lee del historial en vez de llevar un contador aparte: la tabla de
     * chequeos ya es la fuente de verdad, y un contador desincronizado daría
     * avisos que no se corresponden con nada de lo que se ve en el tablero.
     */
    private function fallosSeguidos(Proyecto $proyecto, TipoChequeo $tipo): int
    {
        $ultimos = $proyecto->chequeos()
            ->where('tipo', $tipo)
            ->orderByDesc('ejecutado_at')
            // El id como desempate: dos chequeos pueden caer en el mismo segundo.
            ->orderByDesc('id')
            ->limit($this->umbral())
            ->pluck('estado');

        $seguidos = 0;

        foreach ($ultimos as $estado) {
            if ($estado !== EstadoChequeo::Falla) {
                break;
            }

            $seguidos++;
        }

        return $seguidos;
    }

    private function umbral(): int
    {
        return max(1, (int) Config::get('centinela.umbrales.fallos_para_incidente', 2));
    }

    /**
     * Manda el mail y anota que salió.
     *
     * Un SMTP caído no puede romper la corrida: se registra el problema y el
     * incidente queda sin avisar, así el próximo chequeo lo vuelve a intentar.
     */
    private function avisar(Incidente $incidente, bool $recuperado): void
    {
        $destino = Config::get('centinela.avisos_a');

        if (blank($destino)) {
            return;
        }

        try {
            Mail::to($destino)->send(new AvisoDeIncidente($incidente->load('proyecto'), $recuperado));
        } catch (Throwable $e) {
            Log::warning('No se pudo mandar el aviso de incidente.', [
                'incidente' => $incidente->id,
                'excepcion' => $e->getMessage(),
            ]);

            return;
        }

        $incidente->update($recuperado ? ['avisado_cierre_at' => now()] : ['avisado_at' => now()]);
    }
}
