<script setup lang="ts">
import { computed } from 'vue';

/*
 * El semáforo de un chequeo o de un proyecto entero.
 *
 * Nunca es solo color: lleva su texto al lado o su `title`. Un tablero que
 * distingue "bien" de "caído" únicamente por el tono del punto no se puede usar
 * con daltonismo ni se entiende impreso en blanco y negro.
 *
 * Son **cinco** estados y no tres, porque los dos grises significan cosas
 * opuestas: "inactivo" es un proyecto que no se chequea a propósito y "sin datos"
 * es uno activo que todavía nadie miró. El mapeo de color y texto vive acá y en un
 * solo lugar, así la píldora de la tarjeta y los puntitos de la lista no se van a
 * decir cosas distintas.
 */
type Estado = 'ok' | 'advertencia' | 'falla' | 'inactivo' | null;

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
        case 'inactivo':
            // Apagado a propósito: no se chequea y no genera avisos.
            return { color: 'bg-muted-foreground/30', texto: 'Inactivo' };
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
        <span
            v-if="props.conTexto"
            :class="props.tamano === 'sm' ? 'text-xs' : 'text-sm'"
            >{{ config.texto }}</span
        >
        <span v-else class="sr-only">{{ config.texto }}</span>
    </span>
</template>
