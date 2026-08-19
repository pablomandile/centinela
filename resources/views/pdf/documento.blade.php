<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $documento->titulo }}</title>
    @include('pdf.estilos')
</head>
<body>

<h1>{{ $documento->titulo }}</h1>
<p class="pie">
    {{ $proyecto->nombre }} · {{ $documento->nombre_original }} ·
    generado el {{ now()->isoFormat('D [de] MMMM [de] YYYY') }}
</p>

<hr style="border: none; border-top: 0.6pt solid #d4d4d8; margin: 10pt 0 14pt;">

{{-- El markdown ya viene renderizado y con el HTML de entrada escapado. --}}
{!! $html !!}

</body>
</html>
