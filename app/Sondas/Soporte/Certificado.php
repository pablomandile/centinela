<?php

namespace App\Sondas\Soporte;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Lo poco que hace falta saber del certificado de un host.
 */
final readonly class Certificado
{
    public function __construct(
        /*
         * `CarbonInterface` y no `Illuminate\Support\Carbon`: la app usa fechas
         * inmutables (`Date::use(CarbonImmutable::class)` en AppServiceProvider),
         * así que `now()` devuelve una CarbonImmutable, que no es hija de esa
         * clase. Con el tipo estrecho, construir esto tiraba TypeError, el
         * ejecutor lo atrapaba como "falla" y el chequeo quedaba en rojo sin que
         * nada dijera por qué.
         */
        public CarbonInterface $validoHasta,
        public ?string $emisor = null,
        public ?string $nombre = null,
    ) {}

    /**
     * Días que le quedan. Negativo si ya venció.
     */
    public function diasQueLeQuedan(?CarbonInterface $ahora = null): int
    {
        $ahora ??= Carbon::now();

        // `floor` y no `round`: un certificado que vence en 6 días y 20 horas le
        // queda 6, no 7. Redondear para arriba es exactamente lo que no se quiere
        // en un aviso de vencimiento.
        return (int) floor($ahora->diffInDays($this->validoHasta, absolute: false));
    }
}
