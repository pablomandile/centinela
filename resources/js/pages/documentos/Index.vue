<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useFecha } from '@/composables/useFecha';
import { dashboard } from '@/routes';
import type { DocumentoEncontrado } from '@/types/centinela';

const props = defineProps<{
    q: string;
    documentos: DocumentoEncontrado[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Documentos', href: '/documentos' },
        ],
    },
});

const { haceCuanto } = useFecha();

const termino = ref(props.q);
let temporizador: ReturnType<typeof setTimeout> | undefined;

/*
 * Se busca al escribir, con 300 ms de espera. `replace` para no llenar el historial
 * con una entrada por letra —el "atrás" tiene que volver al tablero, no a
 * "documenta"— y `preserveState` para no perder el foco del input.
 */
watch(termino, (valor) => {
    clearTimeout(temporizador);

    temporizador = setTimeout(() => {
        router.get('/documentos', valor ? { q: valor } : {}, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
});

/** Sin búsqueda, agrupados por proyecto: es un índice. */
const porProyecto = computed(() => {
    const grupos = new Map<string, DocumentoEncontrado[]>();

    for (const documento of props.documentos) {
        const actuales = grupos.get(documento.proyecto) ?? [];
        actuales.push(documento);
        grupos.set(documento.proyecto, actuales);
    }

    return [...grupos.entries()];
});
</script>

<template>
    <Head title="Documentos" />

    <div class="flex flex-col gap-4 p-4">
        <div class="relative max-w-xl">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="termino"
                type="search"
                class="pl-9"
                placeholder="Buscar en toda la documentación…"
                aria-label="Buscar en la documentación"
            />
        </div>

        <!-- Con término: lista plana con el fragmento donde apareció. -->
        <template v-if="props.q">
            <p class="text-sm text-muted-foreground">
                {{ props.documentos.length }}
                {{ props.documentos.length === 1 ? 'resultado' : 'resultados' }}
                para «{{ props.q }}»
            </p>

            <ul class="divide-y rounded-lg border">
                <li
                    v-for="documento in props.documentos"
                    :key="`${documento.proyectoSlug}-${documento.slug}`"
                    class="p-3"
                >
                    <a
                        :href="`/proyectos/${documento.proyectoSlug}/documentos/${documento.slug}`"
                        class="block"
                    >
                        <span class="flex flex-wrap items-baseline gap-2">
                            <span class="font-medium hover:underline">{{
                                documento.titulo
                            }}</span>
                            <Badge variant="secondary">{{
                                documento.proyecto
                            }}</Badge>
                            <Badge variant="outline" class="uppercase">{{
                                documento.formato
                            }}</Badge>
                        </span>

                        <!-- El fragmento evita abrir cada documento para ver si era. -->
                        <span
                            v-if="documento.fragmento"
                            class="mt-1 block text-sm text-muted-foreground"
                        >
                            {{ documento.fragmento }}
                        </span>
                    </a>
                </li>
            </ul>

            <p
                v-if="!props.documentos.length"
                class="text-sm text-muted-foreground"
            >
                No hay nada con ese texto. La búsqueda mira el título y el
                contenido de los markdown; de los PDF solo el título.
            </p>
        </template>

        <!-- Sin término: el índice por proyecto. -->
        <template v-else>
            <div
                v-for="[proyecto, documentos] in porProyecto"
                :key="proyecto"
                class="space-y-2"
            >
                <h2 class="text-sm font-semibold">{{ proyecto }}</h2>

                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="documento in documentos"
                        :key="documento.slug"
                        class="flex items-center justify-between gap-3 p-3"
                    >
                        <a
                            :href="`/proyectos/${documento.proyectoSlug}/documentos/${documento.slug}`"
                            class="flex min-w-0 items-center gap-2 hover:underline"
                        >
                            <FileText
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="truncate text-sm">{{
                                documento.titulo
                            }}</span>
                        </a>

                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ documento.tamano }} ·
                            {{ haceCuanto(documento.actualizado) }}
                        </span>
                    </li>
                </ul>
            </div>

            <p
                v-if="!props.documentos.length"
                class="text-sm text-muted-foreground"
            >
                Todavía no hay documentos. Se suben desde la ficha de cada
                proyecto.
            </p>
        </template>
    </div>
</template>
