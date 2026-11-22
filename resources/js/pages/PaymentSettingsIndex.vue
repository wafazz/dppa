<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api, ApiError, firstError } from '../lib/http';
import { notifyError, notifySuccess } from '../lib/feedback';
import { t } from '../lib/i18n';

const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const errors = ref({});

const form = reactive({
    bank_name: '',
    account_name: '',
    account_number: '',
    enabled_methods: [],
    instructions_bm: '',
    instructions_en: '',
    receipt_footer: '',
    invoice_prefix: 'INV',
    receipt_prefix: 'RCP',
    currency_symbol: 'RM',
    auto_invoice_on_approval: false,
    send_invoice_emails: true,
    reminder_after_days: 0,
});

const methods = ref([]);
const fieldError = (name) => firstError(errors.value, name);

const methodLabels = computed(() => methods.value.map((method) => ({ key: method, label: t(`app.billing.method_${method}`) })));

function fill(data) {
    Object.assign(form, {
        bank_name: data.bank_name,
        account_name: data.account_name,
        account_number: data.account_number,
        enabled_methods: [...data.enabled_methods],
        instructions_bm: data.instructions_bm,
        instructions_en: data.instructions_en,
        receipt_footer: data.receipt_footer,
        invoice_prefix: data.invoice_prefix,
        receipt_prefix: data.receipt_prefix,
        currency_symbol: data.currency_symbol,
        auto_invoice_on_approval: data.auto_invoice_on_approval,
        send_invoice_emails: data.send_invoice_emails,
        reminder_after_days: data.reminder_after_days,
    });
    methods.value = data.methods;
}

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api('/admin/api/payment-settings');
        fill(response.data);
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

function toggleMethod(method, on) {
    const current = new Set(form.enabled_methods);

    if (on) {
        current.add(method);
    } else {
        current.delete(method);
    }

    form.enabled_methods = [...current];
}

async function save() {
    saving.value = true;
    errors.value = {};

    try {
        const response = await api('/admin/api/payment-settings', { method: 'PUT', body: { ...form } });
        fill(response.data);
        notifySuccess(t('app.billing.settings.saved'));
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

onMounted(load);
</script>

<template>
    <div>
        <p class="text-body-secondary mb-3">{{ t('app.billing.settings_intro') }}</p>

        <div v-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>
        <div v-if="loading" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>

        <form v-else novalidate @submit.prevent="save">
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ t('app.billing.settings.section_bank') }}</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label" for="ps-bank">{{ t('app.billing.settings.bank_name') }}</label>
                                <input id="ps-bank" v-model="form.bank_name" type="text" maxlength="120" class="form-control" :class="{ 'is-invalid': fieldError('bank_name') }">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="ps-account-name">{{ t('app.billing.settings.account_name') }}</label>
                                <input id="ps-account-name" v-model="form.account_name" type="text" maxlength="150" class="form-control">
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="ps-account-number">{{ t('app.billing.settings.account_number') }}</label>
                                <input id="ps-account-number" v-model="form.account_number" type="text" maxlength="40" class="form-control">
                            </div>
                            <div class="form-text">{{ t('app.billing.settings.bank_hint') }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ t('app.billing.settings.section_methods') }}</div>
                        <div class="card-body">
                            <div v-for="method in methodLabels" :key="method.key" class="form-check form-switch mb-2">
                                <input :id="`ps-method-${method.key}`" class="form-check-input" type="checkbox" role="switch"
                                       :checked="form.enabled_methods.includes(method.key)"
                                       @change="toggleMethod(method.key, $event.target.checked)">
                                <label class="form-check-label" :for="`ps-method-${method.key}`">{{ method.label }}</label>
                            </div>
                            <div class="form-text">{{ t('app.billing.settings.methods_hint') }}</div>
                            <div class="invalid-feedback d-block" v-if="fieldError('enabled_methods')">{{ fieldError('enabled_methods') }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header fw-semibold">{{ t('app.billing.settings.section_instructions') }}</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="ps-instructions-bm">{{ t('app.billing.settings.instructions_bm') }}</label>
                                    <textarea id="ps-instructions-bm" v-model="form.instructions_bm" rows="4" maxlength="2000" class="form-control"></textarea>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="ps-instructions-en">{{ t('app.billing.settings.instructions_en') }}</label>
                                    <textarea id="ps-instructions-en" v-model="form.instructions_en" rows="4" maxlength="2000" class="form-control"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="ps-footer">{{ t('app.billing.settings.receipt_footer') }}</label>
                                    <input id="ps-footer" v-model="form.receipt_footer" type="text" maxlength="500" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ t('app.billing.settings.section_numbers') }}</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="ps-inv-prefix">{{ t('app.billing.settings.invoice_prefix') }}</label>
                                    <input id="ps-inv-prefix" v-model="form.invoice_prefix" type="text" maxlength="10" class="form-control" :class="{ 'is-invalid': fieldError('invoice_prefix') }">
                                    <div class="invalid-feedback">{{ fieldError('invoice_prefix') }}</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="ps-rcp-prefix">{{ t('app.billing.settings.receipt_prefix') }}</label>
                                    <input id="ps-rcp-prefix" v-model="form.receipt_prefix" type="text" maxlength="10" class="form-control" :class="{ 'is-invalid': fieldError('receipt_prefix') }">
                                    <div class="invalid-feedback">{{ fieldError('receipt_prefix') }}</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="ps-currency">{{ t('app.billing.settings.currency_symbol') }}</label>
                                    <input id="ps-currency" v-model="form.currency_symbol" type="text" maxlength="6" class="form-control" :class="{ 'is-invalid': fieldError('currency_symbol') }">
                                    <div class="invalid-feedback">{{ fieldError('currency_symbol') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ t('app.billing.settings.section_rules') }}</div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-2">
                                <input id="ps-auto" v-model="form.auto_invoice_on_approval" class="form-check-input" type="checkbox" role="switch">
                                <label class="form-check-label" for="ps-auto">{{ t('app.billing.settings.auto_invoice') }}</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input id="ps-emails" v-model="form.send_invoice_emails" class="form-check-input" type="checkbox" role="switch">
                                <label class="form-check-label" for="ps-emails">{{ t('app.billing.settings.send_invoice_emails') }}</label>
                            </div>
                            <label class="form-label" for="ps-reminder">{{ t('app.billing.settings.reminder_after_days') }}</label>
                            <input id="ps-reminder" v-model="form.reminder_after_days" type="number" min="0" max="90" class="form-control w-auto" :class="{ 'is-invalid': fieldError('reminder_after_days') }">
                            <div class="invalid-feedback">{{ fieldError('reminder_after_days') }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" :disabled="saving">{{ t('app.common.save') }}</button>
                </div>
            </div>
        </form>
    </div>
</template>
