<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * El logo completo: escudo, "Centinela" y la bajada. Va donde hay lugar para la
 * marca entera —el login y Acerca de—, no en la sidebar: es un logo apilado en tres
 * niveles y a la altura de una fila de menú no se lee nada.
 *
 * Mismo motivo que en LogoMarca para la placa clara: "Centinela" está en azul marino
 * (#002350) sobre transparente y sobre el tema oscuro tiene 1,2:1 de contraste.
 */
const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex rounded-xl bg-white p-4 ring-1 ring-black/5 dark:ring-white/10',
                props.class,
            )
        "
    >
        <img
            src="/img/logotexto.png?v=2"
            alt="Centinela"
            class="h-auto w-full object-contain"
            width="512"
            height="590"
        />
    </span>
</template>
