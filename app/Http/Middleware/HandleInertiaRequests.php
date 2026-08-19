<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Evita que el navegador guarde la respuesta XHR de Inertia.
     *
     * Una URL de Inertia devuelve dos cuerpos según el header X-Inertia: el HTML
     * de arranque o el JSON de la página. Lo único que se los distingue a una
     * caché es el `Vary`, y el CDN de Hostinger lo borra cuando comprime con
     * brotli. Al restaurar una pestaña descartada, Chrome reusa la entrada
     * guardada sin revalidar y muestra el JSON crudo en pantalla.
     *
     * Va en este archivo y no en un middleware aparte: el de Inertia setea el
     * `Vary` y puede reemplazar la respuesta entera en `onVersionChange()`, así
     * que cualquier middleware agregado después en el grupo `web` correría su
     * parte de salida antes y quedaría pisado.
     *
     * Ver el skill `inertia-json-crudo`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // Inertia lo pone y el CDN lo borra, pero se declara igual: es lo
        // correcto y sirve en cualquier intermediario que sí lo respete.
        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        /*
         * `no-store`, no `no-cache`: `no-cache` permite guardar y solo obliga a
         * revalidar, y una navegación de historial saltea la revalidación.
         *
         * Y solo sobre la respuesta XHR, **nunca** sobre el HTML: `no-store` en
         * el documento principal desactiva el back/forward cache de Chrome y
         * convierte cada "atrás" en una ida completa a la red.
         */
        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
