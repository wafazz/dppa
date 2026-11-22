<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppModal from '../components/AppModal.vue';
import AppSelect2 from '../components/AppSelect2.vue';
import { api, ApiError, buildQuery, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { formatDate } from '../lib/format';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

const summary = ref(null);
const feeTypes = ref([]);
const methods = ref([]);
const invoices = ref([]);
const meta = ref(null);
const loading = ref(false);
const loadError = ref('');
const expanded = ref({});

const filters = reactive({ status: '', fee_type_id: '', year: '', q: '' });
const page = ref(1);

const statusOptions = computed(() => [
    { id: '', text: t('app.billing.status_all') },
    { id: 'unpaid', text: t('app.billing.status_unpaid') },
    { id: 'partial', text: t('app.billing.status_partial') },
    { id: 'overdue', text: t('app.billing.status_overdue') },
    { id: 'paid', text: t('app.billing.status_paid') },
    { id: 'waived', text: t('app.billing.status_waived') },
]);

const feeOptions = computed(() => [
    { id: '', text: t('app.billing.status_all') },
    ...feeTypes.value.map((fee) => ({ id: fee.id, text: fee.name_bm })),
]);

const methodOptions = computed(() => methods.value.map((method) => ({ id: method, text: t(`app.billing.method_${method}`) })));

// Dialogs: one at a time. "generate", "payment", "waive" or "void".
const dialog = ref(null);
const dialogErrors = ref({});
const saving = ref(false);
const generateForm = reactive({ fee_type_id: '', period_type: '', year: String(new Date().getFullYear()), month: '' });
const paymentForm = reactive({ amount: '', method: 'cash', reference: '', paid_on: '', note: '' });
const reasonForm = reactive({ reason: '' });
const claims = ref([]);
const target = ref(null);

const dialogTitle = computed(() => {
    if (dialog.value === 'generate') return t('app.billing.generate_title');
    if (dialog.value === 'payment') return t('app.billing.payment_title', { number: target.value?.number ?? '' });
    if (dialog.value === 'waive') return t('app.billing.waive_title', { number: target.value?.number ?? '' });
    if (dialog.value === 'void') return t('app.billing.void_title', { receipt: target.value?.receipt ?? '' });
    if (dialog.value === 'reject-claim') return t('app.billing.claim_reject_title', { number: target.value?.invoice_number ?? '' });

    return '';
});

const feeSelectOptions = computed(() => feeTypes.value.filter((fee) => fee.is_active).map((fee) => ({ id: fee.id, text: fee.name_bm })));

const selectedGenerateFee = computed(() => feeTypes.value.find((fee) => String(fee.id) === String(generateForm.fee_type_id)) ?? null);

const monthOptions = computed(() => [
    { id: '', text: t('app.billing.month_all') },
    ...Array.from({ length: 12 }, (_, index) => ({
        id: String(index + 1).padStart(2, '0'),
        text: new Intl.DateTimeFormat(window.APP_LOCALE ?? 'en', { month: 'long' }).format(new Date(2000, index, 1)),
    })),
]);

const periodTypeOptions = computed(() => (selectedGenerateFee.value?.periods ?? []).map((period) => ({
    id: period,
    text: t(`app.billing.period_name_${period}`),
})));

// The period sent to the server: "2027" for a year, "2027-03" for one month, null for a lifetime charge.
const generatePeriod = computed(() => {
    if (generateForm.period_type === 'lifetime') return null;
    if (generateForm.period_type === 'monthly' && generateForm.month !== '') return `${generateForm.year}-${generateForm.month}`;

    return String(generateForm.year);
});

watch(() => generateForm.fee_type_id, () => {
    generateForm.period_type = selectedGenerateFee.value?.periods?.[0] ?? '';
    generateForm.month = '';
});

const fieldError = (name) => firstError(dialogErrors.value, name);

function statusBadge(invoice) {
    if (invoice.status === 'waived') return { cls: 'text-bg-secondary', text: t('app.billing.status_waived') };
    if (invoice.status === 'paid') return { cls: 'text-bg-success', text: t('app.billing.status_paid') };
    if (invoice.overdue) return { cls: 'text-bg-danger', text: t('app.billing.status_overdue') };
    if (invoice.status === 'partial') return { cls: 'text-bg-info', text: t('app.billing.status_partial') };

    return { cls: 'text-bg-warning', text: t('app.billing.status_unpaid') };
}

function canPay(invoice) {
    return invoice.balance_cents > 0 && invoice.status !== 'waived';
}

async function loadSummary() {
    try {
        const response = await api('/admin/api/invoices/summary');
        summary.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

async function loadInvoices() {
    loading.value = true;
    loadError.value = '';

    try {
        const query = buildQuery({ ...filters, page: page.value });
        const response = await api(`/admin/api/invoices?${query}`);
        invoices.value = response.data;
        meta.value = response.meta;
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function loadReference() {
    try {
        const [feeResponse, settingsResponse] = await Promise.all([
            api('/admin/api/fee-types'),
            api('/admin/api/payment-settings'),
        ]);

        feeTypes.value = feeResponse.data;
        methods.value = settingsResponse.data.enabled_methods;
    } catch (error) {
        loadError.value = error.message;
    }
}

async function loadClaims() {
    try {
        const response = await api('/admin/api/payment-claims');
        claims.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

async function refresh() {
    await Promise.all([loadSummary(), loadInvoices(), loadClaims()]);
}

async function confirmClaim(claim) {
    const confirmed = await confirmAction({ title: t('app.billing.claim_confirm_title'), text: t('app.billing.claim_confirm_text') });

    if (!confirmed) return;

    try {
        const response = await api(`/admin/api/payment-claims/${claim.id}/confirm`, { method: 'POST' });
        notifySuccess(response.message);
        await refresh();
    } catch (error) {
        notifyError(error.message);
    }
}

function openRejectClaim(claim) {
    reasonForm.reason = '';
    openDialog('reject-claim', claim);
}

function applyFilters() {
    page.value = 1;
    loadInvoices();
}

function goToPage(target) {
    if (!meta.value || target < 1 || target > meta.value.last_page) return;
    page.value = target;
    loadInvoices();
}

function openDialog(kind, subject = null) {
    dialog.value = kind;
    target.value = subject;
    dialogErrors.value = {};
}

function openGenerate() {
    Object.assign(generateForm, { fee_type_id: '', period_type: '', year: String(new Date().getFullYear()), month: '' });
    openDialog('generate');
}

function openPayment(invoice) {
    Object.assign(paymentForm, { amount: '', method: methods.value[0] ?? 'cash', reference: '', paid_on: new Date().toISOString().slice(0, 10), note: '' });
    openDialog('payment', invoice);
}

function openWaive(invoice) {
    reasonForm.reason = '';
    openDialog('waive', invoice);
}

function openVoid(payment, invoice) {
    reasonForm.reason = '';
    openDialog('void', { ...payment, invoice_id: invoice.id });
}

function closeDialog() {
    dialog.value = null;
    target.value = null;
}

function handleError(error) {
    if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
        dialogErrors.value = error.errors;
    } else {
        notifyError(error.message);
    }
}

async function submitDialog() {
    saving.value = true;
    dialogErrors.value = {};

    try {
        if (dialog.value === 'generate') {
            const response = await api('/admin/api/invoices/generate', {
                method: 'POST',
                body: { fee_type_id: Number(generateForm.fee_type_id), period_type: generateForm.period_type, period: generatePeriod.value },
            });
            notifySuccess(response.message);
        }

        if (dialog.value === 'payment') {
            const response = await api(`/admin/api/invoices/${target.value.id}/payments`, {
                method: 'POST',
                body: { ...paymentForm },
            });
            notifySuccess(response.message);
        }

        if (dialog.value === 'waive') {
            const response = await api(`/admin/api/invoices/${target.value.id}/waive`, {
                method: 'POST',
                body: { reason: reasonForm.reason },
            });
            notifySuccess(response.message);
        }

        if (dialog.value === 'void') {
            const response = await api(`/admin/api/payments/${target.value.id}/void`, {
                method: 'POST',
                body: { reason: reasonForm.reason },
            });
            notifySuccess(response.message);
        }

        if (dialog.value === 'reject-claim') {
            const response = await api(`/admin/api/payment-claims/${target.value.id}/reject`, {
                method: 'POST',
                body: { reason: reasonForm.reason },
            });
            notifySuccess(response.message);
        }

        closeDialog();
        await refresh();
    } catch (error) {
        handleError(error);
    } finally {
        saving.value = false;
    }
}

async function confirmWaive(invoice) {
    openWaive(invoice);
}

async function confirmVoid(payment, invoice) {
    const confirmed = await confirmAction({ title: t('app.billing.void_confirm_title'), text: t('app.billing.void_confirm_text') });

    if (confirmed) {
        openVoid(payment, invoice);
    }
}

function toggleExpanded(invoice) {
    expanded.value = { ...expanded.value, [invoice.id]: !expanded.value[invoice.id] };
}

watch(() => filters.status, applyFilters);
watch(() => filters.fee_type_id, applyFilters);

onMounted(async () => {
    await loadReference();
    await refresh();
});
</script>

<template>
    <div>
        <p class="text-body-secondary mb-3">{{ t('app.billing.invoices_title') }}</p>

        <div v-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>

        <div class="row g-3 mb-3" v-if="summary">
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-body-secondary">{{ t('app.billing.summary_outstanding') }}</div>
                    <div class="fs-4 fw-semibold">{{ summary.outstanding_label }}</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-body-secondary">{{ t('app.billing.summary_overdue') }}</div>
                    <div class="fs-4 fw-semibold">{{ summary.overdue_invoices }}</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-body-secondary">{{ t('app.billing.summary_collected_month') }}</div>
                    <div class="fs-4 fw-semibold">{{ summary.collected_month_label }}</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-body-secondary">{{ t('app.billing.summary_no_invoice') }}</div>
                    <div class="fs-4 fw-semibold">{{ summary.members_without_invoices }}</div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3" v-if="claims.length > 0">
            <div class="card-header fw-semibold">{{ t('app.billing.claims_title', { count: claims.length }) }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.billing.col_member') }}</th>
                            <th>{{ t('app.billing.col_number') }}</th>
                            <th class="text-end">{{ t('app.billing.col_amount_due') }}</th>
                            <th>{{ t('app.billing.field_method') }}</th>
                            <th>{{ t('app.billing.field_paid_on') }}</th>
                            <th>{{ t('app.billing.field_reference') }}</th>
                            <th class="text-end">{{ t('app.billing.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="claim in claims" :key="claim.id">
                            <td>{{ claim.member.name }} <span class="small text-body-secondary">{{ claim.member.member_no }}</span></td>
                            <td class="small"><code>{{ claim.invoice_number }}</code> {{ claim.fee }}</td>
                            <td class="text-end text-nowrap">{{ claim.amount_label }}</td>
                            <td class="small">{{ t(`app.billing.method_${claim.method}`) }}</td>
                            <td class="small text-nowrap">{{ claim.paid_on }}</td>
                            <td class="small">{{ claim.reference ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-success me-1" @click="confirmClaim(claim)">{{ t('app.billing.claim_confirm') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="openRejectClaim(claim)">{{ t('app.billing.claim_reject') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" novalidate @submit.prevent="applyFilters">
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="inv-status">{{ t('app.billing.filter_status') }}</label>
                        <AppSelect2 id="inv-status" v-model="filters.status" :options="statusOptions" />
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="inv-fee">{{ t('app.billing.filter_fee') }}</label>
                        <AppSelect2 id="inv-fee" v-model="filters.fee_type_id" :options="feeOptions" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1" for="inv-year">{{ t('app.billing.filter_year') }}</label>
                        <input id="inv-year" v-model="filters.year" type="number" min="2020" max="2100" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="inv-q">{{ t('app.billing.filter_search') }}</label>
                        <input id="inv-q" v-model="filters.q" type="search" maxlength="120" class="form-control">
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                        <button type="submit" class="btn btn-outline-primary">{{ t('app.common.search') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ t('app.billing.invoices_title') }}</span>
                <button type="button" class="btn btn-sm btn-primary" @click="openGenerate">{{ t('app.billing.generate') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.billing.col_number') }}</th>
                            <th>{{ t('app.billing.col_member') }}</th>
                            <th>{{ t('app.billing.col_fee_name') }}</th>
                            <th class="text-end">{{ t('app.billing.col_amount_due') }}</th>
                            <th class="text-end">{{ t('app.billing.col_paid') }}</th>
                            <th class="text-end">{{ t('app.billing.col_balance') }}</th>
                            <th>{{ t('app.billing.col_due') }}</th>
                            <th>{{ t('app.billing.col_invoice_status') }}</th>
                            <th class="text-end">{{ t('app.billing.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="9" class="text-center py-4">{{ t('app.common.loading') }}</td></tr>
                        <tr v-else-if="invoices.length === 0"><td colspan="9" class="text-center py-4 text-body-secondary">{{ t('app.billing.empty') }}</td></tr>
                        <template v-else>
                        <template v-for="invoice in invoices" :key="invoice.id">
                            <tr>
                                <td class="text-nowrap"><code>{{ invoice.number }}</code></td>
                                <td>
                                    <div class="fw-semibold">{{ invoice.member.name }}</div>
                                    <div class="small text-body-secondary">{{ invoice.member.member_no ?? '—' }}</div>
                                </td>
                                <td class="small">{{ invoice.fee.name }}<span v-if="invoice.period"> ({{ invoice.period }})</span></td>
                                <td class="text-end text-nowrap">{{ invoice.amount_label }}</td>
                                <td class="text-end text-nowrap">{{ invoice.paid_label }}</td>
                                <td class="text-end text-nowrap">{{ invoice.balance_label }}</td>
                                <td class="text-nowrap">{{ formatDate(invoice.due_on) }}</td>
                                <td><span class="badge" :class="statusBadge(invoice).cls">{{ statusBadge(invoice).text }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button v-if="canPay(invoice)" type="button" class="btn btn-sm btn-outline-success me-1" @click="openPayment(invoice)">{{ t('app.billing.record_payment') }}</button>
                                    <button v-if="can.manageAccounts && canPay(invoice)" type="button" class="btn btn-sm btn-outline-danger me-1" @click="confirmWaive(invoice)">{{ t('app.billing.waive') }}</button>
                                    <button type="button" class="btn btn-sm btn-link" @click="toggleExpanded(invoice)">{{ t('app.billing.payments_title') }} ({{ invoice.payments.length }})</button>
                                </td>
                            </tr>
                            <tr v-if="expanded[invoice.id]">
                                <td colspan="9" class="bg-body-tertiary">
                                    <div v-if="invoice.payments.length === 0" class="small text-body-secondary">{{ t('app.billing.payment_none') }}</div>
                                    <table v-else class="table table-sm mb-0">
                                        <tbody>
                                            <tr v-for="payment in invoice.payments" :key="payment.id" :class="{ 'text-decoration-line-through text-body-secondary': payment.voided }">
                                                <td class="small"><code>{{ payment.receipt }}</code></td>
                                                <td class="small">{{ payment.paid_on }}</td>
                                                <td class="small">{{ t(`app.billing.method_${payment.method}`) }}<span v-if="payment.reference"> · {{ payment.reference }}</span></td>
                                                <td class="small text-end">{{ payment.amount_label }}</td>
                                                <td class="small text-end">
                                                    <span v-if="payment.voided" class="badge text-bg-secondary">{{ t('app.billing.voided') }}</span>
                                                    <button v-else-if="can.manageAccounts" type="button" class="btn btn-sm btn-outline-danger" @click="confirmVoid(payment, invoice)">{{ t('app.billing.void_payment') }}</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                        </template>
                    </tbody>
                </table>
            </div>
            <div v-if="meta && meta.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">{{ t('app.common.previous') }}</button>
                <span class="small">{{ t('app.common.page_of', { current: meta.current_page, last: meta.last_page }) }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">{{ t('app.common.next') }}</button>
            </div>
        </div>

        <AppModal :open="dialog !== null" :title="dialogTitle" size="modal-lg" @close="closeDialog">
            <form novalidate @submit.prevent="submitDialog">
                <div class="modal-body">
                    <template v-if="dialog === 'generate'">
                        <p class="small text-body-secondary">{{ t('app.billing.generate_hint') }}</p>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="gen-fee">{{ t('app.billing.field_fee') }} <span class="text-danger">*</span></label>
                                <AppSelect2 id="gen-fee" v-model="generateForm.fee_type_id" :options="feeSelectOptions" :invalid="!!fieldError('fee_type_id')" />
                                <div class="invalid-feedback d-block" v-if="fieldError('fee_type_id')">{{ fieldError('fee_type_id') }}</div>
                            </div>
                            <div class="col-12 col-md-6" v-if="selectedGenerateFee">
                                <label class="form-label" for="gen-period-type">{{ t('app.billing.field_period') }} <span class="text-danger">*</span></label>
                                <AppSelect2 id="gen-period-type" v-model="generateForm.period_type" :options="periodTypeOptions" :invalid="!!fieldError('period_type')" />
                                <div class="invalid-feedback d-block" v-if="fieldError('period_type')">{{ fieldError('period_type') }}</div>
                            </div>
                            <template v-if="generateForm.period_type === 'yearly' || generateForm.period_type === 'monthly'">
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="gen-year">{{ t('app.billing.field_year') }} <span class="text-danger">*</span></label>
                                    <input id="gen-year" v-model="generateForm.year" type="number" min="2020" max="2100" class="form-control" :class="{ 'is-invalid': fieldError('period') }">
                                </div>
                                <div class="col-6 col-md-3" v-if="generateForm.period_type === 'monthly'">
                                    <label class="form-label" for="gen-month">{{ t('app.billing.field_month') }}</label>
                                    <AppSelect2 id="gen-month" v-model="generateForm.month" :options="monthOptions" />
                                </div>
                                <div class="col-12">
                                    <div class="invalid-feedback d-block" v-if="fieldError('period')">{{ fieldError('period') }}</div>
                                </div>
                            </template>
                            <div class="col-12" v-if="generateForm.period_type === 'lifetime'">
                                <div class="alert alert-light small mb-0">{{ t('app.billing.lifetime_hint') }}</div>
                            </div>
                        </div>
                    </template>

                    <template v-if="dialog === 'payment'">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="pay-amount">{{ t('app.billing.field_amount_paid') }} <span class="text-danger">*</span></label>
                                <input id="pay-amount" v-model="paymentForm.amount" type="text" inputmode="decimal" class="form-control" :class="{ 'is-invalid': fieldError('amount') || fieldError('amount_cents') }">
                                <div class="invalid-feedback">{{ fieldError('amount') || fieldError('amount_cents') }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="pay-method">{{ t('app.billing.field_method') }} <span class="text-danger">*</span></label>
                                <AppSelect2 id="pay-method" v-model="paymentForm.method" :options="methodOptions" :invalid="!!fieldError('method')" />
                                <div class="invalid-feedback d-block" v-if="fieldError('method')">{{ fieldError('method') }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="pay-date">{{ t('app.billing.field_paid_on') }} <span class="text-danger">*</span></label>
                                <input id="pay-date" v-model="paymentForm.paid_on" type="date" class="form-control" :class="{ 'is-invalid': fieldError('paid_on') }">
                                <div class="invalid-feedback">{{ fieldError('paid_on') }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="pay-reference">{{ t('app.billing.field_reference') }}</label>
                                <input id="pay-reference" v-model="paymentForm.reference" type="text" maxlength="120" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="pay-note">{{ t('app.billing.field_note') }}</label>
                                <input id="pay-note" v-model="paymentForm.note" type="text" maxlength="500" class="form-control">
                            </div>
                        </div>
                    </template>

                    <template v-if="dialog === 'waive' || dialog === 'void' || dialog === 'reject-claim'">
                        <label class="form-label" for="reason">{{ t('app.billing.field_reason') }} <span class="text-danger">*</span></label>
                        <textarea id="reason" v-model="reasonForm.reason" rows="3" maxlength="500" class="form-control" :class="{ 'is-invalid': fieldError('reason') }"></textarea>
                        <div class="invalid-feedback">{{ fieldError('reason') }}</div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="closeDialog">{{ t('app.common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        {{ dialog === 'payment' ? t('app.billing.save_payment') : dialog === 'generate' ? t('app.billing.generate') : dialog === 'waive' ? t('app.billing.waive') : t('app.billing.void_payment') }}
                    </button>
                </div>
            </form>
        </AppModal>
    </div>
</template>
