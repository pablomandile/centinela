{{--
    Mail sobrio a propósito: se lee en el celular, muchas veces de noche, y lo
    único que importa es qué se rompió, dónde y desde cuándo.

    Cuidado con las directivas Blade pegadas a una letra: `algo@endif` no se
    reconoce como directiva. Para condicionales inline conviene una expresión.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $aviso->proyecto->nombre }}</title>
</head>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; font-size: 15px; line-height: 1.5; color: #18181b;">

<p style="font-size: 17px; margin: 0 0 16px;">
    <strong>{{ $aviso->proyecto->nombre }}</strong>
    {{ $seRecupero ? 'volvió a funcionar.' : 'tiene una falla.' }}
</p>

<table cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 20px;">
    <tr>
        <td style="padding: 4px 16px 4px 0; color: #71717a;">Chequeo</td>
        <td style="padding: 4px 0;">{{ $aviso->tipo->etiqueta() }}</td>
    </tr>
    <tr>
        <td style="padding: 4px 16px 4px 0; color: #71717a;">Sitio</td>
        <td style="padding: 4px 0;"><a href="{{ $aviso->proyecto->url }}">{{ $aviso->proyecto->url }}</a></td>
    </tr>
    <tr>
        <td style="padding: 4px 16px 4px 0; color: #71717a;">Empezó</td>
        <td style="padding: 4px 0;">{{ $aviso->abierto_at->isoFormat('D [de] MMMM, HH:mm') }} UTC</td>
    </tr>
    @if ($seRecupero)
        <tr>
            <td style="padding: 4px 16px 4px 0; color: #71717a;">Duró</td>
            <td style="padding: 4px 0;">{{ $aviso->duracion() }}</td>
        </tr>
    @endif
</table>

@if (! $seRecupero && filled($aviso->ultimo_mensaje))
    <p style="margin: 0 0 20px; padding: 12px 14px; background: #fef2f2; border-left: 3px solid #dc2626;">
        {{ $aviso->ultimo_mensaje }}
    </p>
@endif

<p style="margin: 0;">
    {{-- `url()` y no `route()`: el mail lo manda el scheduler, y una vista de mail
         que depende de que exista una ruta con nombre se rompe justo cuando hace
         falta que funcione. La URL del detalle es estable. --}}
    <a href="{{ url('/proyectos/'.$aviso->proyecto->slug) }}">Ver el detalle en Centinela</a>
</p>

<p style="margin: 24px 0 0; color: #a1a1aa; font-size: 13px;">
    Este aviso lo manda Centinela cada vez que un chequeo falla dos veces seguidas,
    y otro cuando se recupera.
</p>

</body>
</html>
