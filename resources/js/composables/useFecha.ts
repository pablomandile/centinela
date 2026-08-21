import dayjs from 'dayjs';
import 'dayjs/locale/es';
import relativeTime from 'dayjs/plugin/relativeTime';
import utc from 'dayjs/plugin/utc';

/*
 * El backend manda todo en UTC con formato ISO 8601 y la conversión a hora local
 * la hace acá el navegador. Es a propósito distinto de huella —que guarda una zona
 * por usuario— porque Centinela es de una sola persona: dejar que el dispositivo
 * decida siempre acierta y no hay nada que mantener.
 */
dayjs.extend(utc);
dayjs.extend(relativeTime);
dayjs.locale('es');

export function useFecha() {
    /** "hace 3 minutos" */
    const haceCuanto = (iso: string | null | undefined): string =>
        iso ? dayjs.utc(iso).local().fromNow() : '—';

    /** "19 de agosto, 22:36" */
    const fechaYHora = (iso: string | null | undefined): string =>
        iso ? dayjs.utc(iso).local().format('D [de] MMMM, HH:mm') : '—';

    /**
     * "en 12 minutos", para una fecha futura.
     *
     * Es el mismo `fromNow()` que `haceCuanto` —dayjs distingue solo el signo—,
     * pero con su propio nombre: en la plantilla, `enCuanto(proximo)` dice qué
     * significa el número y `haceCuanto(proximo)` se leería como un error.
     */
    const enCuanto = (iso: string | null | undefined): string =>
        iso ? dayjs.utc(iso).local().fromNow() : '—';

    /** "22:36" */
    const hora = (iso: string | null | undefined): string =>
        iso ? dayjs.utc(iso).local().format('HH:mm') : '—';

    return { haceCuanto, enCuanto, fechaYHora, hora };
}
