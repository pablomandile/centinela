import { onMounted, onUnmounted, ref } from 'vue';

/*
 * El estado de "se puede instalar", leído del objeto que dejó el script inline del
 * `<head>`.
 *
 * Ese script existe porque Chrome dispara `beforeinstallprompt` antes de que monte
 * Vue: escucharlo desde acá directamente no alcanza, el evento ya pasó. Acá solo se
 * lee lo que quedó guardado y se escuchan los eventos propios.
 */

type PromptDeInstalacion = {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

declare global {
    interface Window {
        __pwaInstall?: {
            prompt: PromptDeInstalacion | null;
            installed: boolean;
        };
    }
}

export function useInstalacion() {
    const sePuedeInstalar = ref(false);
    const yaEstaInstalada = ref(false);
    const seUsoElPrompt = ref(false);
    /** iOS Safari nunca dispara `beforeinstallprompt`: ahí la instalación es manual. */
    const esIos = ref(false);

    const revisar = (): void => {
        sePuedeInstalar.value = window.__pwaInstall?.prompt != null;

        yaEstaInstalada.value =
            window.__pwaInstall?.installed === true ||
            window.matchMedia('(display-mode: standalone)').matches ||
            // Safari en iOS no implementa display-mode.
            (window.navigator as unknown as { standalone?: boolean })
                .standalone === true;
    };

    const detectarIos = (): void => {
        const ua = window.navigator.userAgent;

        // iPadOS se reporta como Mac: se lo distingue por los puntos táctiles.
        esIos.value =
            /iphone|ipod|ipad/i.test(ua) ||
            (/macintosh/i.test(ua) && window.navigator.maxTouchPoints > 1);
    };

    const instalar = async (): Promise<void> => {
        const prompt = window.__pwaInstall?.prompt;

        if (!prompt) {
            return;
        }

        /*
         * El prompt se consume una sola vez: después de `prompt()` el evento muere
         * aunque el usuario lo haya descartado. Por eso el botón no se esconde, sino
         * que a partir de acá muestra el instructivo del menú del navegador —en una
         * SPA una recarga completa casi no pasa, y sin esto quien descartó sin querer
         * no puede reintentar—.
         */
        seUsoElPrompt.value = true;
        await prompt.prompt();
        window.__pwaInstall!.prompt = null;
        sePuedeInstalar.value = false;
    };

    const alInstalar = (): void => {
        yaEstaInstalada.value = true;
        sePuedeInstalar.value = false;
    };

    onMounted(() => {
        detectarIos();
        revisar();

        window.addEventListener('pwa:installable', revisar);
        window.addEventListener('pwa:installed', alInstalar);
    });

    onUnmounted(() => {
        window.removeEventListener('pwa:installable', revisar);
        window.removeEventListener('pwa:installed', alInstalar);
    });

    return { sePuedeInstalar, yaEstaInstalada, seUsoElPrompt, esIos, instalar };
}
