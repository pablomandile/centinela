<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import SemaforoEstado from '@/components/SemaforoEstado.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFecha } from '@/composables/useFecha';
import type { ProyectoDelTablero } from '@/types/centinela';

const props = defineProps<{ proyecto: ProyectoDelTablero }>();

const { haceCuanto, enCuanto, fechaYHora } = useFecha();

/**
 * El estado de la tarjeta entera: el peor de sus chequeos, salvo que el proyecto
 * esté apagado, que gana sobre todo lo demás.
 */
const estado = computed(() => {
    if (!props.proyecto.activo) {
        return 'inactivo' as const;
    }

    const gravedad = { ok: 0, advertencia: 1, falla: 2 };

    return (
        props.proyecto.chequeos
            .map((chequeo) => chequeo.estado)
            .sort((a, b) => gravedad[b] - gravedad[a])[0] ?? null
    );
});

/**
 * El relleno y la franja de color.
 *
 * **Solo lo que requiere atención se pinta fuerte.** Si las dieciséis tarjetas
 * tuvieran un fondo de color parejo, las dos que están mal dejarían de saltar a la
 * vista, que es exactamente lo único que el tablero tiene que lograr. Así que el
 * verde es un susurro, el ámbar y el rojo se ven, y la franja de la izquierda —que
 * sí lleva color siempre— alcanza para recorrer la grilla de un vistazo.
 */
const paleta = computed(() => {
    switch (estado.value) {
        case 'falla':
            return {
                tarjeta:
                    'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
                franja: 'bg-red-500',
            };
        case 'advertencia':
            return {
                tarjeta:
                    'border-amber-300 bg-amber-50 dark:border-amber-900/80 dark:bg-amber-950/30',
                franja: 'bg-amber-500',
            };
        case 'ok':
            return {
                tarjeta: 'bg-emerald-500/[0.06] dark:bg-emerald-400/[0.05]',
                franja: 'bg-emerald-500',
            };
        case 'inactivo':
            return { tarjeta: 'bg-muted/50', franja: 'bg-muted-foreground/25' };
        default:
            return { tarjeta: '', franja: 'bg-muted-foreground/40' };
    }
});

const disponibilidad = computed(() =>
    props.proyecto.chequeos.find(
        (chequeo) => chequeo.tipo === 'disponibilidad',
    ),
);

const ultimoChequeo = computed(() =>
    props.proyecto.chequeos
        .map((chequeo) => chequeo.cuando)
        .sort()
        .at(-1),
);

/** Solo lo que no está bien: una lista de seis "ok" no dice nada. */
const problemas = computed(() =>
    props.proyecto.chequeos.filter((chequeo) => chequeo.estado !== 'ok'),
);
</script>

<template>
    <Card
        :class="[
            'group relative gap-3 overflow-hidden py-4 transition-shadow hover:shadow-md',
            paleta.tarjeta,
        ]"
    >
        <!--
            La franja de color, decorativa: el estado en palabras está en la píldora
            de arriba y en el `title` del punto, así que un lector de pantalla no se
            pierde nada salteándose esto.
        -->
        <span
            aria-hidden="true"
            class="absolute inset-y-0 left-0 w-1"
            :class="paleta.franja"
        />

        <CardHeader class="gap-1 pl-7">
            <div class="flex items-start justify-between gap-2">
                <CardTitle class="text-base leading-tight">
                    <Link
                        :href="`/proyectos/${props.proyecto.slug}`"
                        class="group-hover:underline"
                    >
                        {{ props.proyecto.nombre }}
                    </Link>
                </CardTitle>

                <SemaforoEstado
                    :estado="estado"
                    con-texto
                    tamano="sm"
                    class="shrink-0 rounded-full border bg-background/60 px-2 py-0.5 font-medium"
                />
            </div>

            <p class="text-xs text-muted-foreground">
                {{ props.proyecto.tecnologia }}
            </p>
        </CardHeader>

        <!--
            `flex-1` y el `mt-auto` del pie: en la grilla las tarjetas de una fila
            comparten alto, así que sin eso el enlace "Abrir" de la más corta queda
            flotando en el medio y la tarjeta parece cortada a la mitad.
        -->
        <CardContent class="flex flex-1 flex-col gap-3 pl-7">
            <!-- Lo primero que se quiere ver: cuánto tarda y de cuándo es el dato. -->
            <div class="flex items-end justify-between gap-2">
                <p class="text-2xl leading-none font-semibold tabular-nums">
                    <template v-if="disponibilidad">
                        {{ disponibilidad.latencia ?? '—' }}
                        <span class="text-sm font-normal text-muted-foreground">
                            ms
                        </span>
                    </template>
                    <span
                        v-else
                        class="text-sm font-normal text-muted-foreground"
                    >
                        {{
                            props.proyecto.activo
                                ? 'sin chequear'
                                : 'no se chequea'
                        }}
                    </span>
                </p>

                <div class="text-right text-xs text-muted-foreground">
                    <p :title="fechaYHora(ultimoChequeo)">
                        {{ haceCuanto(ultimoChequeo) }}
                    </p>
                    <!--
                        Cuándo vuelve a mirarse. Va redondeado al tick del
                        scheduler, así que es un "no antes de", no un promedio: el
                        backend ya no promete un minuto que el cron no puede cumplir.
                    -->
                    <p
                        v-if="props.proyecto.proximo"
                        :title="`Próximo chequeo: ${fechaYHora(props.proyecto.proximo)}`"
                    >
                        vuelve {{ enCuanto(props.proyecto.proximo) }}
                    </p>
                </div>
            </div>

            <ul v-if="problemas.length" class="space-y-1">
                <li
                    v-for="chequeo in problemas"
                    :key="chequeo.tipo"
                    class="flex items-start gap-2 rounded-md px-2 py-1.5 text-xs"
                    :class="
                        chequeo.estado === 'falla'
                            ? 'bg-red-500/10 dark:bg-red-500/15'
                            : 'bg-amber-500/10 dark:bg-amber-500/15'
                    "
                >
                    <SemaforoEstado
                        :estado="chequeo.estado"
                        tamano="sm"
                        class="mt-0.5"
                    />
                    <!--
                        Dos líneas y el resto en el `title`: el mensaje del JSON de
                        Inertia son cuatro renglones y con dieciséis tarjetas en
                        grilla desalinea todo. El texto entero está a un clic, en el
                        detalle del proyecto.
                    -->
                    <span class="line-clamp-2" :title="chequeo.mensaje ?? ''">
                        <span class="font-medium">{{ chequeo.etiqueta }}:</span>
                        {{ chequeo.mensaje }}
                    </span>
                </li>
            </ul>

            <p
                v-else-if="props.proyecto.chequeos.length"
                class="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400"
            >
                <Check class="size-3.5 shrink-0" />
                Los {{ props.proyecto.chequeos.length }} chequeos en verde.
            </p>

            <div class="mt-auto flex items-center justify-between gap-2">
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
