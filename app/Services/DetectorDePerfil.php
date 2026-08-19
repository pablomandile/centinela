<?php

namespace App\Services;

use App\Models\Proyecto;
use App\Sondas\Soporte\HacePedidos;
use App\Sondas\Soporte\Pedido;

/**
 * Averigua qué sabe hacer un sitio, pegándole.
 *
 * Las banderas del proyecto deciden qué sondas se le corren, así que tenerlas mal
 * es la forma más fácil de llenar el tablero de rojo inútil —o peor, de no auditar
 * algo que sí corresponde—. Preguntárselo al sitio es más confiable que recordarlo,
 * sobre todo en proyectos que no se tocan desde hace un año.
 *
 * Tres preguntas, y las tres tienen su trampa, encontrada corriendo esto contra los
 * doce sitios reales:
 *
 * 1. **Inertia**: la señal es `X-Inertia-Location` en el 409, no
 *    `X-Inertia-Version` —ese header en la *respuesta* lo agregó inertia-laravel 3
 *    y la mitad de los proyectos sigue en 2—.
 * 2. **PWA**: un 200 no alcanza. Hay hostings que contestan el HTML de la home con
 *    200 para una ruta que no existe, y así localia figuraba como instalable.
 * 3. **Bundle**: se busca en el HTML lo que el navegador realmente va a pedir.
 */
class DetectorDePerfil
{
    use HacePedidos;

    /**
     * @return array{banderas: array{usa_inertia: bool, es_pwa: bool, tiene_bundle: bool}|null, motivo: string}
     */
    public function detectar(Proyecto $proyecto): array
    {
        $pagina = $this->pedir($proyecto->url);

        if (! $pagina->contesto()) {
            return ['banderas' => null, 'motivo' => "No contesta: {$pagina->error}"];
        }

        $banderas = [
            'usa_inertia' => $this->usaInertia($proyecto, $pagina),
            'es_pwa' => $this->esPwa($proyecto),
            'tiene_bundle' => (bool) preg_match('#/build/assets/[A-Za-z0-9_.\-]+\.js#', $pagina->cuerpo()),
        ];

        return ['banderas' => $banderas, 'motivo' => $this->motivoLegible($banderas)];
    }

    private function usaInertia(Proyecto $proyecto, Pedido $pagina): bool
    {
        $xhr = $this->pedir($proyecto->url, ['X-Inertia' => 'true']);

        if ($xhr->contesto()) {
            foreach (['x-inertia-location', 'x-inertia-version', 'x-inertia'] as $cabecera) {
                if ($xhr->cabecera($cabecera) !== null) {
                    return true;
                }
            }
        }

        // Último recurso: así arranca cualquier app de Inertia, de la versión que
        // sea. Sirve para un sitio detrás de un proxy que se coma los headers.
        return str_contains($pagina->cuerpo(), 'data-page=');
    }

    private function esPwa(Proyecto $proyecto): bool
    {
        foreach (['/manifest.webmanifest', '/manifest.json'] as $ruta) {
            $pedido = $this->pedir($proyecto->urlDe($ruta));

            if ($pedido->codigo() !== 200) {
                continue;
            }

            $contenido = json_decode($pedido->cuerpo(), true);

            if (is_array($contenido) && (array_key_exists('name', $contenido) || array_key_exists('icons', $contenido))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{usa_inertia: bool, es_pwa: bool, tiene_bundle: bool}  $banderas
     */
    private function motivoLegible(array $banderas): string
    {
        return implode(', ', [
            $banderas['usa_inertia'] ? 'contesta como Inertia' : 'sin señales de Inertia',
            $banderas['es_pwa'] ? 'manifest válido' : 'sin manifest',
            $banderas['tiene_bundle'] ? 'referencia /build/' : 'sin /build/',
        ]);
    }
}
