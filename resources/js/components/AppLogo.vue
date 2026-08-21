<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import LogoMarca from '@/components/LogoMarca.vue';

const name = usePage().props.name;
</script>

<template>
    <!--
        Acá va la marca sola y el nombre en texto al lado, no el logotexto.

        No es una excepción caprichosa: la sidebar es `collapsible="icon"`, o sea que
        se angosta a una tira de 32 px donde solo cabe un cuadrado, y el logotexto es
        un logo apilado —escudo, nombre y bajada— que a la altura de una fila de menú
        no se lee. El nombre en texto además se recorta solo cuando falta ancho y
        acompaña al colapso, cosa que una imagen no hace.
    -->
    <LogoMarca class="size-8" />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
