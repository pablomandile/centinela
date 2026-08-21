<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Globe, Mail } from '@lucide/vue';
import LogoTexto from '@/components/LogoTexto.vue';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Acerca de', href: '/acerca' },
        ],
    },
});

const EMAIL = 'pablo.mandile@gmail.com';
const WEB = 'https://bioinfo.pablomandile.com.ar/pablo';
</script>

<template>
    <Head title="Acerca de" />

    <div class="flex flex-col gap-6 p-4">
        <header>
            <h1 class="text-xl font-semibold">Acerca de</h1>
        </header>

        <!--
            Ancho acotado: es un bloque de texto para leer, y una línea que cruza un
            monitor de 27" se pierde de renglón.
        -->
        <Card class="max-w-2xl">
            <CardContent class="flex flex-col gap-6 sm:px-8">
                <!-- El logo entero: acá hay lugar y es la página que habla de la app. -->
                <LogoTexto class="w-56" />

                <div class="flex flex-col gap-3 leading-relaxed">
                    <p>
                        Vigila los sitios que ya están publicados y avisa por
                        mail cuando uno se cae y cuando se recupera. Además de
                        si contestan, audita lo que en este hosting ya costó
                        tiempo: que el JSON de Inertia no se pueda guardar en la
                        caché, que el service worker y el manifest no queden
                        congelados en el CDN, que el bundle que pide la página
                        exista y cuánto le queda al certificado.
                    </p>
                    <p>
                        Y agrupa por proyecto la documentación que estaba suelta
                        —los
                        <code class="text-sm">.md</code> y los
                        <code class="text-sm">.pdf</code> de cada uno—, con
                        visor, buscador y exportación a PDF.
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Es una herramienta personal: no tiene registro y solo
                        entran las cuentas habilitadas.
                    </p>
                </div>

                <div class="border-t pt-6">
                    <p
                        class="text-xs font-bold tracking-[0.075em] text-muted-foreground uppercase"
                    >
                        Creado por
                    </p>
                    <p class="mt-1 text-lg font-semibold">Pablo Mandile</p>

                    <!--
                        En una columna en celular y en fila desde sm: un mail largo al
                        lado de otro enlace no entra en 360 px sin desbordar.
                    -->
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:gap-6">
                        <a
                            :href="`mailto:${EMAIL}`"
                            class="inline-flex items-center gap-2 text-sm transition-colors hover:text-primary"
                        >
                            <Mail class="size-4 shrink-0" />
                            <span class="break-all">{{ EMAIL }}</span>
                        </a>

                        <a
                            :href="WEB"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-sm transition-colors hover:text-primary"
                        >
                            <Globe class="size-4 shrink-0" />
                            <span>Bioinfo</span>
                        </a>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
