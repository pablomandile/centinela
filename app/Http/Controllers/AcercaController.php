<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Acerca de: qué es Centinela, quién lo hizo y cómo contactarlo.
 *
 * Es la misma página que la de movieboxd, adaptada. Dos diferencias, las dos a
 * propósito:
 *
 * 1. **Va detrás del login.** En movieboxd es pública porque ahí hay catálogo para
 *    mirar sin cuenta; acá la única ruta pública es `/salud`, y la página usa el
 *    layout de la app —sidebar y menú de usuario— que sin sesión no tiene qué
 *    mostrar.
 * 2. **No lleva props de `meta`.** Movieboxd arma título y descripción para las
 *    tarjetas de redes sociales; a una herramienta de un solo usuario que nadie
 *    puede abrir sin entrar no le sirve de nada.
 *
 * Es un controlador y no un closure en `routes/web.php` porque así está el resto de
 * las rutas, y porque un closure deja de ser cacheable con `route:cache` el día que
 * el deploy lo agregue.
 */
class AcercaController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Acerca');
    }
}
