<?php

namespace App\Sondas\Soporte;

use Illuminate\Http\Client\Response;

/**
 * Un pedido HTTP ya hecho: la respuesta o el motivo por el que no hubo ninguna.
 *
 * Existe porque a una sonda le importan las dos cosas por igual. Un sitio que no
 * resuelve DNS no tira un 500: no contesta nada, y eso es justamente el
 * resultado. Envolverlo evita que cada sonda repita el try/catch.
 */
final readonly class Pedido
{
    /**
     * @param  list<string>  $redirects
     */
    public function __construct(
        public ?Response $respuesta,
        public ?string $error,
        public int $latenciaMs,
        public array $redirects = [],
    ) {}

    public function contesto(): bool
    {
        return $this->respuesta !== null;
    }

    public function codigo(): ?int
    {
        return $this->respuesta?->status();
    }

    /**
     * Una cabecera, en minúsculas y como string simple.
     */
    public function cabecera(string $nombre): ?string
    {
        $valor = $this->respuesta?->header($nombre);

        return blank($valor) ? null : $valor;
    }

    public function cuerpo(): string
    {
        return $this->respuesta?->body() ?? '';
    }
}
