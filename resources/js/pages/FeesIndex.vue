<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AppModal from '../components/AppModal.vue';
import AppSelect2 from '../components/AppSelect2.vue';
import { api, ApiError, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { t } from '../lib/i18n';

const feeTypes = ref([]);
const categories = ref([]);
const loading = ref(false);
const loadError = ref('');
const showForm = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const emptyForm = () => ({
    code: '',
    name_bm: '',
    name_en: '',
    description: '',
    amount: '',
    frequency: 'yearly',
    business_category_id: '',
    due_days: '30',
    sort_order: '0',
    is_active: true,
});

const form = reactive(emptyForm());

const frequencyOptions = computed(() => [
    { id: 'yearly', text: t('app.billing.frequency_yearly') },
    { id: 'monthly', text: t('app.billing.frequency_monthly') },
    { id: 'lifetime', text: t('app.billing.frequency_lifetime') },
]);

const categoryOptions = computed(() => [
    { id: '', text: t('app.billing.category_all') },
    ...categories.value.filter((category) => category.is_active).map((category) => ({ id: category.id, text: category.name_bm })),
]);

const fieldError = (name) => firstError(errors.value, name);

function frequencyLabel(value) {
    return t(`app.billing.frequency_${value}`);
}

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const [feeResponse, categoryResponse] = await Promise.all([
            api('/admin/api/fee-types'),
            api('/admin/api/categories'),
        ]);

        feeTypes.value = feeResponse.data;
        categories.value = categoryResponse.data;
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    errors.value = {};
    Object.assign(form, emptyForm());
    showForm.value = true;
}

function openEdit(fee) {
    editing.value = fee;
    errors.value = {};
    Object.assign(form, {
        code: fee.code,
        name_bm: fee.name_bm,
        name_en: fee.name_en ?? '',
        description: fee.description ?? '',
        amount: fee.amount,
        frequency: fee.frequency,
        business_category_id: fee.business_category_id ? String(fee.business_category_id) : '',
        due_days: String(fee.due_days),
        sort_order: String(fee.sort_order),
        is_active: fee.is_active,
    });
    showForm.value = true;
}

function payload() {
    return {
        code: form.code.trim(),
        name_bm: form.name_bm.trim(),
        name_en: form.name_en.trim() === '' ? null : form.name_en.trim(),
        description: form.description.trim() === '' ? null : form.description.trim(),
        amount: form.amount.trim(),
        frequency: form.frequency,
        business_category_id: form.business_category_id === '' ? null : Number(form.business_category_id),
        due_days: Number(form.due_days),
        sort_order: Number(form.sort_order),
        is_active: form.is_active,
    };
}

async function save() {
    saving.value = true;
    errors.value = {};

    try {
        if (editing.value) {
            await api(`/admin/api/fee-types/${editing.value.id}`, { method: 'PUT', body: payload() });
        } else {
            await api('/admin/api/fee-types', { method: 'POST', body: payload() });
        }

        notifySuccess(t('app.billing.fee_saved'));
        showForm.value = false;
        await load();
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            errors.value = error.errors;
        } else {
            notifyError(error.message);
        }
    } finally {
        saving.value = false;
    }
}

async function toggleActive(fee) {
    const confirmed = await confirmAction({
        title: fee.is_active ? t('app.billing.status_inactive') : t('app.billing.status_active'),
        text: t('app.billing.fees_intro'),
    });

    if (!confirmed) {
        return;
    }

    try {
        await api(`/admin/api/fee-types/${fee.id}`, {
            method: 'PUT',
            body: {
                code: fee.code,
                name_bm: fee.name_bm,
                name_en: fee.name_en,
                description: fee.description,
                amount: fee.amount,
                frequency: fee.frequency,
                business_category_id: fee.business_category_id,
                due_days: fee.due_days,
                sort_order: fee.sort_order,
                is_active: !fee.is_active,
            },
        });
        await load();
    } catch (error) {
        notifyError(error.message);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <p class="text-body-secondary mb-3">{{ t('app.billing.fees_intro') }}</p>

        <div v-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ t('app.billing.fees_title') }}</span>
                <button type="button" class="btn btn-sm btn-primary" @click="openCreate">{{ t('app.billing.new_fee') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.billing.col_name') }}</th>
                            <th>{{ t('app.billing.col_amount') }}</th>
                            <th>{{ t('app.billing.col_frequency') }}</th>
                            <th>{{ t('app.billing.col_applies') }}</th>
                            <th>{{ t('app.billing.col_due') }}</th>
                            <th>{{ t('app.billing.col_status') }}</th>
                            <th class="text-end">{{ t('app.billing.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="7" class="text-center py-4">{{ t('app.common.loading') }}</td></tr>
                        <tr v-else-if="feeTypes.length === 0"><td colspan="7" class="text-center py-4 text-body-secondary">{{ t('app.common.no_records') }}</td></tr>
                        <template v-else>
                        <tr v-for="fee in feeTypes" :key="fee.id">
                            <td>
                                <div class="fw-semibold">{{ fee.name_bm }}</div>
                                <div class="small text-body-secondary"><code>{{ fee.code }}</code></div>
                            </td>
                            <td class="text-nowrap">{{ fee.amount_label }}</td>
                            <td class="small">{{ frequencyLabel(fee.frequency) }}</td>
                            <td class="small">{{ fee.business_category ?? t('app.billing.category_all') }}</td>
                            <td>{{ fee.due_days }}</td>
                            <td><span class="badge" :class="fee.is_active ? 'text-bg-success' : 'text-bg-secondary'">{{ fee.is_active ? t('app.billing.status_active') : t('app.billing.status_inactive') }}</span></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="openEdit(fee)">{{ t('app.common.edit') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleActive(fee)">
                                    {{ fee.is_active ? t('app.billing.status_inactive') : t('app.billing.status_active') }}
                                </button>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <AppModal :open="showForm" :title="editing ? t('app.billing.edit_fee') : t('app.billing.new_fee')" size="modal-lg" @close="showForm = false">
            <form novalidate @submit.prevent="save">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="fee-code">{{ t('app.billing.field_code') }} <span class="text-danger">*</span></label>
                            <input id="fee-code" v-model="form.code" type="text" maxlength="40" class="form-control" :class="{ 'is-invalid': fieldError('code') }">
                            <div class="invalid-feedback">{{ fieldError('code') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="fee-amount">{{ t('app.billing.field_amount') }} <span class="text-danger">*</span></label>
                            <input id="fee-amount" v-model="form.amount" type="text" inputmode="decimal" maxlength="14" class="form-control" :class="{ 'is-invalid': fieldError('amount') }">
                            <div class="invalid-feedback">{{ fieldError('amount') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="fee-name-bm">{{ t('app.billing.field_name_bm') }} <span class="text-danger">*</span></label>
                            <input id="fee-name-bm" v-model="form.name_bm" type="text" maxlength="150" class="form-control" :class="{ 'is-invalid': fieldError('name_bm') }">
                            <div class="invalid-feedback">{{ fieldError('name_bm') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="fee-name-en">{{ t('app.billing.field_name_en') }}</label>
                            <input id="fee-name-en" v-model="form.name_en" type="text" maxlength="150" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="fee-frequency">{{ t('app.billing.field_frequency') }} <span class="text-danger">*</span></label>
                            <AppSelect2 id="fee-frequency" v-model="form.frequency" :options="frequencyOptions" />
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="fee-category">{{ t('app.billing.field_category') }}</label>
                            <AppSelect2 id="fee-category" v-model="form.business_category_id" :options="categoryOptions" />
                            <div class="invalid-feedback d-block" v-if="fieldError('business_category_id')">{{ fieldError('business_category_id') }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="fee-due">{{ t('app.billing.field_due_days') }} <span class="text-danger">*</span></label>
                            <input id="fee-due" v-model="form.due_days" type="number" min="1" max="365" class="form-control" :class="{ 'is-invalid': fieldError('due_days') }">
                            <div class="invalid-feedback">{{ fieldError('due_days') }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="fee-sort">{{ t('app.billing.field_sort') }}</label>
                            <input id="fee-sort" v-model="form.sort_order" type="number" min="0" max="999" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="fee-description">{{ t('app.billing.field_description') }}</label>
                            <textarea id="fee-description" v-model="form.description" rows="2" maxlength="1000" class="form-control"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="fee-active" v-model="form.is_active" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="fee-active">{{ t('app.billing.field_active') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="showForm = false">{{ t('app.common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">{{ t('app.common.save') }}</button>
                </div>
            </form>
        </AppModal>
    </div>
</template>
