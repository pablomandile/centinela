<?php

namespace App\Sondas;

use App\Enums\EstadoChequeo;

/**
 * Lo que devuelve una sonda: un veredicto con su explicación.
 *
 * El `mensaje` se muestra tal cual en el tablero y viaja en el mail, así que se
 * escribe para que se entienda de un vistazo: "el certificado vence en 9 días",
 * no "cert_expiry_warning".
 *
 * El `detalle` es lo que se vio —cabeceras, cadena de redirects, hash del
 * bundle— y sirve para explicar el veredicto sin volver a pegarle al sitio.
 */
final readonly class Resultado
{
    /**
     * @param  array<string, mixed>  $detalle
     */
    public function __construct(
        public EstadoChequeo $estado,
        public string $mensaje,
        public ?int $codigoHttp = null,
        public ?int $latenciaMs = null,
        public array $detalle = [],
    ) {}

    /**
     * @param  array<string, mixed>  $detalle
     */
    public static function ok(string $mensaje, ?int $codigoHttp = null, ?int $latenciaMs = null, array $detalle = []): self
    {
        return new self(EstadoChequeo::Ok, $mensaje, $codigoHttp, $latenciaMs, $detalle);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    public static function advertencia(string $mensaje, ?int $codigoHttp = null, ?int $latenciaMs = null, array $detalle = []): self
    {
        return new self(EstadoChequeo::Advertencia, $mensaje, $codigoHttp, $latenciaMs, $detalle);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    public static function falla(string $mensaje, ?int $codigoHttp = null, ?int $latenciaMs = null, array $detalle = []): self
    {
        return new self(EstadoChequeo::Falla, $mensaje, $codigoHttp, $latenciaMs, $detalle);
    }
}
