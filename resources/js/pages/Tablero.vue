<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import TarjetaProyecto from '@/components/TarjetaProyecto.vue';
import { dashboard } from '@/routes';
import type { ProyectoDelTablero } from '@/types/centinela';

const props = defineProps<{
    proyectos: ProyectoDelTablero[];
    resumen: { proyectos: number; incidentes: number; sinChequear: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

/*
 * Los proyectos con algo roto van primero. En un tablero de doce tarjetas, lo que
 * requiere atención no puede depender de que uno recorra la grilla con la vista.
 */
const ordenados = computed(() => {
    const gravedad = (proyecto: ProyectoDelTablero): number => {
        if (!proyecto.activo) {
            return -1;
        }

        const estados = proyecto.chequeos.map((chequeo) => chequeo.estado);

        if (estados.includes('falla')) {
            return 3;
        }

        if (estados.includes('advertencia')) {
            return 2;
        }

        return estados.length ? 1 : 0;
    };

    return [...props.proyectos].sort((a, b) => gravedad(b) - gravedad(a));
});
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-col gap-6 p-4">
        <!--
            El resumen en píldoras y no en un párrafo: con el mismo lenguaje de
            color que las tarjetas, y los números en `tabular-nums` para que no
            bailen al actualizarse.
        -->
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border bg-card px-3 py-1 text-sm"
            >
                <span class="font-semibold tabular-nums">{{
                    props.resumen.proyectos
                }}</span>
                <span class="text-muted-foreground">
                    {{
                        props.resumen.proyectos === 1
                            ? 'proyecto activo'
                            : 'proyectos activos'
                    }}
                </span>
            </span>

            <span
                v-if="props.resumen.incidentes"
                class="inline-flex items-center gap-1.5 rounded-full border border-red-300 bg-red-50 px-3 py-1 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/50 dark:text-red-100"
            >
                <span class="font-semibold tabular-nums">{{
                    props.resumen.incidentes
                }}</span>
                {{
                    props.resumen.incidentes === 1
                        ? 'incidente abierto'
                        : 'incidentes abiertos'
                }}
            </span>

            <span
                v-if="props.resumen.sinChequear"
                class="inline-flex items-center gap-1.5 rounded-full border bg-muted px-3 py-1 text-sm text-muted-foreground"
            >
                <span class="font-semibold tabular-nums">{{
                    props.resumen.sinChequear
                }}</span>
                sin chequear todavía
            </span>
        </div>

        <!-- Una columna en el celular, que es donde más se mira esto. -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <TarjetaProyecto
                v-for="proyecto in ordenados"
                :key="proyecto.slug"
                :proyecto="proyecto"
            />
        </div>

        <p v-if="!props.proyectos.length" class="text-sm text-muted-foreground">
            Todavía no hay proyectos cargados.
        </p>
    </div>
</template>
