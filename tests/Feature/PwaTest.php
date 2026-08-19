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
