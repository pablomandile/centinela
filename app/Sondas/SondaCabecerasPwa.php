<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\HacePedidos;
use App\Sondas\Soporte\Pedido;

/**
 * El skill `adaptar-a-pwa`, convertido en chequeo.
 *
 * Dos cosas se miran acá, y las dos ya pasaron:
 *
 * 1. **Que el manifest y el service worker existan y salgan sin caché.** El CDN de
 *    Hostinger cachea los estáticos siete días. `sw.js` y el manifest tienen URL
 *    fija, así que un service worker nuevo puede quedar sin llegar a nadie durante
 *    una semana —y como el service worker controla las demás cachés, congela todo
 *    lo demás—. El arreglo es `no-cache` en el `.htaccess`; esto lo verifica.
 * 2. **Que los íconos estén.** Sin 192 y 512 Chrome no ofrece instalar, y el
 *    síntoma es que "no aparece el botón", sin ningún error.
 */
class SondaCabecerasPwa implements Sonda
{
    use HacePedidos;

    public function tipo(): TipoChequeo
    {
        return TipoChequeo::CabecerasPwa;
    }

    public function aplicaA(Proyecto $proyecto): bool
    {
        return $proyecto->es_pwa;
    }

    public function ejecutar(Proyecto $proyecto): Resultado
    {
        [$rutaManifest, $manifest] = $this->manifest($proyecto);

        if (! $this->esUnManifest($manifest)) {
            return Resultado::falla(
                'No hay manifest válido: sin él la app no se puede instalar.',
                codigoHttp: $manifest?->codigo(),
                latenciaMs: $manifest?->latenciaMs,
                detalle: ['buscado' => ['/manifest.webmanifest', '/manifest.json']],
            );
        }

        $urlSw = $proyecto->urlDe('/sw.js');
        $sw = $this->pedir($urlSw);

        $detalle = [
            'manifest' => [
                'ruta' => $rutaManifest,
                'content_type' => $manifest->cabecera('content-type'),
                'cache_control' => $manifest->cabecera('cache-control'),
                'cdn' => $manifest->cabecera('x-hcdn-cache-status'),
                'age' => $manifest->cabecera('age'),
            ],
            'sw' => [
                'codigo' => $sw->codigo(),
                'cache_control' => $sw->cabecera('cache-control'),
                'cdn' => $sw->cabecera('x-hcdn-cache-status'),
                'age' => $sw->cabecera('age'),
            ],
        ];

        if ($sw->codigo() !== 200) {
            return Resultado::falla(
                'El manifest está pero /sw.js contesta '.($sw->codigo() ?? 'nada').'. Sin service worker con handler de fetch, Chrome no ofrece instalar.',
                codigoHttp: $sw->codigo(),
                latenciaMs: $sw->latenciaMs,
                detalle: $detalle,
            );
        }

        [$iconos, $faltantes] = $this->iconos($proyecto, $manifest->cuerpo());
        $detalle['iconos'] = $iconos;

        if ($faltantes !== []) {
            return Resultado::falla(
                'Íconos que el manifest declara y el server no sirve: '.implode(', ', $faltantes),
                codigoHttp: 200,
                latenciaMs: $manifest->latenciaMs,
                detalle: $detalle,
            );
        }

        $avisos = array_filter([
            $this->avisoDeCache('El manifest', $manifest, $proyecto->urlDe($rutaManifest)),
            $this->avisoDeCache('El service worker', $sw, $urlSw),
            $this->avisoDeMime($manifest),
            $this->avisoDeTamanos($iconos),
        ]);

        if ($avisos !== []) {
            return Resultado::advertencia(
                implode(' ', $avisos),
                codigoHttp: 200,
                latenciaMs: $manifest->latenciaMs,
                detalle: $detalle,
            );
        }

        return Resultado::ok(
            'Manifest e íconos en su lugar, y ninguno de los dos queda cacheado.',
            codigoHttp: 200,
            latenciaMs: $manifest->latenciaMs,
            detalle: $detalle,
        );
    }

    /**
     * El manifest, probando los dos nombres que se usan.
     *
     * @return array{0: string, 1: Pedido|null}
     */
    private function manifest(Proyecto $proyecto): array
    {
        $ultimo = null;

        foreach (['/manifest.webmanifest', '/manifest.json'] as $ruta) {
            $ultimo = $this->pedir($proyecto->urlDe($ruta));

            if ($this->esUnManifest($ultimo)) {
                return [$ruta, $ultimo];
            }
        }

        // Ninguno de los dos: se devuelve el último intento para poder contar qué
        // contestó, que no es lo mismo que "no se pudo preguntar".
        return ['/manifest.webmanifest', $ultimo];
    }

    /**
     * 200 no alcanza para dar por bueno un manifest.
     *
     * Varios hostings —y cualquier SPA con fallback a index.html— contestan 200 con
     * el HTML del sitio para una ruta que no existe. Pasó con localia: pedirle
     * `/manifest.webmanifest` devuelve la home entera con 200, y sin mirar el
     * cuerpo la detección la daba por PWA.
     */
    private function esUnManifest(?Pedido $pedido): bool
    {
        if ($pedido === null || $pedido->codigo() !== 200) {
            return false;
        }

        $contenido = json_decode($pedido->cuerpo(), true);

        return is_array($contenido)
            && (array_key_exists('name', $contenido) || array_key_exists('icons', $contenido));
    }

    /**
     * Los íconos que declara el manifest, y cuáles de ellos no responden.
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function iconos(Proyecto $proyecto, string $cuerpoDelManifest): array
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($cuerpoDelManifest, true) ?: [];

        /** @var list<array<string, string>> $declarados */
        $declarados = $manifest['icons'] ?? [];

        $tamanos = [];
        $faltantes = [];

        foreach ($declarados as $icono) {
            $src = $icono['src'] ?? null;
            $tamanos[] = $icono['sizes'] ?? '?';

            if (! is_string($src)) {
                continue;
            }

            $url = str_starts_with($src, 'http') ? $src : $proyecto->urlDe($src);

            if ($this->pedir($url)->codigo() !== 200) {
                $faltantes[] = $src;
            }
        }

        return [['tamanos' => array_values(array_unique($tamanos))], array_values(array_unique($faltantes))];
    }

    /**
     * El CDN cachea siete días lo que no diga lo contrario.
     *
     * Con una vuelta más, que es la que evita mandar a arreglar algo ya
     * arreglado: la cabecera que se lee puede venir de una copia que el borde
     * guardó **antes** de que el sitio se corrigiera, y entonces describe cómo
     * estaba el sitio hace `Age` segundos, no cómo está ahora. Las dos
     * situaciones se ven idénticas desde afuera y se resuelven distinto —una es
     * tocar el `.htaccess`, la otra es purgar o esperar—, así que cuando la
     * respuesta salió de una caché se le pregunta al origen.
     *
     * Pasó de verdad con mantreando: el `.htaccess` ya estaba corregido y un
     * nodo de los cuatro seguía sirviendo la copia de seis días antes, así que
     * el aviso aparecía y desaparecía según a qué nodo cayera el chequeo.
     */
    private function avisoDeCache(string $qué, Pedido $pedido, string $url): ?string
    {
        $cache = (string) $pedido->cabecera('cache-control');

        if ($this->prohibeGuardar($cache)) {
            return null;
        }

        if ($this->salioDeUnaCache($pedido) && $this->prohibeGuardar($this->cacheDelOrigen($url))) {
            return "{$qué} ya sale bien del origen, pero un nodo del CDN todavía entrega una copia vieja («{$cache}»)"
                .$this->cuantoLeFalta($pedido).'. No hay nada que tocar en el sitio: se purga el CDN desde hPanel o se espera.';
        }

        return "{$qué} sale con «".($cache ?: 'sin Cache-Control').'»: el CDN lo va a servir viejo por días.';
    }

    private function prohibeGuardar(string $cache): bool
    {
        return str_contains($cache, 'no-cache')
            || str_contains($cache, 'no-store')
            || str_contains($cache, 'max-age=0');
    }

    /**
     * ¿La contestó el origen, o la sirvió una caché compartida?
     *
     * `Age` es la señal del RFC y vale para cualquier CDN; el header propio de
     * Hostinger se mira además porque es el que aparece en esta cuenta y es
     * explícito. Sin ninguno de los dos se asume que contestó el origen, que es
     * lo conservador: se emite el aviso de siempre.
     */
    private function salioDeUnaCache(Pedido $pedido): bool
    {
        return mb_strtoupper((string) $pedido->cabecera('x-hcdn-cache-status')) === 'HIT'
            || (int) $pedido->cabecera('age') > 0;
    }

    /**
     * Qué contesta el origen, salteando al borde.
     *
     * Hace falta una URL que la caché no pueda tener guardada: medido contra
     * Hostinger, un `Cache-Control: no-cache` en el **pedido** no alcanza, el
     * nodo sigue devolviendo su copia igual. El query string cambia en cada
     * corrida a propósito — uno fijo terminaría cacheado él también y volvería a
     * mentir, que es exactamente el problema que se está resolviendo.
     *
     * Es un pedido de más, y por eso sale solo cuando la cabecera ya vino mal:
     * en un sitio sano esto no se ejecuta nunca. Importa por lo que dice
     * `CLAUDE.md` sobre las ráfagas en hosting compartido.
     */
    private function cacheDelOrigen(string $url): string
    {
        $separador = str_contains($url, '?') ? '&' : '?';

        return (string) $this->pedir($url.$separador.'centinela='.now()->getTimestamp())
            ->cabecera('cache-control');
    }

    /**
     * Cuánto le queda a la copia del borde antes de vencer sola.
     *
     * Sale del `max-age` que declara esa misma copia menos su `Age`. Si falta
     * cualquiera de los dos se devuelve vacío: es preferible un aviso sin plazo
     * a uno con un plazo inventado.
     */
    private function cuantoLeFalta(Pedido $pedido): string
    {
        if (preg_match('/max-age=(\d+)/', (string) $pedido->cabecera('cache-control'), $coincidencia) !== 1) {
            return '';
        }

        $faltan = (int) $coincidencia[1] - (int) $pedido->cabecera('age');

        return $faltan > 0 ? ', que vence en '.$this->enPalabras($faltan) : '';
    }

    private function enPalabras(int $segundos): string
    {
        $horas = (int) round($segundos / 3600);

        if ($horas < 1) {
            return 'menos de una hora';
        }

        if ($horas < 48) {
            return $horas === 1 ? 'una hora' : "{$horas} horas";
        }

        return (int) round($segundos / 86400).' días';
    }

    private function avisoDeMime(Pedido $manifest): ?string
    {
        $tipo = (string) $manifest->cabecera('content-type');

        if (str_contains($tipo, 'manifest+json')) {
            return null;
        }

        // Chrome lo tolera —se instala igual, comprobado— pero incumple la spec y
        // otros navegadores lo pueden rechazar.
        return "El manifest se sirve como «{$tipo}» en vez de application/manifest+json.";
    }

    /**
     * @param  array<string, mixed>  $iconos
     */
    private function avisoDeTamanos(array $iconos): ?string
    {
        /** @var list<string> $tamanos */
        $tamanos = $iconos['tamanos'] ?? [];

        $faltan = array_values(array_diff(['192x192', '512x512'], $tamanos));

        return $faltan === []
            ? null
            : 'El manifest no declara íconos de '.implode(' ni ', $faltan).': Chrome no va a ofrecer instalar.';
    }
}
