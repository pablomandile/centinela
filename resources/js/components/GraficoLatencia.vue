<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { useFecha } from '@/composables/useFecha';
import type { PuntoDeLatencia } from '@/types/centinela';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
);

const props = defineProps<{ puntos: PuntoDeLatencia[] }>();

const { hora, fechaYHora } = useFecha();

const color = (estado: string): string =>
    estado === 'falla'
        ? '#ef4444'
        : estado === 'advertencia'
          ? '#f59e0b'
          : '#10b981';

const datos = computed(() => ({
    labels: props.puntos.map((punto) => hora(punto.cuando)),
    datasets: [
        {
            label: 'Latencia (ms)',
            data: props.puntos.map((punto) => punto.latencia),
            borderColor: '#71717a',
            backgroundColor: 'rgba(113, 113, 122, 0.08)',
            borderWidth: 1.5,
            fill: true,
            tension: 0.25,
            // Un punto por estado: un pico de latencia y una caída se leen distinto,
            // y en una línea gris sola serían lo mismo.
            pointBackgroundColor: props.puntos.map((punto) =>
                color(punto.estado),
            ),
            pointBorderColor: props.puntos.map((punto) => color(punto.estado)),
            pointRadius: props.puntos.map((punto) =>
                punto.estado === 'ok' ? 2 : 4,
            ),
        },
    ],
}));

const opciones = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items: { dataIndex: number }[]) =>
                    fechaYHora(props.puntos[items[0]!.dataIndex]?.cuando),
                label: (item: { raw: unknown }) => `${item.raw ?? '—'} ms`,
            },
        },
    },
    scales: {
        // Arranca en cero, al contrario que la curva de peso de huella: acá el cero
        // es una referencia real y exagerar la pendiente haría ver como un problema
        // una diferencia de 30 ms.
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { ticks: { maxTicksLimit: 8 } },
    },
}));
</script>

<template>
    <div class="h-40">
        <Line
            v-if="props.puntos.length > 1"
            :data="datos"
            :options="opciones"
        />
        <p v-else class="py-6 text-sm text-muted-foreground">
            Todavía no hay suficientes chequeos para dibujar la curva.
        </p>
    </div>
</template>
