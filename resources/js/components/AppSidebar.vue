<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { FileText, FolderGit2, Info, LayoutGrid } from '@lucide/vue';
import { onUnmounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import BotonInstalar from '@/components/BotonInstalar.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Proyectos',
        href: '/proyectos',
        icon: FolderGit2,
    },
    {
        title: 'Documentos',
        href: '/documentos',
        icon: FileText,
    },
    {
        title: 'Acerca de',
        href: '/acerca',
        icon: Info,
    },
];

// Sin links en el pie: los que traía el starter kit apuntaban a su propio
// repositorio y a la documentación de Laravel, que no son de Centinela.
const footerNavItems: NavItem[] = [];

/*
 * En mobile la sidebar es un sheet a pantalla completa, y la navegación de Inertia
 * **no lo desmonta**: elegís una opción, la pantalla nueva carga detrás y el menú
 * queda encima tapándola, con el `body` en `overflow: hidden`. La app funciona
 * perfecto, solo no se ve.
 *
 * Tres decisiones acá, todas del skill `overlays-al-navegar`:
 *
 * 1. **En el router y no en cada enlace**: hay tres grupos de enlaces distintos —el
 *    logo, la navegación y el menú de usuario— y agregando el séptimo se olvida uno
 *    seguro.
 * 2. **Solo en mobile**: en escritorio la sidebar es fija y cerrarla al navegar es
 *    quedarse sin menú a cada paso.
 * 3. **`navigate` y no `start`**: `start` cierra el menú en cuanto tocás, así que
 *    si la visita falla te quedás sin menú y sin página; y dispara también en
 *    cualquier `router.reload()` de fondo.
 */
const { isMobile, setOpenMobile } = useSidebar();

onUnmounted(
    router.on('navigate', () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    }),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!--
                El botón va acá y en un solo lugar. Si estuviera además en el header
                mobile quedaría dos veces en el DOM y cualquier verificación con
                querySelector agarraría el primero, que puede estar oculto (trampa
                del skill `adaptar-a-pwa`, sección 9).
            -->
            <BotonInstalar />
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
