<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import SemaforoEstado from '@/components/SemaforoEstado.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFecha } from '@/composables/useFecha';
import type { ProyectoDelTablero } from '@/types/centinela';

const props = defineProps<{ proyecto: ProyectoDelTablero }>();

const { haceCuanto } = useFecha();

/** El peor estado de sus chequeos manda el color de la tarjeta. */
const peor = computed(() => {
    const gravedad = { ok: 0, advertencia: 1, falla: 2 };

    return (
        props.proyecto.chequeos
            .map((chequeo) => chequeo.estado)
            .sort((a, b) => gravedad[b] - gravedad[a])[0] ?? null
    );
});

const ultimoChequeo = computed(() =>
    props.proyecto.chequeos
        .map((chequeo) => chequeo.cuando)
        .sort()
        .at(-1),
);

const disponibilidad = computed(() =>
    props.proyecto.chequeos.find(
        (chequeo) => chequeo.tipo === 'disponibilidad',
    ),
);

const problemas = computed(() =>
    props.proyecto.chequeos.filter((chequeo) => chequeo.estado !== 'ok'),
);
</script>

<template>
    <Card
        class="transition-colors"
        :class="{
            'border-red-500/40': peor === 'falla',
            'border-amber-500/40': peor === 'advertencia',
            'opacity-60': !props.proyecto.activo,
        }"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-2">
                <CardTitle class="text-base leading-tight">
                    <Link
                        :href="`/proyectos/${props.proyecto.slug}`"
                        class="hover:underline"
                    >
                        {{ props.proyecto.nombre }}
                    </Link>
                </CardTitle>

                <SemaforoEstado :estado="peor" />
            </div>

            <p class="text-xs text-muted-foreground">
                {{ props.proyecto.tecnologia }}
                <span v-if="!props.proyecto.activo"> · inactivo</span>
            </p>
        </CardHeader>

        <CardContent class="space-y-3">
            <!-- Lo primero que se quiere ver: si contesta y cuánto tarda. -->
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span class="text-muted-foreground">
                    {{
                        disponibilidad
                            ? `${disponibilidad.latencia ?? '—'} ms`
                            : 'sin chequear'
                    }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ haceCuanto(ultimoChequeo) }}
                </span>
            </div>

            <!-- Solo lo que no está bien: una lista de seis "ok" no dice nada. -->
            <ul v-if="problemas.length" class="space-y-1.5">
                <li
                    v-for="chequeo in problemas"
                    :key="chequeo.tipo"
                    class="flex items-start gap-2 text-xs"
                >
                    <SemaforoEstado
                        :estado="chequeo.estado"
                        tamano="sm"
                        class="mt-1"
                    />
                    <span>
                        <span class="font-medium">{{ chequeo.etiqueta }}:</span>
                        {{ chequeo.mensaje }}
                    </span>
                </li>
            </ul>

            <p
                v-else-if="props.proyecto.chequeos.length"
                class="text-xs text-muted-foreground"
            >
                Los {{ props.proyecto.chequeos.length }} chequeos en verde.
            </p>

            <div class="flex items-center justify-between gap-2 pt-1">
                <Badge v-if="props.proyecto.incidentes" variant="destructive">
                    {{ props.proyecto.incidentes }}
                    {{
                        props.proyecto.incidentes === 1
                            ? 'incidente abierto'
                            : 'incidentes abiertos'
                    }}
                </Badge>
                <span v-else />

                <a
                    :href="props.proyecto.url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    Abrir <ExternalLink class="size-3" />
                </a>
            </div>
        </CardContent>
    </Card>
</template>
