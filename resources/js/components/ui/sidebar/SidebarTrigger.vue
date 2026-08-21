<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <!--
      Hamburguesa, y el mismo ícono en los dos estados.

      No va Menu/X: en escritorio la sidebar es `inset` + `collapsible="icon"`, o sea
      que nunca desaparece —se angosta a la tira de íconos—, así que una cruz
      prometería cerrar algo que sigue ahí. Y en mobile, con el sheet abierto este
      botón queda tapado por el overlay: el que cierra es la X que ya trae
      SheetContent.
    -->
    <Menu />
    <span class="sr-only">Mostrar u ocultar la barra lateral</span>
  </Button>
</template>
