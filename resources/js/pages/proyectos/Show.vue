<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ExternalLink, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import GraficoLatencia from '@/components/GraficoLatencia.vue';
import PanelDocumentos from '@/components/PanelDocumentos.vue';
import SemaforoEstado from '@/components/SemaforoEstado.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useFecha } from '@/composables/useFecha';
import { dashboard } from '@/routes';
import type {
    ChequeoDetallado,
    DocumentoResumido,
    IncidenteResumido,
    PuntoDeLatencia,
} from '@/types/centinela';

const props = defineProps<{
    proyecto: {
        slug: string;
        nombre: string;
        url: string;
        repo_url: string | null;
        activo: boolean;
        tecnologia: string;
        notas: string | null;
        intervalo_minutos: number;
    };
    chequeos: ChequeoDetallado[];
    latencias: PuntoDeLatencia[];
    incidentes: IncidenteResumido[];
    documentos: DocumentoResumido[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const { haceCuanto, fechaYHora } = useFecha();

/** Qué chequeo tiene el detalle desplegado. Uno a la vez. */
const abierto = ref<string | null>(null);

const detectar = useForm({});
</script>

<template>
    <Head :title="props.proyecto.nombre" />

    <div class="flex flex-col gap-6 p-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ props.proyecto.nombre }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.proyecto.tecnologia }} · chequeos cada
                    {{ props.proyecto.intervalo_minutos }} min
                    <span v-if="!props.proyecto.activo"> · inactivo</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="detectar.processing"
                    @click="
                        detectar.post(
                            `/proyectos/${props.proyecto.slug}/detectar`,
                            { preserveScroll: true },
                        )
                    "
                >
                    <Spinner v-if="detectar.processing" />
                    <RefreshCw v-else class="size-4" />
                    Detectar qué usa
                </Button>

                <Button as-child variant="outline" size="sm">
                    <a
                        :href="props.proyecto.url"
                        target="_blank"
                        rel="noopener"
                    >
                        Abrir <ExternalLink class="size-4" />
                    </a>
                </Button>
            </div>
        </header>

        <p
            v-if="props.proyecto.notas"
            class="max-w-3xl text-sm text-muted-foreground"
        >
            {{ props.proyecto.notas }}
        </p>

        <!-- Los chequeos, con su detalle desplegable. -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Chequeos</CardTitle>
            </CardHeader>
            <CardContent class="divide-y">
                <div
                    v-for="chequeo in props.chequeos"
                    :key="chequeo.tipo"
                    class="py-3 first:pt-0 last:pb-0"
                >
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 text-left"
                        :aria-expanded="abierto === chequeo.tipo"
                        @click="
                            abierto =
                                abierto === chequeo.tipo ? null : chequeo.tipo
                        "
                    >
                        <SemaforoEstado
                            :estado="chequeo.estado"
                            class="mt-1.5"
                        />

                        <span class="flex-1">
                            <span class="flex flex-wrap items-baseline gap-x-2">
                                <span class="font-medium">{{
                                    chequeo.etiqueta
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ haceCuanto(chequeo.cuando) }}
                                    <template v-if="chequeo.latencia">
                                        · {{ chequeo.latencia }} ms
                                    </template>
                                    <template v-if="chequeo.codigo">
                                        · HTTP {{ chequeo.codigo }}
                                    </template>
                                </span>
                            </span>
                            <span
                                class="mt-0.5 block text-sm text-muted-foreground"
                            >
                                {{ chequeo.mensaje }}
                            </span>
                        </span>

                        <ChevronDown
                            class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': abierto === chequeo.tipo }"
                        />
                    </button>

                    <div
                        v-if="abierto === chequeo.tipo"
                        class="mt-3 space-y-2 pl-8"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ chequeo.descripcion }}
                        </p>
                        <!-- El detalle crudo: es lo que permite explicar el veredicto
                             sin volver a pegarle al sitio. Scrollea aparte para que
                             una cabecera larga no ensanche la página. -->
                        <pre
                            v-if="chequeo.detalle"
                            class="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs"
                            >{{
                                JSON.stringify(chequeo.detalle, null, 2)
                            }}</pre>
                    </div>
                </div>

                <p
                    v-if="!props.chequeos.length"
                    class="py-2 text-sm text-muted-foreground"
                >
                    Todavía no se le corrió ningún chequeo.
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base"
                        >Latencia, últimas 24 h</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <GraficoLatencia :puntos="props.latencias" />

                    <!-- El gráfico nunca es la única fuente: los últimos valores van
                         en texto, para lector de pantalla y para puntos encimados. -->
                    <ul
                        v-if="props.latencias.length"
                        class="mt-3 space-y-1 text-xs text-muted-foreground"
                    >
                        <li
                            v-for="punto in props.latencias.slice(-3).reverse()"
                            :key="punto.cuando"
                            class="flex items-center gap-2"
                        >
                            <SemaforoEstado
                                :estado="punto.estado"
                                tamano="sm"
                            />
                            {{ fechaYHora(punto.cuando) }} ·
                            {{ punto.latencia ?? '—' }} ms
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Incidentes</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="incidente in props.incidentes"
                        :key="incidente.id"
                        class="flex items-start justify-between gap-3 text-sm"
                    >
                        <div>
                            <p class="flex items-center gap-2">
                                <span class="font-medium">{{
                                    incidente.tipo
                                }}</span>
                                <Badge
                                    v-if="!incidente.cerrado"
                                    variant="destructive"
                                    >abierto</Badge
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ fechaYHora(incidente.abierto) }} ·
                                {{ incidente.duracion }}
                            </p>
                            <p
                                v-if="incidente.mensaje"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ incidente.mensaje }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="!props.incidentes.length"
                        class="text-sm text-muted-foreground"
                    >
                        Nunca tuvo un incidente.
                    </p>
                </CardContent>
            </Card>
        </div>

        <PanelDocumentos
            :proyecto-slug="props.proyecto.slug"
            :documentos="props.documentos"
        />

        <p class="text-sm">
            <Link
                href="/proyectos"
                class="text-muted-foreground hover:text-foreground"
            >
                Editar este y los demás proyectos
            </Link>
        </p>
    </div>
</template>
