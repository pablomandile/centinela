<?php

use App\Services\MarkdownService;

beforeEach(function () {
    $this->markdown = new MarkdownService;
});

it('arma el índice con los encabezados de nivel 2 y 3', function () {
    // El h1 no entra: en estos documentos hay uno solo y es el título, que ya se
    // muestra arriba de la pantalla.
    $indice = $this->markdown->indice("# Título\n\n## Uno\n\n### Uno punto uno\n\n## Dos");

    expect($indice)->toHaveCount(3)
        ->and($indice[0])->toBe(['nivel' => 2, 'titulo' => 'Uno', 'ancla' => 'uno'])
        ->and($indice[1]['nivel'])->toBe(3);
});

it('ignora los encabezados que están dentro de un bloque de código', function () {
    /*
     * Un `# comentario` adentro de un ```bash``` no es un encabezado: colarlo en el
     * índice manda a un ancla que no existe, y el enlace no hace nada.
     */
    $markdown = <<<'MD'
        ## De verdad

        ```bash
        # esto es un comentario de shell
        ls -la
        ```

        ## También de verdad
        MD;

    $indice = $this->markdown->indice($markdown);

    expect(array_column($indice, 'titulo'))->toBe(['De verdad', 'También de verdad']);
});

it('desambigua dos secciones con el mismo nombre', function () {
    // "Notas" y "Verificación" aparecen dos veces en varios de estos documentos.
    $indice = $this->markdown->indice("## Notas\n\ntexto\n\n## Notas\n\notro texto");

    expect(array_column($indice, 'ancla'))->toBe(['notas', 'notas-2']);
});

it('le pone id a cada encabezado del HTML, en orden', function () {
    $renderizado = $this->markdown->renderizar("## Primera\n\ntexto\n\n## Segunda");

    expect($renderizado['html'])->toContain('<h2 id="primera">Primera</h2>')
        ->and($renderizado['html'])->toContain('<h2 id="segunda">Segunda</h2>');
});

it('escapa el HTML que venga en el markdown', function () {
    // Los documentos son propios, pero un .md puede traer HTML pegado de cualquier
    // lado y no hay ninguna razón para ejecutarlo.
    $html = $this->markdown->aHtml('Hola <script>alert(1)</script>');

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('renderiza tablas y bloques de código, que es de lo que están hechos estos documentos', function () {
    $html = $this->markdown->aHtml("| a | b |\n|---|---|\n| 1 | 2 |\n\n```php\necho 1;\n```");

    expect($html)->toContain('<table>')
        ->and($html)->toContain('<code');
});
