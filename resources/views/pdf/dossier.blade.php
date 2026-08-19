<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dossier de {{ $proyecto->nombre }}</title>
    @include('pdf.estilos')
</head>
<body>

<h1>{{ $proyecto->nombre }}</h1>
<p class="portada-datos">
    Documentación completa · {{ $proyecto->url }}<br>
    {{ $proyecto->etiquetaTecnica() }}<br>
    Generado el {{ $generado->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }} UTC
</p>

@if (filled($proyecto->notas))
    <p class="portada-datos" style="margin-top: 10pt;">{{ $proyecto->notas }}</p>
@endif

<h2 style="margin-top: 24pt;">Contenido</h2>
<ol>
    @foreach ($secciones as $seccion)
        <li>{{ $seccion['titulo'] }} <span class="pie">({{ $seccion['nombre'] }})</span></li>
    @endforeach
</ol>

@if ($adjuntos->isNotEmpty())
    {{--
        Se listan y no se incluyen: DomPDF no une PDFs. Decirlo es mejor que dejar
        que el dossier parezca completo cuando no lo está.
    --}}
    <h3>Además, sin incluir acá</h3>
    <p class="pie">
        Estos documentos ya son PDF y no se pueden unir a este archivo. Están en
        Centinela, en la ficha del proyecto:
    </p>
    <ul class="pie">
        @foreach ($adjuntos as $adjunto)
            <li>{{ $adjunto }}</li>
        @endforeach
    </ul>
@endif

@foreach ($secciones as $seccion)
    <div class="seccion">
        <h2>{{ $seccion['titulo'] }}</h2>
        <p class="pie">{{ $seccion['nombre'] }}</p>
        {!! $seccion['html'] !!}
    </div>
@endforeach

</body>
</html>
