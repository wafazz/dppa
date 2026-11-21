<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppSelect2 from '../components/AppSelect2.vue';
import AppointmentModal from '../components/forms/AppointmentModal.vue';
import NamedItemModal from '../components/forms/NamedItemModal.vue';
import { api, buildQuery } from '../lib/http';
import { formatDate } from '../lib/format';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

const activeTab = ref('positions');
const positions = ref([]);
const currentHolders = ref([]);
const history = ref([]);
const historyMeta = ref(null);
const loadingCurrent = ref(false);
const loadingHistory = ref(false);
const loadError = ref('');

const historyFilters = reactive({ position_id: '', current_only: false, page: 1 });

const showPosition = ref(false);
const positionToEdit = ref(null);
const showAppointment = ref(false);
const appointmentToEdit = ref(null);

const tabs = computed(() => [
    { key: 'positions', label: t('app.positions.tab_positions') },
    { key: 'current', label: t('app.positions.tab_current') },
    { key: 'history', label: t('app.positions.tab_history') },
]);

const filterPositionOptions = computed(() => positions.value.map((position) => ({
    id: position.id,
    text: position.name_bm,
})));

async function loadPositions() {
    try {
        const response = await api('/admin/api/positions');
        positions.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

// Each list ignores responses that are no longer the newest request for that list.
let currentHoldersRequest = 0;
let historyRequest = 0;

async function loadCurrentHolders() {
    const requestId = ++currentHoldersRequest;

    loadingCurrent.value = true;
    loadError.value = '';

    try {
        const response = await api(`/admin/api/appointments?${buildQuery({ current: 1, per_page: 100 })}`);

        if (requestId === currentHoldersRequest) {
            currentHolders.value = response.data;
        }
    } catch (error) {
        if (requestId === currentHoldersRequest) {
            loadError.value = error.message;
        }
    } finally {
        if (requestId === currentHoldersRequest) {
            loadingCurrent.value = false;
        }
    }
}

async function loadHistory() {
    const requestId = ++historyRequest;

    loadingHistory.value = true;
    loadError.value = '';

    try {
        const response = await api(`/admin/api/appointments?${buildQuery({
            position_id: historyFilters.position_id,
            current: historyFilters.current_only ? 1 : '',
            page: historyFilters.page,
            per_page: 15,
        })}`);

        if (requestId === historyRequest) {
            history.value = response.data;
            historyMeta.value = response.meta;
        }
    } catch (error) {
        if (requestId === historyRequest) {
            loadError.value = error.message;
        }
    } finally {
        if (requestId === historyRequest) {
            loadingHistory.value = false;
        }
    }
}

function refreshActiveTab() {
    if (activeTab.value === 'current') {
        loadCurrentHolders();
    } else if (activeTab.value === 'history') {
        loadHistory();
    }
}

function refreshAll() {
    loadPositions();
    refreshActiveTab();
}

function openPosition(position = null) {
    positionToEdit.value = position;
    showPosition.value = true;
}

function openAppointment(appointment = null) {
    appointmentToEdit.value = appointment;
    showAppointment.value = true;
}

function applyHistoryFilters() {
    historyFilters.page = 1;
    loadHistory();
}

function goToHistoryPage(page) {
    if (!historyMeta.value || page < 1 || page > historyMeta.value.last_page) {
        return;
    }

    historyFilters.page = page;
    loadHistory();
}

watch(activeTab, refreshActiveTab);

onMounted(() => {
    // Sidebar submenus link here with ?tab=current or ?tab=history.
    const tab = new URLSearchParams(window.location.search).get('tab');

    if (tab === 'current' || tab === 'history') {
        activeTab.value = tab;
    }

    loadPositions();
    loadHistory();
});
</script>

<template>
    <div>
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item" role="presentation">
                <button type="button" role="tab" class="nav-link" :class="{ active: activeTab === tab.key }"
                        :aria-selected="activeTab === tab.key" @click="activeTab = tab.key">
                    {{ tab.label }}
                </button>
            </li>
        </ul>

        <div v-if="loadError" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.positions.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="refreshAll">{{ t('app.common.retry') }}</button>
        </div>

        <!-- Positions -->
        <div v-if="activeTab === 'positions'" class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ t('app.positions.list_title') }}</span>
                <button v-if="can.managePositions" type="button" class="btn btn-sm btn-primary" @click="openPosition()">{{ t('app.positions.add') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.positions.name_bm') }}</th>
                            <th>{{ t('app.positions.name_en') }}</th>
                            <th>{{ t('app.positions.order') }}</th>
                            <th>{{ t('app.positions.status') }}</th>
                            <th v-if="can.managePositions"><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!positions.length">
                            <td :colspan="can.managePositions ? 5 : 4" class="text-center py-4 text-body-secondary">{{ t('app.positions.empty') }}</td>
                        </tr>
                        <tr v-for="position in positions" :key="position.id">
                            <td class="fw-semibold">{{ position.name_bm }}</td>
                            <td>{{ position.name_en ?? '—' }}</td>
                            <td>{{ position.sort_order }}</td>
                            <td>
                                <span class="badge" :class="position.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ position.is_active ? t('app.common.active') : t('app.common.inactive') }}
                                </span>
                            </td>
                            <td v-if="can.managePositions" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="openPosition(position)">{{ t('app.common.edit') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Current holders -->
        <div v-else-if="activeTab === 'current'" class="card">
            <div class="card-header fw-semibold">{{ t('app.positions.current_title') }}</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.positions.col_position') }}</th>
                            <th>{{ t('app.positions.col_member') }}</th>
                            <th>{{ t('app.positions.col_business') }}</th>
                            <th>{{ t('app.positions.col_start') }}</th>
                            <th>{{ t('app.positions.col_end') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadingCurrent">
                            <td colspan="5" class="text-center py-4">{{ t('app.positions.loading') }}</td>
                        </tr>
                        <tr v-else-if="!currentHolders.length">
                            <td colspan="5" class="text-center py-4 text-body-secondary">{{ t('app.positions.current_empty') }}</td>
                        </tr>
                        <template v-else>
                            <tr v-for="appointment in currentHolders" :key="appointment.id">
                                <td class="fw-semibold">{{ appointment.chamber_position?.name_bm }}</td>
                                <td>
                                    <a :href="`/admin/members/${appointment.member?.id}`">{{ appointment.member?.applicant_name }}</a>
                                    <div class="small text-body-secondary">{{ appointment.member?.member_no }}</div>
                                </td>
                                <td>{{ appointment.member?.business_name }}</td>
                                <td>{{ formatDate(appointment.term_start) }}</td>
                                <td>{{ formatDate(appointment.term_end) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History -->
        <div v-else class="card">
            <div class="card-header">
                <form class="row g-2 align-items-end" novalidate @submit.prevent="applyHistoryFilters">
                    <div class="col-12 col-md-5">
                        <label class="form-label small mb-1" for="history-position">{{ t('app.positions.filter_position') }}</label>
                        <AppSelect2 id="history-position" v-model="historyFilters.position_id" :options="filterPositionOptions" :placeholder="t('app.positions.filter_all')" />
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-check mt-4">
                            <input id="history-current" v-model="historyFilters.current_only" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="history-current">{{ t('app.positions.current_only') }}</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">{{ t('app.common.apply') }}</button>
                        <button v-if="can.manageAppointments" type="button" class="btn btn-success flex-fill" @click="openAppointment()">{{ t('app.positions.appoint') }}</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.positions.col_position') }}</th>
                            <th>{{ t('app.positions.col_member') }}</th>
                            <th>{{ t('app.positions.col_start') }}</th>
                            <th>{{ t('app.positions.col_end') }}</th>
                            <th>{{ t('app.positions.col_term') }}</th>
                            <th v-if="can.manageAppointments"><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadingHistory">
                            <td colspan="6" class="text-center py-4">{{ t('app.positions.loading') }}</td>
                        </tr>
                        <tr v-else-if="!history.length">
                            <td colspan="6" class="text-center py-4 text-body-secondary">{{ t('app.positions.history_empty') }}</td>
                        </tr>
                        <template v-else>
                            <tr v-for="appointment in history" :key="appointment.id" :class="{ 'table-success': appointment.is_current }">
                                <td class="fw-semibold">{{ appointment.chamber_position?.name_bm }}</td>
                                <td>
                                    <a :href="`/admin/members/${appointment.member?.id}`">{{ appointment.member?.applicant_name }}</a>
                                </td>
                                <td>{{ formatDate(appointment.term_start) }}</td>
                                <td>{{ formatDate(appointment.term_end) }}</td>
                                <td>
                                    <span v-if="appointment.is_current" class="badge text-bg-success">{{ t('app.positions.badge_current') }}</span>
                                    <span v-else class="badge text-bg-secondary">{{ t('app.positions.badge_history') }}</span>
                                </td>
                                <td v-if="can.manageAppointments" class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openAppointment(appointment)">{{ t('app.common.edit') }}</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div v-if="historyMeta && historyMeta.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="historyMeta.current_page <= 1"
                        @click="goToHistoryPage(historyMeta.current_page - 1)">{{ t('app.common.previous') }}</button>
                <span class="small">{{ t('app.common.page_of', { current: historyMeta.current_page, last: historyMeta.last_page }) }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="historyMeta.current_page >= historyMeta.last_page"
                        @click="goToHistoryPage(historyMeta.current_page + 1)">{{ t('app.common.next') }}</button>
            </div>
        </div>

        <NamedItemModal
            :open="showPosition"
            :title="positionToEdit ? t('app.positions.edit_title') : t('app.positions.new_title')"
            endpoint="/admin/api/positions"
            :item="positionToEdit"
            @close="showPosition = false"
            @saved="refreshAll"
        />
        <AppointmentModal
            :open="showAppointment"
            :appointment="appointmentToEdit"
            :positions="positions"
            @close="showAppointment = false"
            @saved="refreshActiveTab"
        />
    </div>
</template>
