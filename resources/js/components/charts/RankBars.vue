<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from './chartSetup';
import { t } from '../../lib/i18n';

/**
 * Horizontal ranked bars (Chart.js). items: [{ name, total }]
 */
const props = defineProps({
    items: { type: Array, required: true },
    color: { type: String, default: '#0d6efd' },
});

const canvas = ref(null);
let chart = null;

function dataFrom(items) {
    return {
        labels: items.map((item) => item.name),
        datasets: [{ label: t('app.dashboard.series_applications'), data: items.map((item) => item.total), backgroundColor: props.color, borderRadius: 4 }],
    };
}

onMounted(() => {
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: dataFrom(props.items),
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 700 },
            plugins: { legend: { display: false }, tooltip: { padding: 10, cornerRadius: 8 } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(127,127,127,0.15)' } },
                y: { grid: { display: false } },
            },
        },
    });
});

watch(
    () => props.items,
    (items) => {
        if (!chart) return;

        chart.data = dataFrom(items);
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
        <canvas ref="canvas" role="img" :aria-label="t('app.dashboard.chart_categories')"></canvas>
    </div>
</template>

<style scoped>
.chart-box { position: relative; height: 260px; width: 100%; }
</style>
