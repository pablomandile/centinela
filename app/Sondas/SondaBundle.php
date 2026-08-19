<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\HacePedidos;

/**
 * ¿El JS que pide la página existe de verdad?
 *
 * Es el chequeo del deploy a medio camino. El flujo de esta cuenta compila local
 * y copia `public/build` por scp: si el swap quedó por la mitad —o si se subió el
 * HTML nuevo con el build viejo— la página carga y el navegador pide un
 * `app-<hash>.js` que ya no está. La pantalla queda en blanco y el server
 * contesta 200 igual, así que la sonda de disponibilidad no lo ve.
 *
 * De paso guarda el nombre del asset, que lleva hash de contenido: cuando cambia,
 * hubo un deploy. Es la forma más barata de saber cuándo se desplegó algo.
 */
class SondaBundle implements Sonda
{
    use HacePedidos;

    public function tipo(): TipoChequeo
    {
        return TipoChequeo::Bundle;
    }

    public function aplicaA(Proyecto $proyecto): bool
    {
        return $proyecto->tiene_bundle;
    }

    public function ejecutar(Proyecto $proyecto): Resultado
    {
        $pagina = $this->pedir($proyecto->url);

        if (! $pagina->contesto()) {
            return Resultado::falla(
                "No se pudo leer la página: {$pagina->error}",
                latenciaMs: $pagina->latenciaMs,
                detalle: ['error' => $pagina->error],
            );
        }

        $asset = $this->assetPrincipal($pagina->cuerpo());

        if ($asset === null) {
            // Puede ser que el perfil esté mal cargado —un sitio sin build
            // marcado como si tuviera— y por eso es advertencia y no falla.
            return Resultado::advertencia(
                'La página no referencia ningún archivo de /build/. ¿El perfil es el correcto?',
                codigoHttp: $pagina->codigo(),
                latenciaMs: $pagina->latenciaMs,
            );
        }

        $urlAsset = str_starts_with($asset, 'http') ? $asset : $proyecto->urlDe($asset);
        $pedido = $this->pedir($urlAsset);
        $detalle = ['asset' => basename($asset), 'url' => $urlAsset];

        if (! $pedido->contesto()) {
            return Resultado::falla(
                "El bundle no se pudo pedir: {$pedido->error}",
                latenciaMs: $pedido->latenciaMs,
                detalle: [...$detalle, 'error' => $pedido->error],
            );
        }

        $codigo = (int) $pedido->codigo();

        if ($codigo !== 200) {
            return Resultado::falla(
                "La página pide {$detalle['asset']} y el server contesta {$codigo}. Deploy a medio camino.",
                codigoHttp: $codigo,
                latenciaMs: $pedido->latenciaMs,
                detalle: $detalle,
            );
        }

        return Resultado::ok(
            "El bundle {$detalle['asset']} responde 200.",
            codigoHttp: $codigo,
            latenciaMs: $pedido->latenciaMs,
            detalle: [...$detalle, 'bytes' => strlen($pedido->cuerpo())],
        );
    }

    /**
     * El primer JS de `/build/` que referencia la página, prefiriendo el `app-*`.
     *
     * Se lee del HTML y no del `manifest.json` del build: ese archivo no siempre
     * es público, y lo que importa es justamente lo que el navegador va a pedir.
     */
    private function assetPrincipal(string $html): ?string
    {
        preg_match_all('#(?:/|https?://[^"\']+/)build/assets/[A-Za-z0-9_.\-]+\.js#', $html, $coincidencias);

        $assets = array_unique($coincidencias[0]);

        if ($assets === []) {
            return null;
        }

        foreach ($assets as $asset) {
            if (str_contains(basename($asset), 'app-')) {
                return $asset;
            }
        }

        return (string) reset($assets);
    }
}
