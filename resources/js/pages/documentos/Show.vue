<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, FileDown } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useFecha } from '@/composables/useFecha';
import { dashboard } from '@/routes';
import type { EntradaDeIndice } from '@/types/centinela';

const props = defineProps<{
    documento: {
        slug: string;
        titulo: string;
        nombre_original: string;
        tamano: string;
        actualizado: string | null;
    };
    proyecto: { slug: string; nombre: string };
    /** HTML ya renderizado en el server, con el HTML de entrada escapado. */
    html: string;
    indice: EntradaDeIndice[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const { haceCuanto } = useFecha();
</script>

<template>
    <Head :title="props.documento.titulo" />

    <div class="p-4">
        <header class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">
                    {{ props.documento.titulo }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    <Link
                        :href="`/proyectos/${props.proyecto.slug}`"
                        class="hover:underline"
                    >
                        {{ props.proyecto.nombre }}
                    </Link>
                    · {{ props.documento.nombre_original }} ·
                    {{ props.documento.tamano }} ·
                    {{ haceCuanto(props.documento.actualizado) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button as-child variant="outline" size="sm">
                    <a
                        :href="`/proyectos/${props.proyecto.slug}/documentos/${props.documento.slug}/pdf`"
                    >
                        <FileDown class="size-4" /> PDF
                    </a>
                </Button>
                <Button as-child variant="outline" size="sm">
                    <a
                        :href="`/proyectos/${props.proyecto.slug}/documentos/${props.documento.slug}/descargar`"
                    >
                        <Download class="size-4" /> Original
                    </a>
                </Button>
            </div>
        </header>

        <div class="flex flex-col gap-8 lg:flex-row-reverse lg:items-start">
            <!--
                El índice va a la derecha en escritorio y arriba en celular, y solo si
                tiene al menos dos entradas: un índice de un solo ítem no ayuda a
                nadie y ocupa una pantalla del pulgar.
            -->
            <nav
                v-if="props.indice.length > 1"
                class="lg:sticky lg:top-4 lg:w-64 lg:shrink-0"
                aria-label="Contenido del documento"
            >
                <p
                    class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    En este documento
                </p>
                <ul class="space-y-1 border-l pl-3 text-sm">
                    <li
                        v-for="entrada in props.indice"
                        :key="entrada.ancla"
                        :class="entrada.nivel === 3 ? 'pl-3' : ''"
                    >
                        <a
                            :href="`#${entrada.ancla}`"
                            class="block text-muted-foreground hover:text-foreground"
                        >
                            {{ entrada.titulo }}
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- eslint-disable-next-line vue/no-v-html -->
            <article class="prosa min-w-0 flex-1" v-html="props.html" />
        </div>
    </div>
</template>

<style scoped>
/*
 * Estilos del markdown renderizado, a mano en vez de con @tailwindcss/typography:
 * es una sola pantalla y sumar una dependencia para esto no se paga. Van con
 * `:deep()` porque el HTML entra por v-html y no lo toca el scoping de Vue.
 */
.prosa {
    line-height: 1.7;
}

.prosa :deep(h1),
.prosa :deep(h2),
.prosa :deep(h3),
.prosa :deep(h4) {
    font-weight: 600;
    line-height: 1.3;
    /* Para que el ancla no quede tapada por la cabecera al saltar. */
    scroll-margin-top: 1rem;
}

.prosa :deep(h1) {
    font-size: 1.5rem;
    margin: 1.75rem 0 0.75rem;
}

.prosa :deep(h2) {
    font-size: 1.25rem;
    margin: 1.75rem 0 0.75rem;
    padding-bottom: 0.3rem;
    border-bottom: 1px solid var(--border);
}

.prosa :deep(h3) {
    font-size: 1.05rem;
    margin: 1.5rem 0 0.5rem;
}

.prosa :deep(p),
.prosa :deep(ul),
.prosa :deep(ol),
.prosa :deep(blockquote) {
    margin: 0 0 1rem;
}

.prosa :deep(ul),
.prosa :deep(ol) {
    padding-left: 1.5rem;
}

.prosa :deep(ul) {
    list-style: disc;
}

.prosa :deep(ol) {
    list-style: decimal;
}

.prosa :deep(li) {
    margin-bottom: 0.35rem;
}

.prosa :deep(a) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.prosa :deep(code) {
    background: var(--muted);
    border-radius: 0.25rem;
    padding: 0.1rem 0.3rem;
    font-size: 0.875em;
}

.prosa :deep(pre) {
    background: var(--muted);
    border-radius: 0.5rem;
    padding: 0.85rem 1rem;
    margin: 0 0 1rem;
    /* Un bloque de consola largo scrollea solo y no ensancha la página. */
    overflow-x: auto;
}

.prosa :deep(pre code) {
    background: none;
    padding: 0;
}

.prosa :deep(blockquote) {
    border-left: 3px solid var(--border);
    padding-left: 1rem;
    color: var(--muted-foreground);
}

/* Las tablas de estos documentos son anchas: scrollean adentro de su contenedor. */
.prosa :deep(table) {
    display: block;
    overflow-x: auto;
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 1rem;
    font-size: 0.9rem;
}

.prosa :deep(th),
.prosa :deep(td) {
    border: 1px solid var(--border);
    padding: 0.4rem 0.6rem;
    text-align: left;
    vertical-align: top;
}

.prosa :deep(th) {
    background: var(--muted);
    font-weight: 600;
}

.prosa :deep(hr) {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.75rem 0;
}

.prosa :deep(img) {
    max-width: 100%;
    height: auto;
}
</style>
