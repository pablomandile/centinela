/*
 * Los tipos que viajan del backend a las pantallas.
 *
 * Están acá y no repetidos en cada `defineProps` porque el tablero, el detalle y
 * el mail miran los mismos datos, y un chequeo con una clave distinta en cada
 * pantalla es la forma más fácil de romper una sin darse cuenta.
 */

export type EstadoChequeo = 'ok' | 'advertencia' | 'falla';

export type ChequeoResumido = {
    tipo: string;
    etiqueta: string;
    estado: EstadoChequeo;
    mensaje: string | null;
    latencia: number | null;
    /** ISO 8601 en UTC: lo convierte dayjs en el navegador. */
    cuando: string;
};

export type ProyectoDelTablero = {
    slug: string;
    nombre: string;
    url: string;
    activo: boolean;
    tecnologia: string;
    chequeos: ChequeoResumido[];
    /** Cuándo vuelve a chequearse la salud. Null en un proyecto inactivo. */
    proximo: string | null;
    incidentes: number;
};

export type ChequeoDetallado = ChequeoResumido & {
    descripcion: string;
    codigo: number | null;
    detalle: Record<string, unknown> | null;
};

export type IncidenteResumido = {
    id: number;
    tipo: string;
    abierto: string;
    cerrado: string | null;
    duracion: string;
    mensaje: string | null;
};

export type PuntoDeLatencia = {
    cuando: string;
    latencia: number | null;
    estado: EstadoChequeo;
};

export type ProyectoEditable = {
    slug: string;
    nombre: string;
    url: string;
    repo_url: string | null;
    usa_inertia: boolean;
    es_pwa: boolean;
    tiene_bundle: boolean;
    activo: boolean;
    palabra_clave: string | null;
    intervalo_minutos: number;
    notas: string | null;
    tecnologia: string;
};

export type DocumentoResumido = {
    slug: string;
    titulo: string;
    formato: 'md' | 'pdf';
    nombre_original: string;
    tamano: string;
    actualizado: string | null;
};

export type DocumentoEncontrado = {
    slug: string;
    titulo: string;
    formato: 'md' | 'pdf';
    tamano: string;
    proyecto: string;
    proyectoSlug: string;
    fragmento: string | null;
    actualizado: string | null;
};

export type EntradaDeIndice = {
    nivel: number;
    titulo: string;
    ancla: string;
};
