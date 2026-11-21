<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from './chartSetup';
import { t } from '../../lib/i18n';

/**
 * Grouped monthly bars (Chart.js). series: [{ name, values, color }]
 */
const props = defineProps({
    labels: { type: Array, required: true },
    series: { type: Array, required: true },
});

const canvas = ref(null);
let chart = null;

function datasetsFrom(series) {
    return series.map((serie) => ({
        label: serie.name,
        data: serie.values,
        backgroundColor: serie.color,
        borderRadius: 4,
        maxBarThickness: 28,
    }));
}

onMounted(() => {
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: { labels: props.labels, datasets: datasetsFrom(props.series) },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 700 },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12 } },
                tooltip: { padding: 10, cornerRadius: 8 },
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(127,127,127,0.15)' } },
            },
        },
    });
});

watch(
    () => [props.labels, props.series],
    () => {
        if (!chart) return;

        chart.data.labels = props.labels;
        chart.data.datasets = datasetsFrom(props.series);
        chart.update();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="chart-box">
        <canvas ref="canvas" role="img" :aria-label="t('app.dashboard.chart_trend')"></canvas>
    </div>
</template>

<style scoped>
.chart-box { position: relative; height: 300px; width: 100%; }
</style>
