<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from './chartSetup';
import { t } from '../../lib/i18n';

/**
 * Status doughnut (Chart.js). Clicking a slice opens the matching filtered list through Turbo.
 * items: [{ id, label, value, color, href }]
 */
const props = defineProps({
    items: { type: Array, required: true },
});

const canvas = ref(null);
let chart = null;

const total = computed(() => props.items.reduce((sum, item) => sum + item.value, 0));

function dataFrom(items) {
    return {
        labels: items.map((item) => item.label),
        datasets: [{
            data: items.map((item) => item.value),
            backgroundColor: items.map((item) => item.color),
            borderWidth: 0,
            hoverOffset: 8,
        }],
    };
}

function percent(value) {
    return total.value === 0 ? 0 : Math.round((value / total.value) * 100);
}

function openItem(index) {
    const href = props.items[index]?.href;

    if (!href) return;

    if (window.Turbo) {
        window.Turbo.visit(href);
    } else {
        window.location.href = href;
    }
}

onMounted(() => {
    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: dataFrom(props.items),
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '68%',
            animation: { duration: 700 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (context) => ` ${context.label}: ${context.parsed} (${percent(context.parsed)}%)`,
                    },
                },
            },
            onClick: (_event, elements) => {
                if (elements.length) openItem(elements[0].index);
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
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
    <div class="status-donut">
        <div class="position-relative mx-auto" style="max-width: 240px">
            <canvas ref="canvas" role="img" :aria-label="t('app.dashboard.chart_status')"></canvas>
            <div class="donut-centre text-center">
                <div class="display-6 fw-bold mb-0">{{ total }}</div>
                <div class="small text-body-secondary">{{ t('app.dashboard.status_total') }}</div>
            </div>
        </div>

        <ul class="list-unstyled mt-3 mb-0">
            <li v-for="item in items" :key="item.id" class="mb-2">
                <a :href="item.href" class="legend-row d-flex align-items-center gap-2 text-decoration-none text-body">
                    <span class="legend-dot" :style="{ background: item.color }"></span>
                    <span class="flex-grow-1">{{ item.label }}</span>
                    <span class="fw-semibold">{{ item.value }}</span>
                    <span class="small text-body-secondary">{{ percent(item.value) }}%</span>
                </a>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.donut-centre {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.legend-row { padding: 0.25rem 0.5rem; border-radius: 0.375rem; transition: background 0.15s ease; }
.legend-row:hover { background: var(--bs-tertiary-bg); }
</style>
