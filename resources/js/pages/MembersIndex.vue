<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppSelect2 from '../components/AppSelect2.vue';
import { api, buildQuery } from '../lib/http';
import { formatDate, statusBadgeClass, statusOptions } from '../lib/format';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

const SORTABLE_COLUMNS = [
    { key: 'member_no', label: t('app.members.columns.member_no') },
    { key: 'applicant_name', label: t('app.members.columns.applicant') },
    { key: 'business_name', label: t('app.members.columns.business') },
    { key: 'status', label: t('app.members.columns.status') },
    { key: 'applied_at', label: t('app.members.columns.applied') },
    { key: 'approved_at', label: t('app.members.columns.approved') },
];

const STATUS_CHOICES = statusOptions();

const PER_PAGE_OPTIONS = [10, 15, 25, 50].map((count) => ({
    id: String(count),
    text: t('app.common.per_page', { count }),
}));

const filters = reactive({ q: '', status: '', category_id: '', services: '' });
const sort = reactive({ column: 'applied_at', direction: 'desc' });
const paging = reactive({ page: 1, perPage: '15' });

const rows = ref([]);
const meta = ref(null);
const categories = ref([]);
const loading = ref(false);
const loadError = ref('');

const categoryOptions = computed(() => categories.value
    .filter((category) => category.is_active)
    .map((category) => ({ id: category.id, text: category.name_bm })));

const hasResults = computed(() => rows.value.length > 0);

function currentQuery() {
    return {
        q: filters.q.trim(),
        status: filters.status,
        category_id: filters.category_id,
        services: filters.services.trim(),
        sort: sort.column,
        direction: sort.direction,
        page: paging.page,
        per_page: paging.perPage,
    };
}

async function loadCategories() {
    try {
        const response = await api('/admin/api/categories');
        categories.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

// Only the newest request may update the table, so a slow earlier response cannot overwrite it.
let latestRequest = 0;

async function load() {
    const requestId = ++latestRequest;

    loading.value = true;
    loadError.value = '';

    try {
        const response = await api(`/admin/api/members?${buildQuery(currentQuery())}`);

        if (requestId !== latestRequest) {
            return;
        }

        rows.value = response.data;
        meta.value = response.meta;
    } catch (error) {
        if (requestId !== latestRequest) {
            return;
        }

        rows.value = [];
        meta.value = null;
        loadError.value = error.message;
    } finally {
        if (requestId === latestRequest) {
            loading.value = false;
        }
    }
}

function search() {
    paging.page = 1;
    load();
}

function resetFilters() {
    Object.assign(filters, { q: '', status: '', category_id: '', services: '' });
    search();
}

function toggleSort(column) {
    if (sort.column === column) {
        sort.direction = sort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        sort.column = column;
        sort.direction = 'asc';
    }

    paging.page = 1;
    load();
}

function sortIndicator(column) {
    if (sort.column !== column) {
        return '';
    }

    return sort.direction === 'asc' ? ' ▲' : ' ▼';
}

function goToPage(page) {
    if (!meta.value || page < 1 || page > meta.value.last_page) {
        return;
    }

    paging.page = page;
    load();
}

function exportCsv() {
    const { page, per_page: perPage, sort: sortColumn, direction, ...exportFilters } = currentQuery();

    window.location.href = `/admin/api/members/export?${buildQuery(exportFilters)}`;
}

watch(() => paging.perPage, () => search());

onMounted(() => {
    // Dashboard cards link here with ?status=... so the list opens already filtered.
    const params = new URLSearchParams(window.location.search);

    if (STATUS_CHOICES.some((option) => option.id === params.get('status'))) {
        filters.status = params.get('status');
    }

    loadCategories();
    load();
});
</script>

<template>
    <div>
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" novalidate @submit.prevent="search">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label small mb-1" for="filter-q">{{ t('app.members.search_label') }}</label>
                        <input id="filter-q" v-model="filters.q" type="search" class="form-control" :placeholder="t('app.members.search_placeholder')">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1" for="filter-status">{{ t('app.members.status') }}</label>
                        <AppSelect2 id="filter-status" v-model="filters.status" :options="STATUS_CHOICES" :placeholder="t('app.common.all_statuses')" />
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="filter-category">{{ t('app.members.category') }}</label>
                        <AppSelect2 id="filter-category" v-model="filters.category_id" :options="categoryOptions" :placeholder="t('app.common.all_categories')" />
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="filter-services">{{ t('app.members.services') }}</label>
                        <input id="filter-services" v-model="filters.services" type="search" class="form-control" :placeholder="t('app.members.services_placeholder')">
                    </div>
                    <div class="col-12 col-xl-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">{{ t('app.common.search') }}</button>
                        <button type="button" class="btn btn-outline-secondary" @click="resetFilters">{{ t('app.common.reset') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="small text-body-secondary" aria-live="polite">
                    <span v-if="meta">{{ t('app.members.summary_range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total }) }}</span>
                    <span v-else>{{ t('app.members.summary_plain') }}</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div style="min-width: 190px">
                        <AppSelect2 v-model="paging.perPage" :options="PER_PAGE_OPTIONS" :placeholder="t('app.members.per_page')" />
                    </div>
                    <button v-if="can.exportMembers" type="button" class="btn btn-outline-success btn-sm text-nowrap" @click="exportCsv">
                        {{ t('app.common.export_csv') }}
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th v-for="column in SORTABLE_COLUMNS" :key="column.key" scope="col">
                                <button type="button" class="btn btn-link p-0 text-decoration-none text-reset fw-semibold"
                                        :aria-label="t('app.members.sort_by', { column: column.label })" @click="toggleSort(column.key)">
                                    {{ column.label }}{{ sortIndicator(column.key) }}
                                </button>
                            </th>
                            <th scope="col">{{ t('app.members.columns.category') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="text-center py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                {{ t('app.members.loading') }}
                            </td>
                        </tr>
                        <tr v-else-if="loadError">
                            <td colspan="8" class="py-4">
                                <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center mb-0" role="alert">
                                    <span>{{ t('app.members.load_error', { message: loadError }) }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="!hasResults">
                            <td colspan="8" class="text-center py-5 text-body-secondary">{{ t('app.common.no_records') }}</td>
                        </tr>
                        <template v-else>
                            <tr v-for="member in rows" :key="member.id">
                                <td class="text-nowrap">{{ member.member_no ?? '—' }}</td>
                                <td>{{ member.applicant_name }}</td>
                                <td>{{ member.business_name }}</td>
                                <td class="small">{{ member.business_category?.name_bm ?? '—' }}</td>
                                <td><span class="badge" :class="statusBadgeClass(member.status)">{{ member.status_label }}</span></td>
                                <td class="text-nowrap">{{ formatDate(member.applied_at) }}</td>
                                <td class="text-nowrap">{{ formatDate(member.approved_at) }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" :href="`/admin/members/${member.id}`">{{ t('app.common.view') }}</a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && meta.last_page > 1" class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page <= 1 || loading"
                        @click="goToPage(meta.current_page - 1)">{{ t('app.common.previous') }}</button>
                <span class="small">{{ t('app.common.page_of', { current: meta.current_page, last: meta.last_page }) }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page >= meta.last_page || loading"
                        @click="goToPage(meta.current_page + 1)">{{ t('app.common.next') }}</button>
            </div>
        </div>
    </div>
</template>
