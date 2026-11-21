<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MonthlyBars from '../components/charts/MonthlyBars.vue';
import StatusDonut from '../components/charts/StatusDonut.vue';
import RankBars from '../components/charts/RankBars.vue';
import { api } from '../lib/http';
import { formatDate, formatDateTime } from '../lib/format';
import { dateLocale, t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    currentUser: { type: Object, required: true },
});

const REFRESH_SECONDS = 60;

const data = ref(null);
const months = ref(6);
const loading = ref(false);
const loadError = ref('');
const lastUpdated = ref(null);
const autoRefresh = ref(true);
const secondsToRefresh = ref(REFRESH_SECONDS);

let countdownTimer = null;
let requestSequence = 0;

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) return t('app.dashboard.greeting_morning');
    if (hour < 15) return t('app.dashboard.greeting_afternoon');
    if (hour < 19) return t('app.dashboard.greeting_evening');

    return t('app.dashboard.greeting_night');
});

const todayLabel = new Date().toLocaleDateString(dateLocale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const statusItems = computed(() => {
    if (!data.value?.kpis) return [];

    const kpis = data.value.kpis;

    return [
        { id: 'pending', label: t('app.enums.member_status.pending'), value: kpis.pending, color: '#f0ad4e', href: '/admin/members?status=pending' },
        { id: 'approved', label: t('app.enums.member_status.approved'), value: kpis.approved, color: '#198754', href: '/admin/members?status=approved' },
        { id: 'rejected', label: t('app.enums.member_status.rejected'), value: kpis.rejected, color: '#dc3545', href: '/admin/members?status=rejected' },
    ];
});

const monthlySeries = computed(() => {
    if (!data.value?.monthly) return [];

    return [
        { name: t('app.dashboard.series_applications'), values: data.value.monthly.applications, color: '#0d6efd' },
        { name: t('app.dashboard.series_approvals'), values: data.value.monthly.approvals, color: '#198754' },
    ];
});

const membersVisible = computed(() => data.value?.can_view_members === true);

const kpiCards = computed(() => {
    if (!data.value) return [];

    const kpis = data.value.kpis;
    const cards = [];

    if (membersVisible.value) {
        cards.push(
            { key: 'total', label: t('app.dashboard.kpi_total'), value: kpis.total, note: t('app.dashboard.kpi_total_note', { count: kpis.applied_this_month }), accent: 'primary', href: '/admin/members' },
            { key: 'pending', label: t('app.dashboard.kpi_pending'), value: kpis.pending, note: t('app.dashboard.kpi_pending_note'), accent: 'warning', href: '/admin/members?status=pending' },
            { key: 'approved', label: t('app.dashboard.kpi_approved'), value: kpis.approved, note: t('app.dashboard.kpi_approved_note'), accent: 'success', href: '/admin/members?status=approved' },
        );
    }

    cards.push({
        key: 'positions',
        label: t('app.dashboard.kpi_positions'),
        value: data.value.positions_current,
        note: t('app.dashboard.kpi_positions_note', { count: data.value.positions_total }),
        accent: 'info',
        href: '/admin/positions',
    });

    return cards;
});

async function load() {
    const sequence = ++requestSequence;
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api(`/admin/api/dashboard?months=${months.value}`);

        if (sequence !== requestSequence) return;

        data.value = response.data;
        lastUpdated.value = new Date();
        secondsToRefresh.value = REFRESH_SECONDS;
    } catch (error) {
        if (sequence === requestSequence) loadError.value = error.message;
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

function startTimers() {
    stopTimers();

    countdownTimer = setInterval(() => {
        if (!autoRefresh.value) return;

        secondsToRefresh.value -= 1;

        if (secondsToRefresh.value <= 0) load();
    }, 1000);
}

function stopTimers() {
    clearInterval(countdownTimer);
    countdownTimer = null;
}

watch(months, () => load());

onMounted(() => {
    load();
    startTimers();
});

// Turbo can move to another page at any time: stop timers so they never run on a removed page.
onBeforeUnmount(stopTimers);
</script>

<template>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-hero rounded-4 p-4 mb-4 text-white d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="small text-white-50">{{ todayLabel }}</div>
                <h2 class="h3 mb-1">{{ greeting }}, {{ currentUser.name }}</h2>
                <div class="small text-white-50">
                    <span v-if="currentUser.isSuperadmin">{{ t('app.dashboard.superadmin_access') }}</span>
                    <span v-else>{{ t('app.dashboard.subtitle') }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn" :class="months === 6 ? 'btn-light' : 'btn-outline-light'" @click="months = 6">{{ t('app.dashboard.months_6') }}</button>
                    <button type="button" class="btn" :class="months === 12 ? 'btn-light' : 'btn-outline-light'" @click="months = 12">{{ t('app.dashboard.months_12') }}</button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light" :disabled="loading" @click="load">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ t('app.dashboard.refresh') }}
                </button>
                <div class="form-check form-switch m-0 small">
                    <input id="auto-refresh" v-model="autoRefresh" class="form-check-input" type="checkbox" role="switch">
                    <label class="form-check-label text-white" for="auto-refresh">
                        {{ t('app.dashboard.auto') }} {{ autoRefresh ? `(${secondsToRefresh}s)` : '' }}
                    </label>
                </div>
            </div>
        </div>

        <div v-if="loadError" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.dashboard.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
        </div>

        <!-- Loading placeholders -->
        <div v-if="!data && loading" class="row g-3" aria-busy="true">
            <div v-for="n in 4" :key="n" class="col-12 col-sm-6 col-xl-3">
                <div class="card placeholder-glow h-100"><div class="card-body"><span class="placeholder col-6 mb-3"></span><span class="placeholder col-8 display-6"></span></div></div>
            </div>
        </div>

        <template v-if="data">
            <!-- KPI cards -->
            <div class="row g-3 mb-4">
                <div v-for="card in kpiCards" :key="card.key" class="col-12 col-sm-6 col-xl-3">
                    <a :href="card.href" class="card kpi-card h-100 text-decoration-none" :class="`accent-${card.accent}`">
                        <div class="card-body">
                            <div class="small text-body-secondary text-uppercase fw-semibold">{{ card.label }}</div>
                            <div class="display-6 fw-bold my-1">{{ card.value }}</div>
                            <div class="small text-body-secondary">{{ card.note }}</div>
                        </div>
                    </a>
                </div>
            </div>

            <div v-if="!membersVisible" class="alert alert-info">{{ t('app.dashboard.viewer_notice') }}</div>

            <template v-if="membersVisible">
                <!-- Trend and status -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-xl-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">{{ t('app.dashboard.trend_title') }}</span>
                                <span class="small text-body-secondary">{{ t('app.dashboard.trend_period', { months }) }}</span>
                            </div>
                            <div class="card-body">
                                <MonthlyBars :labels="data.monthly.labels" :series="monthlySeries" />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">{{ t('app.dashboard.status_title') }}</div>
                            <div class="card-body">
                                <StatusDonut :items="statusItems" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories and recent applications -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-xl-5">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">{{ t('app.dashboard.categories_title') }}</div>
                            <div class="card-body">
                                <RankBars v-if="data.categories.length" :items="data.categories" />
                                <p v-else class="text-body-secondary mb-0">{{ t('app.dashboard.categories_empty') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">{{ t('app.dashboard.recent_title') }}</span>
                                <a href="/admin/members" class="small">{{ t('app.dashboard.view_all') }}</a>
                            </div>
                            <div class="list-group list-group-flush">
                                <a v-for="item in data.recent_applications" :key="item.id" :href="`/admin/members/${item.id}`"
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                                    <div class="text-truncate">
                                        <div class="fw-semibold text-truncate">{{ item.applicant_name }}</div>
                                        <div class="small text-body-secondary text-truncate">{{ item.business_name }}</div>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <span class="badge" :class="{
                                            'text-bg-warning': item.status === 'pending',
                                            'text-bg-success': item.status === 'approved',
                                            'text-bg-danger': item.status === 'rejected',
                                        }">{{ item.status_label }}</span>
                                        <div class="small text-body-secondary">{{ formatDate(item.applied_at) }}</div>
                                    </div>
                                </a>
                                <div v-if="!data.recent_applications.length" class="list-group-item text-body-secondary">{{ t('app.dashboard.recent_empty') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity and expiring terms -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">{{ t('app.dashboard.activity_title') }}</div>
                            <ul class="list-group list-group-flush activity-list">
                                <li v-for="log in data.activity" :key="log.created_at + log.member_id" class="list-group-item">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span>
                                            {{ t('app.dashboard.activity_changed', {
                                                name: log.applicant_name ?? t('app.dashboard.activity_member'),
                                                status: log.to_status_label,
                                            }) }}
                                        </span>
                                        <span class="small text-body-secondary text-nowrap">{{ formatDateTime(log.created_at) }}</span>
                                    </div>
                                    <div class="small text-body-secondary">{{ t('app.dashboard.activity_by', { name: log.changed_by ?? t('app.dashboard.activity_system') }) }}</div>
                                </li>
                                <li v-if="!data.activity.length" class="list-group-item text-body-secondary">{{ t('app.dashboard.activity_empty') }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">{{ t('app.dashboard.terms_title') }}</div>
                            <ul class="list-group list-group-flush">
                                <li v-for="term in data.expiring_terms" :key="term.member_id + term.position" class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                    <div class="text-truncate">
                                        <div class="fw-semibold text-truncate">{{ term.position }}</div>
                                        <div class="small text-body-secondary text-truncate">{{ term.applicant_name }}</div>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <span class="badge" :class="term.days_left <= 14 ? 'text-bg-danger' : 'text-bg-warning'">
                                            {{ t('app.dashboard.days_left', { count: term.days_left }) }}
                                        </span>
                                        <div class="small text-body-secondary">{{ formatDate(term.term_end) }}</div>
                                    </div>
                                </li>
                                <li v-if="!data.expiring_terms.length" class="list-group-item text-body-secondary">{{ t('app.dashboard.terms_empty') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="lastUpdated" class="small text-body-secondary text-end">
                {{ t('app.dashboard.updated_at', { time: lastUpdated.toLocaleTimeString(dateLocale, { hour: '2-digit', minute: '2-digit' }) }) }}
            </div>
        </template>
    </div>
</template>

<style scoped>
.dashboard-hero {
    background: linear-gradient(135deg, #0d6efd 0%, #0a4bb0 55%, #0b2e6b 100%);
    box-shadow: 0 1rem 2rem rgba(13, 110, 253, 0.2);
}
.kpi-card {
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    color: inherit;
}
.kpi-card::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: var(--accent);
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.08); }
.accent-primary { --accent: #0d6efd; }
.accent-warning { --accent: #f0ad4e; }
.accent-success { --accent: #198754; }
.accent-info { --accent: #0dcaf0; }
.activity-list { max-height: 360px; overflow-y: auto; }
</style>
