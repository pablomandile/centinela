<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\HacePedidos;
use App\Sondas\Soporte\Pedido;

/**
 * El skill `inertia-json-crudo`, convertido en chequeo.
 *
 * Una URL de Inertia devuelve dos cuerpos según el header `X-Inertia`: el HTML de
 * arranque para una navegación, el JSON de la página para un XHR. Si el navegador
 * puede **guardar** la respuesta JSON, al restaurar una pestaña descartada la
 * reusa sin revalidar y el usuario ve el JSON crudo en pantalla. F5 lo arregla, y
 * por eso pasa meses sin que nadie lo reporte.
 *
 * Lo que hace falta es `no-store` —no `no-cache`, que permite guardar— y **solo**
 * en la respuesta XHR: en el HTML mataría el back/forward cache de Chrome, que no
 * da ningún síntoma que lo delate.
 *
 * Hasta ahora esto se verificaba a mano, con tres curl, proyecto por proyecto.
 */
class SondaCacheInertia implements Sonda
{
    use HacePedidos;

    public function tipo(): TipoChequeo
    {
        return TipoChequeo::CacheInertia;
    }

    public function aplicaA(Proyecto $proyecto): bool
    {
        return $proyecto->usa_inertia;
    }

    public function ejecutar(Proyecto $proyecto): Resultado
    {
        // Un XHR sin versión: Inertia contesta 409 y de ahí sale todo lo demás.
        $tanteo = $this->pedir($proyecto->url, ['X-Inertia' => 'true']);

        if (! $tanteo->contesto()) {
            return Resultado::falla(
                "No contesta: {$tanteo->error}",
                latenciaMs: $tanteo->latenciaMs,
                detalle: ['error' => $tanteo->error],
            );
        }

        if (! $this->pareceInertia($tanteo)) {
            // Es advertencia y no falla porque lo más probable es que la bandera
            // `usa_inertia` del proyecto esté mal: la corrige
            // `centinela:detectar-perfil`.
            return Resultado::advertencia(
                'El sitio no contesta como Inertia. ¿Sigue usándolo? Probá centinela:detectar-perfil.',
                codigoHttp: $tanteo->codigo(),
                latenciaMs: $tanteo->latenciaMs,
            );
        }

        $version = $this->version($proyecto, $tanteo);

        if ($version === null) {
            return Resultado::advertencia(
                'No se pudo averiguar la versión de asset, así que el XHR contestaría 409 y no se puede evaluar la caché.',
                codigoHttp: $tanteo->codigo(),
                latenciaMs: $tanteo->latenciaMs,
            );
        }

        $xhr = $this->pedir($proyecto->url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $html = $this->pedir($proyecto->url);

        $cacheXhr = (string) $xhr->cabecera('cache-control');
        $cacheHtml = (string) $html->cabecera('cache-control');

        $detalle = [
            'version' => $version,
            'xhr' => [
                'codigo' => $xhr->codigo(),
                'content_type' => $xhr->cabecera('content-type'),
                'cache_control' => $cacheXhr ?: null,
                'vary' => $xhr->cabecera('vary'),
            ],
            'html' => [
                'codigo' => $html->codigo(),
                'content_type' => $html->cabecera('content-type'),
                'cache_control' => $cacheHtml ?: null,
                'vary' => $html->cabecera('vary'),
            ],
            'vary_con_brotli' => $this->varyConBrotli($proyecto),
        ];

        if (! str_contains((string) $xhr->cabecera('content-type'), 'json')) {
            return Resultado::advertencia(
                'El XHR de Inertia no devolvió JSON: no se pudo evaluar la caché.',
                codigoHttp: $xhr->codigo(),
                latenciaMs: $xhr->latenciaMs,
                detalle: $detalle,
            );
        }

        if (! str_contains($cacheXhr, 'no-store')) {
            return Resultado::falla(
                'El JSON de Inertia se puede guardar ('.($cacheXhr ?: 'sin Cache-Control').'). Al restaurar una pestaña va a aparecer el JSON crudo en pantalla.',
                codigoHttp: $xhr->codigo(),
                latenciaMs: $xhr->latenciaMs,
                detalle: $detalle,
            );
        }

        if (str_contains($cacheHtml, 'no-store')) {
            return Resultado::advertencia(
                'El JSON está bien, pero el HTML también sale con no-store: eso desactiva el back/forward cache y cada "atrás" es una ida completa a la red.',
                codigoHttp: $html->codigo(),
                latenciaMs: $xhr->latenciaMs,
                detalle: $detalle,
            );
        }

        return Resultado::ok(
            'El JSON no se puede guardar y el HTML sí. Como corresponde.',
            codigoHttp: $xhr->codigo(),
            latenciaMs: $xhr->latenciaMs,
            detalle: $detalle,
        );
    }

    /**
     * ¿Del otro lado hay Inertia?
     *
     * Cualquiera de los dos headers alcanza. **`X-Inertia-Location` es el que
     * importa**: `X-Inertia-Version` en la respuesta lo agregó inertia-laravel 3,
     * y la mitad de los proyectos de la cuenta siguen en 2. Confiar solo en él
     * daba "no parece Inertia" en sitios que sí lo son.
     */
    private function pareceInertia(Pedido $tanteo): bool
    {
        return $tanteo->cabecera('x-inertia-location') !== null
            || $tanteo->cabecera('x-inertia-version') !== null
            || $tanteo->cabecera('x-inertia') !== null;
    }

    /**
     * La versión del asset, sin la cual Inertia contesta 409 en vez de la página.
     */
    private function version(Proyecto $proyecto, Pedido $tanteo): ?string
    {
        // inertia-laravel 3 la manda en la respuesta.
        if (filled($cabecera = $tanteo->cabecera('x-inertia-version'))) {
            return $cabecera;
        }

        // En 2 hay que sacarla del `data-page` del HTML, donde viaja con las
        // comillas escapadas como entidades.
        $html = $this->pedir($proyecto->url);

        if (! $html->contesto()) {
            return null;
        }

        $cuerpo = html_entity_decode($html->cuerpo(), ENT_QUOTES | ENT_HTML5);

        return preg_match('/"version"\s*:\s*"([^"]+)"/', $cuerpo, $coincidencia) === 1
            ? $coincidencia[1]
            : null;
    }

    /**
     * ¿Sobrevive el `Vary` cuando el CDN comprime con brotli?
     *
     * No cambia el veredicto —el `no-store` es lo que arregla el bug y el CDN no
     * se puede configurar— pero es el dato que explica por qué hace falta: con
     * brotli, que es lo que pide cualquier navegador real, el CDN de Hostinger
     * borra el `Vary` y las dos respuestas comparten clave de caché.
     */
    private function varyConBrotli(Proyecto $proyecto): ?string
    {
        $pedido = $this->pedir($proyecto->url, ['Accept-Encoding' => 'gzip, deflate, br, zstd']);

        if (! $pedido->contesto()) {
            return null;
        }

        return $pedido->cabecera('vary') ?? 'ausente';
    }
}
