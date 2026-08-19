<script setup lang="ts">
import { computed } from 'vue';

/*
 * El semáforo de un chequeo o de un proyecto entero.
 *
 * Nunca es solo color: lleva su texto al lado o su `title`. Un tablero que
 * distingue "bien" de "caído" únicamente por el tono del punto no se puede usar
 * con daltonismo ni se entiende impreso en blanco y negro.
 */
type Estado = 'ok' | 'advertencia' | 'falla' | null;

const props = withDefaults(
    defineProps<{
        estado: Estado;
        conTexto?: boolean;
        tamano?: 'sm' | 'md';
    }>(),
    { conTexto: false, tamano: 'md' },
);

const config = computed(() => {
    switch (props.estado) {
        case 'ok':
            return { color: 'bg-emerald-500', texto: 'Bien' };
        case 'advertencia':
            return { color: 'bg-amber-500', texto: 'Atención' };
        case 'falla':
            return { color: 'bg-red-500', texto: 'Falla' };
        default:
            // Sin chequeos todavía **no** es lo mismo que estar bien: es no haber
            // mirado. Por eso tiene su propio gris y su propio texto.
            return { color: 'bg-muted-foreground/40', texto: 'Sin datos' };
    }
});
</script>

<template>
    <span class="inline-flex items-center gap-2" :title="config.texto">
        <span
            class="shrink-0 rounded-full"
            :class="[
                config.color,
                props.tamano === 'sm' ? 'size-2' : 'size-2.5',
            ]"
        />
        <span v-if="props.conTexto" class="text-sm">{{ config.texto }}</span>
        <span v-else class="sr-only">{{ config.texto }}</span>
    </span>
</template>
