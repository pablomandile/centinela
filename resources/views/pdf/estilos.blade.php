{{--
    Estilo sobrio y ancho fijo a propósito: esto se imprime, se fotocopia y se lee
    en blanco y negro. DomPDF además no soporta flexbox ni grid, así que todo va en
    flujo normal.

    Las tablas anchas y los bloques de código largos son el punto débil de DomPDF:
    se les fuerza el ancho y se les deja partir palabras para que no se corten.
--}}
<style>
    @page { margin: 2.2cm 2cm; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5pt;
        line-height: 1.45;
        color: #18181b;
    }

    h1 { font-size: 17pt; margin: 0 0 4pt; }
    h2 { font-size: 13pt; margin: 18pt 0 6pt; border-bottom: 0.6pt solid #d4d4d8; padding-bottom: 3pt; }
    h3 { font-size: 11.5pt; margin: 14pt 0 4pt; }
    h4 { font-size: 10.5pt; margin: 12pt 0 4pt; }

    p, ul, ol { margin: 0 0 8pt; }
    li { margin-bottom: 3pt; }

    a { color: #18181b; text-decoration: underline; }

    code {
        font-family: DejaVu Sans Mono, monospace;
        font-size: 8.5pt;
        background: #f4f4f5;
        padding: 0 2pt;
    }

    pre {
        font-family: DejaVu Sans Mono, monospace;
        font-size: 8pt;
        background: #f4f4f5;
        padding: 6pt 8pt;
        margin: 0 0 10pt;
        /* Sin esto una línea larga de consola se sale de la página. */
        word-wrap: break-word;
        white-space: pre-wrap;
    }

    blockquote {
        margin: 0 0 10pt;
        padding-left: 10pt;
        border-left: 2pt solid #d4d4d8;
        color: #52525b;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 10pt;
        font-size: 9pt;
        table-layout: fixed;
    }

    th, td {
        border: 0.5pt solid #d4d4d8;
        padding: 3pt 4pt;
        text-align: left;
        vertical-align: top;
        word-wrap: break-word;
    }

    th { background: #f4f4f5; }

    .pie {
        color: #71717a;
        font-size: 8.5pt;
    }

    .portada-datos { color: #52525b; font-size: 10pt; }

    .seccion { page-break-before: always; }
</style>
