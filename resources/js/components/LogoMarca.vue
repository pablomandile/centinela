<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * La marca sola: el escudo con el faro, sin texto. Es la forma compacta del logo,
 * para donde no entra el logotexto (el pie de la sidebar, el header mobile).
 *
 * La placa clara no es decoración. Los dos logos vienen con **fondo transparente** y
 * el faro es azul marino (#002B5E): sobre el tema oscuro de la app la torre queda
 * con menos de 2:1 de contraste y se ve un escudo vacío. Con la placa el logo se lee
 * igual en los dos temas y no hace falta mantener una segunda versión del archivo.
 * En el tema claro el fondo ya es casi blanco, así que apenas se nota.
 *
 * El `?v=` es la misma versión que los íconos: el borde de Hostinger cachea siete
 * días por URL fija, así que cambiar el archivo sin cambiar la URL no se ve.
 */
const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex aspect-square items-center justify-center rounded-md bg-white p-0.5 ring-1 ring-black/5 dark:ring-white/10',
                props.class,
            )
        "
    >
        <!-- alt vacío: al lado va el nombre en texto y un lector de pantalla lo diría dos veces. -->
        <img
            src="/img/logo.png?v=2"
            alt=""
            class="size-full object-contain"
            width="256"
            height="256"
        />
    </span>
</template>
