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
        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
            <p class="text-sm text-muted-foreground">
                {{ props.resumen.proyectos }}
                {{
                    props.resumen.proyectos === 1
                        ? 'proyecto activo'
                        : 'proyectos activos'
                }}
            </p>

            <p
                v-if="props.resumen.incidentes"
                class="text-sm font-medium text-red-600 dark:text-red-400"
            >
                {{ props.resumen.incidentes }}
                {{
                    props.resumen.incidentes === 1
                        ? 'incidente abierto'
                        : 'incidentes abiertos'
                }}
            </p>

            <p
                v-if="props.resumen.sinChequear"
                class="text-sm text-muted-foreground"
            >
                {{ props.resumen.sinChequear }} sin chequear todavía
            </p>
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
