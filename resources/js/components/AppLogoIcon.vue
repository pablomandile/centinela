<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!--
        Un ojo abierto: un centinela es alguien que mira. Es el mismo dibujo que los
        íconos de la PWA (ver scripts/generar-iconos.php), acá en SVG para que escale
        y tome el color del tema.

        El punto es verde fijo y no `currentColor` a propósito: es el mismo verde del
        semáforo "bien" del tablero, y en el logo funciona como guiño a eso.
    -->
    <svg
        v-bind="$attrs"
        :class="className"
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
    >
        <path
            d="M12 5C6.8 5 2.6 8.1 1 12c1.6 3.9 5.8 7 11 7s9.4-3.1 11-7c-1.6-3.9-5.8-7-11-7Zm0 2c3.9 0 7.3 2 8.8 5-1.5 3-4.9 5-8.8 5s-7.3-2-8.8-5c1.5-3 4.9-5 8.8-5Z"
            fill="currentColor"
        />
        <path
            d="M12 8.2a3.8 3.8 0 1 0 0 7.6 3.8 3.8 0 0 0 0-7.6Zm0 2a1.8 1.8 0 1 1 0 3.6 1.8 1.8 0 0 1 0-3.6Z"
            fill="currentColor"
        />
        <circle cx="14.6" cy="9.7" r="1.5" fill="#10b981" />
    </svg>
</template>
