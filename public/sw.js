/*
 * Service worker de Centinela.
 *
 * Hace dos cosas: cumplir el requisito de instalabilidad (Chrome exige un handler
 * de `fetch`, no solo un service worker registrado) y dejar la app usable cuando
 * no hay red.
 *
 * La regla que ordena todo: **cache-first solo para lo que tiene hash de contenido
 * en el nombre**. Cualquier URL fija —íconos, manifest, páginas— va network-first,
 * o queda congelada para siempre y no hay forma de actualizarla.
 *
 * Al cambiar los íconos hay que tocar tres lugares a la vez: el nombre de CACHE
 * acá, el `?v=` de los `<link rel="icon">` y el `?v=` del manifest. Ver el skill
 * `adaptar-a-pwa`, sección 6.
 */
const CACHE = 'centinela-v2';
const SIN_CONEXION = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([SIN_CONEXION, '/manifest.webmanifest']))
            .catch(() => {}),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((nombres) =>
                Promise.all(
                    nombres.filter((nombre) => nombre !== CACHE).map((nombre) => caches.delete(nombre)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

/**
 * Una navegación de verdad, no un XHR del router.
 *
 * El chequeo por `Accept: text/html` que traen casi todos los service workers no
 * alcanza: **el router de Inertia manda ese mismo Accept en sus XHR**, así que
 * daría true para cada navegación de la SPA.
 */
const esNavegacion = (request) => request.mode === 'navigate';

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Lo único con hash de contenido en el nombre: se puede guardar para siempre.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (guardada) =>
                    guardada ??
                    fetch(request).then((respuesta) => {
                        const copia = respuesta.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copia));

                        return respuesta;
                    }),
            ),
        );

        return;
    }

    if (esNavegacion(request)) {
        event.respondWith(
            fetch(request)
                .then((respuesta) => {
                    /*
                     * Red de seguridad del skill `inertia-json-crudo`: si a una
                     * navegación le llega una respuesta armada para un XHR de
                     * Inertia —porque quedó guardada en la caché HTTP del
                     * navegador— se pide de nuevo forzando HTML. Sin esto, la
                     * pantalla muestra el JSON crudo y la app no arranca.
                     *
                     * El header `x-inertia` de la **respuesta** y no el
                     * content-type: una exportación que se descarga también
                     * contesta JSON a una navegación de verdad, y pedirla dos
                     * veces se nota.
                     */
                    if (!respuesta.headers.get('x-inertia')) {
                        return respuesta;
                    }

                    return fetch(request.url, {
                        cache: 'reload',
                        headers: { Accept: 'text/html' },
                    }).then((recuperada) =>
                        /*
                         * Si la sesión venció, esa URL redirige al login. Una
                         * respuesta ya redirigida no se le puede entregar a una
                         * navegación —lo prohíbe la Service Worker API—, así que se
                         * le pasa el redirect y lo sigue el navegador.
                         */
                        recuperada.redirected
                            ? Response.redirect(recuperada.url, 302)
                            : recuperada,
                    );
                })
                .catch(() => caches.match(SIN_CONEXION)),
        );

        return;
    }

    // Todo lo demás: red primero, y lo guardado como respaldo si no hay conexión.
    event.respondWith(
        fetch(request)
            .then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copia));
                }

                return respuesta;
            })
            .catch(() => caches.match(request)),
    );
});
