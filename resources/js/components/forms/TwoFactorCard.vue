<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

/**
 * Two-factor sign-in for the signed-in account. Each method is switched on here and used at sign-in.
 * Email and WhatsApp appear only when their channel is connected. Switching a method off needs a code from that same method.
 */
const status = ref(null);
const loadError = ref('');
const busy = ref(false);
const errors = ref({});

const setup = reactive({ secret: '', otpauth_uri: '', qr_data_uri: '', code: '' });
const emailCode = reactive({ sent: false, code: '' });
const turnOff = reactive({ method: null, code: '', sent: false });

async function load() {
    loadError.value = '';

    try {
        const response = await api('/admin/api/profile/two-factor');
        status.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

function showError(error) {
    if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
        errors.value = error.errors;
    } else {
        notifyError(error.message);
    }
}

async function run(action, successMessage) {
    busy.value = true;
    errors.value = {};

    try {
        const response = await action();
        if (successMessage) {
            notifySuccess(successMessage);
        }
        return response;
    } catch (error) {
        showError(error);
        return null;
    } finally {
        busy.value = false;
    }
}

async function startAuthenticator() {
    const response = await run(() => api('/admin/api/profile/two-factor/authenticator/setup', { method: 'POST' }));

    if (response) {
        Object.assign(setup, {
            secret: response.data.secret,
            otpauth_uri: response.data.otpauth_uri,
            qr_data_uri: response.data.qr_data_uri,
            code: '',
        });
        await load();
    }
}

async function confirmAuthenticator() {
    const response = await run(
        () => api('/admin/api/profile/two-factor/authenticator/confirm', { method: 'POST', body: { code: setup.code } }),
        t('app.two_factor.saved_authenticator'),
    );

    if (response) {
        Object.assign(setup, { secret: '', otpauth_uri: '', qr_data_uri: '', code: '' });
        await load();
    }
}

async function copySecret() {
    try {
        await navigator.clipboard.writeText(setup.secret);
        notifySuccess(t('app.two_factor.copied'));
    } catch {
        notifyError(t('app.feedback.server_error'));
    }
}

async function sendEmailCode() {
    const response = await run(() => api('/admin/api/profile/two-factor/email/send', { method: 'POST' }), t('app.two_factor.email_sent'));

    if (response) {
        emailCode.sent = true;
    }
}

async function confirmEmail() {
    const response = await run(
        () => api('/admin/api/profile/two-factor/email/confirm', { method: 'POST', body: { code: emailCode.code } }),
        t('app.two_factor.saved_email'),
    );

    if (response) {
        Object.assign(emailCode, { sent: false, code: '' });
        await load();
    }
}

function startTurnOff(method) {
    errors.value = {};
    Object.assign(turnOff, { method, code: '', sent: false });
}

function cancelTurnOff() {
    errors.value = {};
    Object.assign(turnOff, { method: null, code: '', sent: false });
}

// Email codes can only be switched off with a code sent to the account's email.
async function sendTurnOffEmailCode() {
    const response = await run(
        () => api('/admin/api/profile/two-factor/email/disable-code', { method: 'POST' }),
        t('app.two_factor.email_sent'),
    );

    if (response) {
        turnOff.sent = true;
    }
}

async function confirmTurnOff() {
    const confirmed = await confirmAction({ title: t('app.two_factor.turn_off'), text: t('app.two_factor.card_title'), danger: true });

    if (!confirmed) {
        return;
    }

    const response = await run(
        () => api(`/admin/api/profile/two-factor/${turnOff.method}`, { method: 'DELETE', body: { code: turnOff.code } }),
        t('app.two_factor.disabled'),
    );

    if (response) {
        cancelTurnOff();
        await load();
    }
}

function label(name) {
    return t(`app.two_factor.${name}`);
}

onMounted(load);
</script>

<template>
    <div class="card">
        <div class="card-header fw-semibold">{{ t('app.two_factor.card_title') }}</div>
        <div class="card-body">
            <p class="small text-body-secondary">{{ t('app.two_factor.card_intro') }}</p>

            <div v-if="loadError" class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
                <span>{{ loadError }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
            </div>

            <div v-else-if="!status" class="small text-body-secondary">{{ t('app.common.loading') }}</div>

            <div v-else class="vstack gap-3">
                <!-- Authenticator app -->
                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold">{{ label('authenticator_title') }}</div>
                            <div class="small text-body-secondary">{{ label('authenticator_desc') }}</div>
                        </div>
                        <span class="badge" :class="status.authenticator.enabled ? 'text-bg-success' : 'text-bg-secondary'">
                            {{ status.authenticator.enabled ? label('enabled') : label('disabled_badge') }}
                        </span>
                    </div>

                    <div v-if="!status.authenticator.enabled" class="mt-3">
                        <button v-if="!setup.secret" type="button" class="btn btn-sm btn-outline-primary" :disabled="busy" @click="startAuthenticator">
                            {{ label('setup') }}
                        </button>

                        <div v-else>
                            <p class="small mb-2">{{ label('scan_qr') }}</p>
                            <div class="text-center mb-3">
                                <img :src="setup.qr_data_uri" :alt="label('qr_alt')" width="240" height="240" class="bg-white p-2 rounded">
                            </div>
                            <p class="small mb-2">{{ label('setup_help') }}</p>
                            <label class="form-label small mb-1">{{ label('secret_label') }}</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control font-monospace" :value="setup.secret" readonly>
                                <button type="button" class="btn btn-outline-secondary" @click="copySecret">{{ label('copy') }}</button>
                            </div>
                            <label class="form-label small mb-1" for="tf-app-code">{{ label('confirm_code') }}</label>
                            <div class="d-flex gap-2">
                                <input id="tf-app-code" v-model="setup.code" type="text" inputmode="numeric" maxlength="6" class="form-control"
                                       :class="{ 'is-invalid': firstError(errors, 'code') }" placeholder="000000">
                                <button type="button" class="btn btn-primary text-nowrap" :disabled="busy || setup.code.length !== 6" @click="confirmAuthenticator">
                                    {{ label('turn_on_app') }}
                                </button>
                            </div>
                            <div v-if="firstError(errors, 'code')" class="invalid-feedback d-block">{{ firstError(errors, 'code') }}</div>
                        </div>
                    </div>
                    <div v-else class="mt-3">
                        <button v-if="turnOff.method !== 'authenticator'" type="button" class="btn btn-sm btn-outline-danger" @click="startTurnOff('authenticator')">
                            {{ label('turn_off') }}
                        </button>
                    </div>
                </div>

                <!-- Email code: shown only when Resend is connected, or still on -->
                <div v-if="status.email.available || status.email.enabled" class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold">{{ label('email_title') }}</div>
                            <div class="small text-body-secondary">{{ label('email_desc') }}</div>
                        </div>
                        <span class="badge" :class="status.email.enabled ? 'text-bg-success' : 'text-bg-secondary'">
                            {{ status.email.enabled ? label('enabled') : label('disabled_badge') }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <template v-if="!status.email.enabled && status.email.available">
                            <p v-if="!emailCode.sent" class="small mb-2">{{ label('email_enable_help') }}</p>
                            <button v-if="!emailCode.sent" type="button" class="btn btn-sm btn-outline-primary" :disabled="busy" @click="sendEmailCode">
                                {{ label('send_code') }}
                            </button>
                            <div v-else class="d-flex gap-2">
                                <input v-model="emailCode.code" type="text" inputmode="numeric" maxlength="6" class="form-control" placeholder="000000"
                                       :class="{ 'is-invalid': firstError(errors, 'code') }">
                                <button type="button" class="btn btn-primary text-nowrap" :disabled="busy || emailCode.code.length !== 6" @click="confirmEmail">
                                    {{ label('turn_on_app') }}
                                </button>
                            </div>
                            <div v-if="firstError(errors, 'code')" class="invalid-feedback d-block">{{ firstError(errors, 'code') }}</div>
                        </template>
                        <template v-else-if="status.email.enabled">
                            <button v-if="turnOff.method !== 'email'" type="button" class="btn btn-sm btn-outline-danger" @click="startTurnOff('email')">
                                {{ label('turn_off') }}
                            </button>
                        </template>
                        <div v-if="!status.email.available" class="small text-warning mt-2">{{ label('email_unavailable_note') }}</div>
                    </div>
                </div>

                <!-- WhatsApp code: shown only when OnSend is connected, or still on -->
                <div v-if="status.whatsapp.available || status.whatsapp.enabled" class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold">{{ label('whatsapp_title') }}</div>
                            <div class="small text-body-secondary">{{ label('whatsapp_desc') }}</div>
                        </div>
                        <span class="badge" :class="status.whatsapp.enabled ? 'text-bg-success' : 'text-bg-secondary'">
                            {{ status.whatsapp.enabled ? label('enabled') : label('disabled_badge') }}
                        </span>
                    </div>
                    <div v-if="status.whatsapp.enabled" class="small text-warning mt-2">{{ label('whatsapp_unavailable_note') }}</div>
                </div>

                <!-- A code from the same method is needed to switch it off -->
                <form v-if="turnOff.method" class="border rounded p-3 bg-body-tertiary" novalidate @submit.prevent="confirmTurnOff">
                    <div class="fw-semibold mb-1">{{ label('turn_off') }}</div>

                    <template v-if="turnOff.method === 'authenticator'">
                        <p class="small mb-2">{{ label('turn_off_app_help') }}</p>
                    </template>
                    <template v-else-if="turnOff.method === 'email'">
                        <p class="small mb-2">{{ turnOff.sent ? label('turn_off_email_help') : label('turn_off_email_send_help') }}</p>
                        <button v-if="!turnOff.sent" type="button" class="btn btn-sm btn-outline-primary mb-2" :disabled="busy" @click="sendTurnOffEmailCode">
                            {{ label('send_code') }}
                        </button>
                    </template>

                    <div v-if="turnOff.method === 'authenticator' || turnOff.sent" class="d-flex gap-2">
                        <input v-model="turnOff.code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="form-control"
                               placeholder="000000" :aria-label="label('code_label')" :class="{ 'is-invalid': firstError(errors, 'code') }">
                        <button type="submit" class="btn btn-danger text-nowrap" :disabled="busy || turnOff.code.length !== 6">{{ label('turn_off') }}</button>
                        <button type="button" class="btn btn-outline-secondary" @click="cancelTurnOff">{{ label('cancel') }}</button>
                    </div>
                    <div v-else class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" @click="cancelTurnOff">{{ label('cancel') }}</button>
                    </div>
                    <div v-if="firstError(errors, 'code')" class="invalid-feedback d-block">{{ firstError(errors, 'code') }}</div>
                </form>
            </div>
        </div>
    </div>
</template>
