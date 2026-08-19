<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Markdown a HTML, con índice de encabezados.
 *
 * Usa `Str::markdown()`, que ya viene con Laravel (league/commonmark está en el
 * árbol del framework): no hace falta ninguna dependencia nueva para esto.
 */
class MarkdownService
{
    /**
     * @return array{html: string, indice: list<array{nivel: int, titulo: string, ancla: string}>}
     */
    public function renderizar(string $markdown): array
    {
        $indice = $this->indice($markdown);

        return [
            'html' => $this->conAnclas($this->aHtml($markdown), $indice),
            'indice' => $indice,
        ];
    }

    public function aHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            /*
             * `escape` y no `allow`: los documentos son propios, pero un `.md` puede
             * traer HTML pegado de cualquier lado y no hay ninguna razón para
             * ejecutarlo. Escapar cuesta nada y saca del medio toda una familia de
             * problemas.
             */
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Los encabezados de nivel 2 y 3, en orden.
     *
     * Los de nivel 1 no entran: en estos documentos hay uno solo y es el título,
     * que ya se muestra arriba.
     *
     * @return list<array{nivel: int, titulo: string, ancla: string}>
     */
    public function indice(string $markdown): array
    {
        // Los bloques de código se sacan primero: un `# comentario` dentro de un
        // ```bash``` no es un encabezado, y colarlo en el índice manda a un ancla
        // que no existe.
        $sinCodigo = (string) preg_replace('/^```.*?^```/ms', '', $markdown);

        preg_match_all('/^(#{2,3})\s+(.+?)\s*$/m', $sinCodigo, $coincidencias, PREG_SET_ORDER);

        $usadas = [];
        $indice = [];

        foreach ($coincidencias as $coincidencia) {
            $titulo = trim(strip_tags($coincidencia[2]));
            $ancla = $this->anclaLibre($titulo, $usadas);
            $usadas[] = $ancla;

            $indice[] = [
                'nivel' => strlen($coincidencia[1]),
                'titulo' => $titulo,
                'ancla' => $ancla,
            ];
        }

        return $indice;
    }

    /**
     * Le pone `id` a los `<h2>` y `<h3>` para que el índice pueda apuntarles.
     *
     * Se hace acá, sobre el HTML ya generado, en vez de con la extensión de
     * permalinks de CommonMark: esa agrega su propio `<a>` con un símbolo adentro
     * del encabezado, y esto solo necesita el atributo.
     *
     * @param  list<array{nivel: int, titulo: string, ancla: string}>  $indice
     */
    private function conAnclas(string $html, array $indice): string
    {
        foreach ($indice as $entrada) {
            $etiqueta = "h{$entrada['nivel']}";

            // Una por una y en orden: los encabezados salen en el HTML en el mismo
            // orden en que se leyeron del markdown.
            $html = (string) preg_replace(
                '/<'.$etiqueta.'>/',
                '<'.$etiqueta.' id="'.$entrada['ancla'].'">',
                $html,
                1,
            );
        }

        return $html;
    }

    /**
     * @param  list<string>  $usadas
     */
    private function anclaLibre(string $titulo, array $usadas): string
    {
        $base = Str::slug($titulo) ?: 'seccion';
        $ancla = $base;
        $sufijo = 2;

        // Dos secciones con el mismo nombre son comunes ("Notas", "Verificación") y
        // sin desambiguar el índice mandaría las dos al mismo lugar.
        while (in_array($ancla, $usadas, strict: true)) {
            $ancla = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $ancla;
    }
}
