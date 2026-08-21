<?php

use App\Models\User;

/*
 * Lo que se puede verificar sin un navegador: que los archivos existan, digan lo
 * que tienen que decir y que el HTML los referencie.
 *
 * Lo que **no** se puede verificar acá —que Chrome ofrezca instalar y que el
 * service worker quede activo— va con `scripts/check-pwa.mjs` del skill
 * `adaptar-a-pwa`, contra el sitio corriendo.
 */

it('tiene manifest con los dos tamaños que Chrome exige', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['name'])->toBe('Centinela')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/dashboard');

    $tamanos = array_column($manifest['icons'], 'sizes');

    // Sin 192 y 512 Chrome no ofrece instalar, y el síntoma es que "no aparece el
    // botón", sin ningún error.
    expect($tamanos)->toContain('192x192')
        ->and($tamanos)->toContain('512x512');

    // Y al menos un maskable, o en Android el ícono queda con un cuadrado blanco.
    expect(array_column($manifest['icons'], 'purpose'))->toContain('maskable');
});

it('los íconos que declara el manifest existen', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    foreach ($manifest['icons'] as $icono) {
        // El `?v=` es parte de la URL, no del nombre del archivo.
        $ruta = public_path(parse_url($icono['src'], PHP_URL_PATH));

        expect(file_exists($ruta))->toBeTrue("Falta el ícono {$icono['src']}");
    }
});

it('el service worker tiene handler de fetch, que es lo que Chrome exige', function () {
    $sw = (string) file_get_contents(public_path('sw.js'));

    // Un service worker registrado pero sin `fetch` no cuenta como instalable.
    expect($sw)->toContain("addEventListener('fetch'")
        // Cache-first solo para lo que tiene hash de contenido en el nombre.
        ->and($sw)->toContain("startsWith('/build/')")
        // La red de seguridad del JSON crudo de Inertia, con sus tres condiciones.
        ->and($sw)->toContain("request.mode === 'navigate'")
        ->and($sw)->toContain("headers.get('x-inertia')")
        ->and($sw)->toContain('recuperada.redirected');
});

it('el HTML enlaza el manifest y captura el evento de instalación antes de montar Vue', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain('<link rel="manifest" href="/manifest.webmanifest">')
        ->and($html)->toContain('theme-color')
        ->and($html)->toContain('viewport-fit=cover')
        // El listener inline: desde un componente el evento ya pasó.
        ->and($html)->toContain('beforeinstallprompt');
});

it('los archivos de marca y las cuatro referencias van con la misma versión', function () {
    /*
     * La trampa del skill `adaptar-a-pwa`, sección 6: un ícono vive en tres cachés
     * que no se limpian solas (la del service worker, la HTTP y la base de favicons
     * de Chrome mobile), y la única forma de saltearlas es cambiar la URL. O sea que
     * al regenerar los íconos hay que subir el `?v=` **y** el nombre de CACHE, y si
     * se sube uno solo el síntoma es que "el ícono viejo no se actualiza", sin error
     * en ningún lado.
     *
     * Este test los ata: no verifica que la versión sea un número en particular,
     * verifica que las cuatro digan lo mismo.
     */
    $version = function (string $contenido, string $patron): string {
        expect($contenido)->toMatch($patron);
        preg_match($patron, $contenido, $coincidencias);

        return $coincidencias[1];
    };

    $enElHtml = $version(
        $this->get(route('login'))->assertOk()->getContent(),
        '#/icons/icon-192\.png\?v=(\d+)#',
    );

    expect($version((string) file_get_contents(public_path('manifest.webmanifest')), '#/icons/icon-192\.png\?v=(\d+)#'))
        ->toBe($enElHtml)
        ->and($version((string) file_get_contents(public_path('sw.js')), "#const CACHE = 'centinela-v(\d+)'#"))
        ->toBe($enElHtml)
        // La pantalla de sin conexión es la cuarta, y la que se olvida: no la toca
        // nadie hasta que se corta la red.
        ->and($version((string) file_get_contents(public_path('offline.html')), '#/icons/icon-192\.png\?v=(\d+)#'))
        ->toBe($enElHtml);
});

it('la marca existe en los dos formatos y el favicon no es el de Laravel', function () {
    // Los dos derivados que usa la interfaz, generados por scripts/generar-iconos.php.
    expect(file_exists(public_path('img/logo.png')))->toBeTrue()
        ->and(file_exists(public_path('img/logotexto.png')))->toBeTrue()
        // iOS busca este solo en la raíz, no en /icons.
        ->and(file_exists(public_path('apple-touch-icon.png')))->toBeTrue()
        // El kit deja un favicon.svg con el logo de Laravel; si vuelve a aparecer,
        // gana sobre el .ico en los navegadores que soportan SVG.
        ->and(file_exists(public_path('favicon.svg')))->toBeFalse();

    // Un .ico con PNG adentro: cabecera de 6 bytes, tipo 1 = ícono.
    $ico = (string) file_get_contents(public_path('favicon.ico'));

    expect(unpack('vreservado/vtipo/vcantidad', $ico))
        ->toMatchArray(['reservado' => 0, 'tipo' => 1, 'cantidad' => 3]);
});

it('el .htaccess saca de la caché al service worker y al manifest', function () {
    /*
     * Es el arreglo de la trampa del CDN: los estáticos salen con max-age de siete
     * días, y estos dos archivos son los que le avisan al navegador que hay algo
     * nuevo. Cacheados, ninguna actualización llega hasta que expiren.
     */
    $htaccess = (string) file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('sw\.js|manifest\.webmanifest')
        ->and($htaccess)->toContain('no-cache, must-revalidate, max-age=0')
        ->and($htaccess)->toContain('AddType application/manifest+json .webmanifest');
});

it('el .htaccess conserva las reglas del framework', function () {
    // Se reescribió a mano para sumarle las cabeceras: que no se haya perdido en el
    // camino el ruteo al front controller ni el header del CSRF.
    $htaccess = (string) file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('RewriteRule ^ index.php [L]')
        ->and($htaccess)->toContain('HTTP:x-xsrf-token');
});

it('hay una pantalla de sin conexión que no depende de la red', function () {
    $offline = (string) file_get_contents(public_path('offline.html'));

    // Ni hoja de estilos externa ni fuente remota: se sirve justamente cuando no
    // hay red.
    expect($offline)->not->toContain('<link rel="stylesheet"')
        ->and($offline)->not->toContain('fonts.googleapis')
        ->and($offline)->toContain('Sin conexión');
});

it('la app instalada arranca en el tablero', function () {
    // `start_url` puede requerir login: eso no afecta la instalabilidad, el server
    // manda al login y listo.
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    $this->get($manifest['start_url'])->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get($manifest['start_url'])
        ->assertOk();
});
