<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\HacePedidos;
use App\Sondas\Soporte\Pedido;
use Illuminate\Support\Facades\Config;

/**
 * ¿Contesta el sitio, a tiempo y con su contenido?
 *
 * Es la única sonda que aplica a todos los proyectos y la que corre seguido.
 */
class SondaDisponibilidad implements Sonda
{
    use HacePedidos;

    public function tipo(): TipoChequeo
    {
        return TipoChequeo::Disponibilidad;
    }

    public function aplicaA(Proyecto $proyecto): bool
    {
        return true;
    }

    public function ejecutar(Proyecto $proyecto): Resultado
    {
        $pedido = $this->pedir($proyecto->url);

        if (! $pedido->contesto()) {
            return Resultado::falla(
                "No contesta: {$pedido->error}",
                latenciaMs: $pedido->latenciaMs,
                detalle: ['error' => $pedido->error],
            );
        }

        $detalle = $this->detalle($pedido);
        $codigo = (int) $pedido->codigo();

        if ($codigo >= 400) {
            return Resultado::falla(
                "Contesta {$codigo}.",
                codigoHttp: $codigo,
                latenciaMs: $pedido->latenciaMs,
                detalle: $detalle,
            );
        }

        // Un 200 no alcanza: una pantalla de error de PHP, un "sitio en
        // mantenimiento" del hosting o un index vacío también contestan 200. La
        // palabra clave es lo que distingue "responde" de "funciona".
        if (filled($proyecto->palabra_clave) && ! str_contains($pedido->cuerpo(), $proyecto->palabra_clave)) {
            return Resultado::falla(
                "Contesta {$codigo} pero no aparece «{$proyecto->palabra_clave}».",
                codigoHttp: $codigo,
                latenciaMs: $pedido->latenciaMs,
                detalle: [...$detalle, 'palabra_clave' => $proyecto->palabra_clave],
            );
        }

        $lento = (int) Config::get('centinela.umbrales.latencia_advertencia', 3000);

        if ($pedido->latenciaMs > $lento) {
            return Resultado::advertencia(
                "Contesta {$codigo}, pero tarda {$pedido->latenciaMs} ms.",
                codigoHttp: $codigo,
                latenciaMs: $pedido->latenciaMs,
                detalle: $detalle,
            );
        }

        return Resultado::ok(
            $this->mensajeOk($codigo, $pedido),
            codigoHttp: $codigo,
            latenciaMs: $pedido->latenciaMs,
            detalle: $detalle,
        );
    }

    private function mensajeOk(int $codigo, Pedido $pedido): string
    {
        // Que la raíz redirija al login es lo normal en la mitad de los
        // proyectos: se dice, para que no parezca que algo cambió.
        if ($pedido->redirects !== []) {
            $ultimo = (string) last($pedido->redirects);
            $ruta = parse_url($ultimo, PHP_URL_PATH) ?: $ultimo;

            return "Contesta {$codigo} tras redirigir a {$ruta}.";
        }

        return "Contesta {$codigo}.";
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(Pedido $pedido): array
    {
        return [
            'redirects' => $pedido->redirects,
            'content_type' => $pedido->cabecera('content-type'),
            'bytes' => strlen($pedido->cuerpo()),
            'servidor' => $pedido->cabecera('server'),
        ];
    }
}
