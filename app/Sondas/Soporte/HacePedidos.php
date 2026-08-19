<?php

namespace App\Sondas\Soporte;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * El pedido HTTP que hacen todas las sondas, con las dos decisiones que se toman
 * una sola vez acá.
 */
trait HacePedidos
{
    /**
     * @param  array<string, string>  $cabeceras
     */
    protected function pedir(string $url, array $cabeceras = [], bool $seguirRedirects = true): Pedido
    {
        $timeout = (int) Config::get('centinela.umbrales.timeout', 15);
        $maximo = (int) Config::get('centinela.umbrales.redirects', 5);

        /*
         * Seguir los redirects es obligatorio y no una comodidad: la raíz de
         * varios proyectos contesta 302 a /login, y tratar eso como caída es el
         * falso negativo más fácil de cometer en todo Centinela. Es el mismo
         * motivo por el que el skill de deploy insiste con el `curl -L`.
         *
         * `track_redirects` deja la cadena en una cabecera, que es lo que después
         * permite mostrar "302 → /login" en vez de un 200 sin contexto.
         */
        $opciones = $seguirRedirects
            ? ['allow_redirects' => ['max' => $maximo, 'track_redirects' => true, 'strict' => true]]
            : ['allow_redirects' => false];

        $arranque = hrtime(true);

        try {
            $respuesta = Http::withOptions($opciones)
                ->withHeaders($cabeceras)
                ->timeout($timeout)
                // Un User-Agent propio: en los logs del server queda claro quién
                // pegó, y algunos WAF cortan los clientes sin identificar.
                ->withUserAgent('Centinela/1.0 (+https://centinela.pablomandile.com.ar)')
                ->get($url);
        } catch (ConnectionException $e) {
            return new Pedido(null, $this->motivoDelError($e), $this->msDesdeElArranque($arranque));
        } catch (Throwable $e) {
            // Cualquier otra cosa —una URL mal formada, un error de TLS— también
            // es "no contestó", no una excepción que deba tumbar la corrida de
            // los otros once proyectos.
            return new Pedido(null, $this->motivoDelError($e), $this->msDesdeElArranque($arranque));
        }

        return new Pedido(
            respuesta: $respuesta,
            error: null,
            latenciaMs: $this->msDesdeElArranque($arranque),
            redirects: $this->historialDeRedirects($respuesta),
        );
    }

    /**
     * La cadena de redirects que dejó Guzzle en una cabecera.
     *
     * @return list<string>
     */
    private function historialDeRedirects(Response $respuesta): array
    {
        $historial = $respuesta->header('X-Guzzle-Redirect-History');

        if (blank($historial)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $historial))));
    }

    private function msDesdeElArranque(int|float $arranque): int
    {
        return (int) round((hrtime(true) - $arranque) / 1_000_000);
    }

    /**
     * El mensaje de la excepción, recortado.
     *
     * Los de Guzzle traen la URL completa y a veces cabeceras: en el tablero
     * alcanza con la primera línea, y el resto solo hace ruido.
     *
     * Los métodos privados de este trait llevan nombres largos a propósito: un
     * `motivo()` acá lo pisa cualquier clase que use el trait y defina el suyo, sin
     * ningún aviso. Ya pasó.
     */
    private function motivoDelError(Throwable $e): string
    {
        $mensaje = trim(strtok($e->getMessage(), "\n") ?: $e->getMessage());

        return mb_strimwidth($mensaje, 0, 180, '…');
    }
}
