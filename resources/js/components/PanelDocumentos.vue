<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Download, FileText, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useFecha } from '@/composables/useFecha';
import type { DocumentoResumido } from '@/types/centinela';

const props = defineProps<{
    proyectoSlug: string;
    documentos: DocumentoResumido[];
}>();

const { haceCuanto } = useFecha();

const entrada = ref<HTMLInputElement | null>(null);
const form = useForm<{ archivos: File[] }>({ archivos: [] });

function elegidos(evento: Event): void {
    const archivos = (evento.target as HTMLInputElement).files;

    if (!archivos?.length) {
        return;
    }

    form.archivos = Array.from(archivos);

    form.post(`/proyectos/${props.proyectoSlug}/documentos`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();

            // El input se limpia a mano: sin esto, volver a elegir el mismo archivo
            // no dispara `change` y parece que la subida se ignoró.
            if (entrada.value) {
                entrada.value.value = '';
            }
        },
    });
}

function quitar(documento: DocumentoResumido): void {
    if (
        !confirm(
            `¿Eliminar «${documento.titulo}»? El archivo se borra del servidor.`,
        )
    ) {
        return;
    }

    router.delete(
        `/proyectos/${props.proyectoSlug}/documentos/${documento.slug}`,
        {
            preserveScroll: true,
        },
    );
}

const tieneMarkdown = () =>
    props.documentos.some((documento) => documento.formato === 'md');
</script>

<template>
    <Card>
        <CardHeader class="flex-row items-center justify-between gap-2 pb-2">
            <CardTitle class="text-base">Documentación</CardTitle>

            <div class="flex items-center gap-2">
                <Button
                    v-if="tieneMarkdown()"
                    as-child
                    variant="outline"
                    size="sm"
                    title="Todos los markdown en un PDF"
                >
                    <a :href="`/proyectos/${props.proyectoSlug}/dossier`">
                        <Download class="size-4" /> Dossier
                    </a>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                    @click="entrada?.click()"
                >
                    <Spinner v-if="form.processing" />
                    <Upload v-else class="size-4" />
                    Subir
                </Button>
            </div>
        </CardHeader>

        <CardContent class="space-y-3">
            <!--
                `multiple` y sin `capture`: la documentación de un proyecto son
                cinco o seis archivos, y subirlos de a uno con el celular en la mano
                es lo que garantiza que no se suban nunca.
            -->
            <input
                ref="entrada"
                type="file"
                class="sr-only"
                multiple
                accept=".md,.markdown,.pdf"
                @change="elegidos"
            />

            <InputError :message="form.errors.archivos" />
            <InputError :message="form.errors['archivos.0']" />

            <ul v-if="props.documentos.length" class="divide-y">
                <li
                    v-for="documento in props.documentos"
                    :key="documento.slug"
                    class="flex items-center justify-between gap-3 py-2 first:pt-0"
                >
                    <a
                        :href="`/proyectos/${props.proyectoSlug}/documentos/${documento.slug}`"
                        class="flex min-w-0 items-center gap-2"
                    >
                        <FileText
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">
                                {{ documento.titulo }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                {{ documento.nombre_original }} ·
                                {{ documento.tamano }} ·
                                {{ haceCuanto(documento.actualizado) }}
                            </span>
                        </span>
                    </a>

                    <div class="flex shrink-0 items-center gap-1">
                        <Badge variant="secondary" class="uppercase">
                            {{ documento.formato }}
                        </Badge>

                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            title="Bajar el original"
                        >
                            <a
                                :href="`/proyectos/${props.proyectoSlug}/documentos/${documento.slug}/descargar`"
                            >
                                <Download class="size-4" />
                                <span class="sr-only"
                                    >Bajar {{ documento.titulo }}</span
                                >
                            </a>
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon"
                            title="Eliminar"
                            @click="quitar(documento)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only"
                                >Eliminar {{ documento.titulo }}</span
                            >
                        </Button>
                    </div>
                </li>
            </ul>

            <p v-else class="text-sm text-muted-foreground">
                Todavía no hay documentos. Se pueden subir varios `.md` o `.pdf`
                de una.
            </p>
        </CardContent>
    </Card>
</template>
