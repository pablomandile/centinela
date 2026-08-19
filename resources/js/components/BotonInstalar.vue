<script setup lang="ts">
import { Download, Share } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useInstalacion } from '@/composables/useInstalacion';

const { sePuedeInstalar, yaEstaInstalada, seUsoElPrompt, esIos, instalar } =
    useInstalacion();

const instructivo = ref(false);

/*
 * El botón se muestra aunque no haya prompt: en iOS nunca hay, y quien descartó el
 * de Chrome sin querer necesita poder reintentar. Lo único que lo esconde es que la
 * app ya esté instalada.
 */
const conInstructivo = computed(
    () => esIos.value || (!sePuedeInstalar.value && seUsoElPrompt.value),
);

async function alHacerClick(): Promise<void> {
    if (conInstructivo.value || !sePuedeInstalar.value) {
        instructivo.value = true;

        return;
    }

    await instalar();
}
</script>

<template>
    <Button
        v-if="!yaEstaInstalada"
        variant="ghost"
        size="sm"
        class="w-full justify-start gap-2 text-muted-foreground hover:text-foreground"
        data-test="boton-instalar"
        @click="alHacerClick"
    >
        <Download class="size-4" />
        <span class="group-data-[collapsible=icon]:hidden"
            >Instalar la app</span
        >
    </Button>

    <Dialog v-model:open="instructivo">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Instalar Centinela</DialogTitle>
                <DialogDescription>
                    Queda como una app más, con su ícono y sin la barra del
                    navegador.
                </DialogDescription>
            </DialogHeader>

            <div v-if="esIos" class="space-y-3 text-sm">
                <p>En iPhone y iPad se instala desde Safari, a mano:</p>
                <ol
                    class="list-inside list-decimal space-y-1 text-muted-foreground"
                >
                    <li class="flex items-center gap-1.5">
                        Tocá <Share class="size-4" /> Compartir, abajo en el
                        medio.
                    </li>
                    <li>Bajá hasta «Agregar a inicio».</li>
                    <li>Confirmá con «Agregar».</li>
                </ol>
                <p class="text-xs text-muted-foreground">
                    Safari no ofrece un botón para esto: es la única forma que
                    hay.
                </p>
            </div>

            <div v-else class="space-y-3 text-sm">
                <p>Desde el menú del navegador:</p>
                <ol
                    class="list-inside list-decimal space-y-1 text-muted-foreground"
                >
                    <li>Abrí el menú (⋮ arriba a la derecha).</li>
                    <li>
                        Elegí «Instalar Centinela» o «Agregar a la pantalla de
                        inicio».
                    </li>
                </ol>
                <p class="text-xs text-muted-foreground">
                    Aparece acá porque el navegador ya usó su aviso de
                    instalación una vez y no lo vuelve a ofrecer hasta recargar
                    la página.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
