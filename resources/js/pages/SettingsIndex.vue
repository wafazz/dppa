<script setup>
import { onMounted, reactive, ref } from 'vue';
import LogoUpload from '../components/forms/LogoUpload.vue';
import { api, ApiError, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

const summary = ref(null);
const logoUrl = ref(null);
const loading = ref(false);
const loadError = ref('');
const savingCard = ref('');
const sendingTest = ref(false);
const errors = ref({});
const testRecipient = ref('');

// Form state per card. API key fields always start empty: the server never returns a saved key.
const resendForm = reactive({ api_key: '', from_email: '', from_name: '' });
const onsendForm = reactive({ api_key: '', base_url: '', sender_id: '' });

function fillForms(data) {
    summary.value = data;
    resendForm.api_key = '';
    resendForm.from_email = data.resend.from_email ?? '';
    resendForm.from_name = data.resend.from_name ?? '';
    onsendForm.api_key = '';
    onsendForm.base_url = data.onsend.base_url ?? '';
    onsendForm.sender_id = data.onsend.sender_id ?? '';
}

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api('/admin/api/settings/integrations');
        fillForms(response.data);
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

const fieldError = (name) => firstError(errors.value, name);

function clean(form) {
    const body = {};

    Object.entries(form).forEach(([key, value]) => {
        const trimmed = typeof value === 'string' ? value.trim() : value;
        body[key] = trimmed === '' ? null : trimmed;
    });

    return body;
}

async function saveCard(card, form) {
    const confirmed = await confirmAction({
        title: t('app.settings.save'),
        text: t(card === 'resend' ? 'app.settings.header_resend' : 'app.settings.header_onsend'),
    });

    if (!confirmed) {
        return;
    }

    savingCard.value = card;
    errors.value = {};

    try {
        const response = await api(`/admin/api/settings/integrations/${card}`, { method: 'PUT', body: clean(form) });
        fillForms(response.data);
        notifySuccess(t('app.settings.saved'));
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            errors.value = error.errors;
        } else {
            notifyError(error.message);
        }
    } finally {
        savingCard.value = '';
    }
}

async function sendTestEmail() {
    const recipient = testRecipient.value.trim();

    if (recipient === '') {
        notifyError(t('app.settings.test_recipient'));
        return;
    }

    const confirmed = await confirmAction({
        title: t('app.settings.test_button'),
        text: recipient,
    });

    if (!confirmed) {
        return;
    }

    sendingTest.value = true;

    try {
        const response = await api('/admin/api/settings/integrations/test-resend', {
            method: 'POST',
            body: { to: recipient },
        });
        notifySuccess(response.message);
    } catch (error) {
        notifyError(error.message);
    } finally {
        sendingTest.value = false;
    }
}

async function loadBranding() {
    if (!props.can.manageBranding) {
        return;
    }

    try {
        const response = await api('/admin/api/branding');
        logoUrl.value = response.data.logo_url;
    } catch (error) {
        notifyError(error.message);
    }
}

function onLogoChanged(data) {
    logoUrl.value = data.logo_url;
}

onMounted(() => {
    load();
    loadBranding();
});
</script>

<template>
    <div>
        <div v-if="loadError" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.settings.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
        </div>

        <div v-if="loading && !summary" class="text-body-secondary">{{ t('app.common.loading') }}</div>

        <div v-if="summary" class="row g-3">
            <!-- System logo: superadmin only -->
            <div v-if="can.manageBranding" class="col-12">
                <LogoUpload :logo-url="logoUrl" @changed="onLogoChanged" />
            </div>
            <!-- Resend card: one Save and one Test for the whole card -->
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ t('app.settings.header_resend') }}</span>
                        <span class="badge" :class="summary.resend.has_api_key ? 'text-bg-success' : 'text-bg-secondary'">
                            {{ summary.resend.has_api_key ? t('app.common.active') : t('app.settings.api_key_missing') }}
                        </span>
                    </div>
                    <form class="card-body" novalidate @submit.prevent="saveCard('resend', resendForm)">
                        <div class="mb-3">
                            <label class="form-label" for="resend-key">{{ t('app.settings.api_key') }}</label>
                            <input id="resend-key" v-model="resendForm.api_key" type="password" autocomplete="off" maxlength="255"
                                   class="form-control" :class="{ 'is-invalid': fieldError('api_key') }"
                                   :placeholder="summary.resend.has_api_key ? '••••••••••••' : 're_...'">
                            <div class="invalid-feedback">{{ fieldError('api_key') }}</div>
                            <div class="form-text">{{ summary.resend.has_api_key ? t('app.settings.api_key_saved') : t('app.settings.api_key_missing') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="resend-from-email">{{ t('app.settings.from_email') }}</label>
                            <input id="resend-from-email" v-model="resendForm.from_email" type="email" maxlength="190"
                                   class="form-control" :class="{ 'is-invalid': fieldError('from_email') }">
                            <div class="invalid-feedback">{{ fieldError('from_email') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="resend-from-name">{{ t('app.settings.from_name') }}</label>
                            <input id="resend-from-name" v-model="resendForm.from_name" type="text" maxlength="100"
                                   class="form-control" :class="{ 'is-invalid': fieldError('from_name') }">
                            <div class="invalid-feedback">{{ fieldError('from_name') }}</div>
                        </div>

                        <div class="d-flex justify-content-end mb-4">
                            <button type="submit" class="btn btn-primary" :disabled="savingCard === 'resend'">
                                <span v-if="savingCard === 'resend'" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                {{ t('app.settings.save') }}
                            </button>
                        </div>

                        <hr>
                        <label class="form-label fw-semibold" for="test-recipient">{{ t('app.settings.test_title') }}</label>
                        <div class="input-group">
                            <input id="test-recipient" v-model="testRecipient" type="email" maxlength="190" class="form-control"
                                   :placeholder="t('app.settings.test_recipient')">
                            <button type="button" class="btn btn-outline-primary" :disabled="sendingTest || testRecipient.trim() === ''"
                                    @click="sendTestEmail">
                                <span v-if="sendingTest" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                {{ t('app.settings.test_button') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- OnSend card: one Save for the whole card; testing is not available yet -->
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ t('app.settings.header_onsend') }}</span>
                        <span class="badge" :class="summary.onsend.has_api_key ? 'text-bg-success' : 'text-bg-secondary'">
                            {{ summary.onsend.has_api_key ? t('app.common.active') : t('app.settings.api_key_missing') }}
                        </span>
                    </div>
                    <form class="card-body" novalidate @submit.prevent="saveCard('onsend', onsendForm)">
                        <div class="alert alert-warning small">{{ t('app.settings.onsend_pending') }}</div>

                        <div class="mb-3">
                            <label class="form-label" for="onsend-key">{{ t('app.settings.api_key') }}</label>
                            <input id="onsend-key" v-model="onsendForm.api_key" type="password" autocomplete="off" maxlength="255"
                                   class="form-control" :class="{ 'is-invalid': fieldError('api_key') }"
                                   :placeholder="summary.onsend.has_api_key ? '••••••••••••' : ''">
                            <div class="invalid-feedback">{{ fieldError('api_key') }}</div>
                            <div class="form-text">{{ summary.onsend.has_api_key ? t('app.settings.api_key_saved') : t('app.settings.api_key_missing') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="onsend-base-url">{{ t('app.settings.base_url') }}</label>
                            <input id="onsend-base-url" v-model="onsendForm.base_url" type="url" maxlength="255" placeholder="https://"
                                   class="form-control" :class="{ 'is-invalid': fieldError('base_url') }">
                            <div class="invalid-feedback">{{ fieldError('base_url') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="onsend-sender">{{ t('app.settings.sender_id') }}</label>
                            <input id="onsend-sender" v-model="onsendForm.sender_id" type="text" maxlength="100"
                                   class="form-control" :class="{ 'is-invalid': fieldError('sender_id') }">
                            <div class="invalid-feedback">{{ fieldError('sender_id') }}</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" disabled :title="t('app.settings.test_unavailable')">
                                {{ t('app.settings.test_button') }}
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="savingCard === 'onsend'">
                                <span v-if="savingCard === 'onsend'" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                {{ t('app.settings.save') }}
                            </button>
                        </div>
                        <div class="form-text text-end">{{ t('app.settings.test_unavailable') }}</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
