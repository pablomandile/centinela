<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\HacePedidos;

/**
 * ¿`http://` manda a `https://`, o sirve el sitio en plano?
 *
 * Importa más de lo que parece: en http plano no funciona WebCrypto (secretos
 * depende de eso), las PWA no se pueden instalar y la sesión viaja sin cifrar.
 * Y es de las cosas que se rompen solas cuando alguien toca un `.htaccess`.
 */
class SondaRedireccionHttps implements Sonda
{
    use HacePedidos;

    public function tipo(): TipoChequeo
    {
        return TipoChequeo::RedireccionHttps;
    }

    public function aplicaA(Proyecto $proyecto): bool
    {
        // Si la URL canónica ya es http, no hay nada que exigir: el proyecto
        // simplemente no tiene https.
        return $proyecto->esHttps();
    }

    public function ejecutar(Proyecto $proyecto): Resultado
    {
        $url = 'http://'.$proyecto->host().'/';

        // Sin seguir el redirect: lo que se mide es la respuesta cruda, y
        // siguiéndolo el 301 se perdería.
        $pedido = $this->pedir($url, seguirRedirects: false);

        if (! $pedido->contesto()) {
            // No contestar en http no es una falla: puede ser que el puerto 80
            // esté cerrado a propósito, y eso también resuelve el problema.
            return Resultado::advertencia(
                "El puerto 80 no contesta: {$pedido->error}",
                latenciaMs: $pedido->latenciaMs,
                detalle: ['error' => $pedido->error],
            );
        }

        $codigo = (int) $pedido->codigo();
        $destino = $pedido->cabecera('location');
        $detalle = ['codigo' => $codigo, 'location' => $destino];

        if ($codigo >= 300 && $codigo < 400 && filled($destino) && str_starts_with($destino, 'https://')) {
            return Resultado::ok(
                "Redirige {$codigo} a https.",
                codigoHttp: $codigo,
                latenciaMs: $pedido->latenciaMs,
                detalle: $detalle,
            );
        }

        if ($codigo >= 300 && $codigo < 400) {
            return Resultado::advertencia(
                "Redirige {$codigo}, pero no a https: {$destino}",
                codigoHttp: $codigo,
                latenciaMs: $pedido->latenciaMs,
                detalle: $detalle,
            );
        }

        return Resultado::falla(
            "Sirve el sitio en http plano (contesta {$codigo}).",
            codigoHttp: $codigo,
            latenciaMs: $pedido->latenciaMs,
            detalle: $detalle,
        );
    }
}
