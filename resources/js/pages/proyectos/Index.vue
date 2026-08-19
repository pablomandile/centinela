<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import type { ProyectoEditable } from '@/types/centinela';

const props = defineProps<{ proyectos: ProyectoEditable[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: dashboard() },
            { title: 'Proyectos', href: '/proyectos' },
        ],
    },
});

const abierto = ref(false);
const editando = ref<ProyectoEditable | null>(null);

const form = useForm({
    nombre: '',
    slug: '',
    url: '',
    repo_url: '',
    usa_inertia: false,
    es_pwa: false,
    tiene_bundle: false,
    activo: true,
    palabra_clave: '',
    intervalo_minutos: 15,
    notas: '',
});

function nuevo(): void {
    editando.value = null;
    form.reset();
    form.clearErrors();
    abierto.value = true;
}

function editar(proyecto: ProyectoEditable): void {
    editando.value = proyecto;
    form.clearErrors();
    form.defaults({
        nombre: proyecto.nombre,
        slug: proyecto.slug,
        url: proyecto.url,
        repo_url: proyecto.repo_url ?? '',
        usa_inertia: proyecto.usa_inertia,
        es_pwa: proyecto.es_pwa,
        tiene_bundle: proyecto.tiene_bundle,
        activo: proyecto.activo,
        palabra_clave: proyecto.palabra_clave ?? '',
        intervalo_minutos: proyecto.intervalo_minutos,
        notas: proyecto.notas ?? '',
    });
    form.reset();
    abierto.value = true;
}

function guardar(): void {
    /*
     * `onSuccess` cierra el sheet a mano: la navegación de Inertia no desmonta los
     * overlays, así que sin esto el panel queda abierto tapando la lista ya
     * actualizada. Es el mismo problema del menú mobile (skill
     * `overlays-al-navegar`).
     */
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
        },
    };

    if (editando.value) {
        form.put(`/proyectos/${editando.value.slug}`, opciones);

        return;
    }

    form.post('/proyectos', opciones);
}

function quitar(proyecto: ProyectoEditable): void {
    if (
        !confirm(
            `¿Quitar «${proyecto.nombre}» de la lista? Sus chequeos se conservan.`,
        )
    ) {
        return;
    }

    router.delete(`/proyectos/${proyecto.slug}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Proyectos" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                {{ props.proyectos.length }} cargados. Las banderas deciden qué
                se le chequea a cada uno.
            </p>

            <Button size="sm" @click="nuevo">
                <Plus class="size-4" /> Nuevo
            </Button>
        </div>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="proyecto in props.proyectos"
                :key="proyecto.slug"
                class="flex flex-wrap items-center justify-between gap-3 p-3"
                :class="{ 'opacity-60': !proyecto.activo }"
            >
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2">
                        <a
                            :href="`/proyectos/${proyecto.slug}`"
                            class="font-medium hover:underline"
                        >
                            {{ proyecto.nombre }}
                        </a>
                        <Badge variant="secondary">{{
                            proyecto.tecnologia
                        }}</Badge>
                        <Badge v-if="!proyecto.activo" variant="outline"
                            >inactivo</Badge
                        >
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ proyecto.url }}
                    </p>
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Editar"
                        @click="editar(proyecto)"
                    >
                        <Pencil class="size-4" />
                        <span class="sr-only"
                            >Editar {{ proyecto.nombre }}</span
                        >
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Quitar"
                        @click="quitar(proyecto)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only"
                            >Quitar {{ proyecto.nombre }}</span
                        >
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <!-- Sheet y no dialog: en el celular un formulario largo dentro de un modal
         centrado no se maneja con el pulgar. -->
    <Sheet v-model:open="abierto">
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 sm:max-w-md"
        >
            <SheetHeader>
                <SheetTitle>
                    {{
                        editando
                            ? `Editar ${editando.nombre}`
                            : 'Proyecto nuevo'
                    }}
                </SheetTitle>
                <SheetDescription>
                    La URL tiene que ser la canónica: las rutas absolutas del
                    sitio (/build, /sw.js) resuelven ahí.
                </SheetDescription>
            </SheetHeader>

            <form
                class="flex-1 space-y-4 overflow-y-auto px-4"
                @submit.prevent="guardar"
            >
                <div class="grid gap-2">
                    <Label for="nombre">Nombre</Label>
                    <Input
                        id="nombre"
                        v-model="form.nombre"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.nombre" />
                </div>

                <div class="grid gap-2">
                    <Label for="url">URL</Label>
                    <Input
                        id="url"
                        v-model="form.url"
                        type="url"
                        required
                        placeholder="https://…"
                    />
                    <InputError :message="form.errors.url" />
                </div>

                <div class="grid gap-2">
                    <Label for="slug">Identificador</Label>
                    <Input
                        id="slug"
                        v-model="form.slug"
                        placeholder="se deriva del nombre"
                    />
                    <InputError :message="form.errors.slug" />
                </div>

                <div class="grid gap-2">
                    <Label for="repo_url">Repositorio</Label>
                    <Input id="repo_url" v-model="form.repo_url" type="url" />
                    <InputError :message="form.errors.repo_url" />
                </div>

                <fieldset class="space-y-3 rounded-md border p-3">
                    <legend class="px-1 text-sm font-medium">
                        Qué se le chequea
                    </legend>

                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox v-model="form.usa_inertia" />
                        <span>
                            Usa Inertia
                            <span class="block text-xs text-muted-foreground">
                                audita que el JSON no se pueda guardar
                            </span>
                        </span>
                    </Label>

                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox v-model="form.es_pwa" />
                        <span>
                            Es PWA
                            <span class="block text-xs text-muted-foreground">
                                audita manifest, service worker e íconos
                            </span>
                        </span>
                    </Label>

                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox v-model="form.tiene_bundle" />
                        <span>
                            Compila assets
                            <span class="block text-xs text-muted-foreground">
                                verifica que el JS que pide la página exista
                            </span>
                        </span>
                    </Label>

                    <p class="text-xs text-muted-foreground">
                        Desde el detalle del proyecto se pueden detectar solas.
                    </p>
                </fieldset>

                <div class="grid gap-2">
                    <Label for="palabra_clave">Palabra clave</Label>
                    <Input
                        id="palabra_clave"
                        v-model="form.palabra_clave"
                        placeholder="un texto que tiene que aparecer"
                    />
                    <p class="text-xs text-muted-foreground">
                        Un 200 no alcanza: una pantalla de error también
                        contesta 200.
                    </p>
                    <InputError :message="form.errors.palabra_clave" />
                </div>

                <div class="grid gap-2">
                    <Label for="intervalo">Intervalo (minutos)</Label>
                    <Input
                        id="intervalo"
                        v-model="form.intervalo_minutos"
                        type="number"
                        min="5"
                        max="1440"
                        required
                    />
                    <InputError :message="form.errors.intervalo_minutos" />
                </div>

                <div class="grid gap-2">
                    <Label for="notas">Notas</Label>
                    <textarea
                        id="notas"
                        v-model="form.notas"
                        rows="3"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="form.errors.notas" />
                </div>

                <Label class="flex items-center gap-3 font-normal">
                    <Checkbox v-model="form.activo" />
                    <span>Activo (si no, no se chequea ni avisa)</span>
                </Label>
            </form>

            <SheetFooter>
                <Button :disabled="form.processing" @click="guardar">
                    <Spinner v-if="form.processing" />
                    Guardar
                </Button>
                <Button variant="outline" @click="abierto = false"
                    >Cancelar</Button
                >
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
