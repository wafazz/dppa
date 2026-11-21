import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

/*
 * Register only the Chart.js parts the dashboard uses, which keeps the bundle small.
 */
Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
);

Chart.defaults.font.family = 'inherit';
Chart.defaults.color = getComputedStyle(document.body).getPropertyValue('--bs-secondary-color') || '#6c757d';

export default Chart;
