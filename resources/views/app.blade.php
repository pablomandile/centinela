<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        {{-- `viewport-fit=cover` para que la app instalada use la pantalla completa
             en los celulares con notch. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <meta name="theme-color" content="#0d0d0f">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Centinela">
        <link rel="manifest" href="/manifest.webmanifest">

        {{--
            ⚠️ Este script va acá, inline y antes de que monte Vue, y no en un
            componente.

            Chrome dispara `beforeinstallprompt` apenas carga la página,
            normalmente **antes** de que monte la app. Escuchándolo desde un
            `onMounted` el evento ya pasó y el botón de instalar no aparece nunca
            —de forma intermitente, que es lo peor—. Ver el skill `adaptar-a-pwa`,
            sección 4.
        --}}
        <script>
            (function () {
                window.__pwaInstall = { prompt: null, installed: false };

                window.addEventListener('beforeinstallprompt', function (e) {
                    // Se evita el banner automático: lo lanza el botón de la app.
                    e.preventDefault();
                    window.__pwaInstall.prompt = e;
                    window.dispatchEvent(new CustomEvent('pwa:installable'));
                });

                window.addEventListener('appinstalled', function () {
                    window.__pwaInstall.prompt = null;
                    window.__pwaInstall.installed = true;
                    window.dispatchEvent(new CustomEvent('pwa:installed'));
                });
            })();
        </script>

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{--
            El `?v=` no es decorativo: los íconos tienen URL fija y viven en tres
            cachés distintas (la del service worker, la HTTP del navegador y la base
            de favicons de Chrome mobile). Al cambiar un ícono hay que subir este
            número **y** el del manifest **y** el nombre de CACHE en sw.js.
        --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/icons/icon-192.png?v=1" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=1">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
