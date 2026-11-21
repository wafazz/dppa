<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api, ApiError, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { t } from '../lib/i18n';

const count = ref(0);
const loaded = ref(false);
const sending = ref(false);
const loadError = ref('');
const errors = ref({});
const form = reactive({ subject: '', body: '' });

const canSend = computed(() => loaded.value
    && count.value > 0
    && form.subject.trim() !== ''
    && form.body.trim() !== ''
    && !sending.value);

const fieldError = (name) => firstError(errors.value, name);

async function loadRecipients() {
    try {
        const response = await api('/admin/api/announcements/recipients');
        count.value = response.data.count;
        loaded.value = true;
    } catch (error) {
        loadError.value = error.message;
    }
}

async function send() {
    const confirmed = await confirmAction({
        title: t('app.announcements.confirm_title'),
        text: t('app.announcements.confirm_text', { count: count.value }),
    });

    if (!confirmed) {
        return;
    }

    sending.value = true;
    errors.value = {};

    try {
        const response = await api('/admin/api/announcements', {
            method: 'POST',
            body: { subject: form.subject.trim(), body: form.body.trim() },
        });

        notifySuccess(t('app.announcements.sent', { sent: response.data.sent, failed: response.data.failed }));
        form.subject = '';
        form.body = '';
        await loadRecipients();
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            errors.value = error.errors;
        } else {
            notifyError(error.message);
        }
    } finally {
        sending.value = false;
    }
}

onMounted(loadRecipients);
</script>

<template>
    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <p class="text-body-secondary">{{ t('app.announcements.intro') }}</p>

            <div v-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>

            <div class="card">
                <div class="card-body">
                    <p class="mb-3 fw-semibold">
                        <template v-if="loaded && count > 0">{{ t('app.announcements.recipients', { count }) }}</template>
                        <template v-else-if="loaded">{{ t('app.announcements.none') }}</template>
                    </p>

                    <form novalidate @submit.prevent="send">
                        <div class="mb-3">
                            <label class="form-label" for="announcement-subject">{{ t('app.announcements.subject') }}</label>
                            <input id="announcement-subject" v-model="form.subject" type="text" maxlength="150"
                                   class="form-control" :class="{ 'is-invalid': fieldError('subject') }">
                            <div class="invalid-feedback">{{ fieldError('subject') }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="announcement-body">{{ t('app.announcements.body') }}</label>
                            <textarea id="announcement-body" v-model="form.body" rows="10" maxlength="5000"
                                      class="form-control" :class="{ 'is-invalid': fieldError('body') }"></textarea>
                            <div class="invalid-feedback">{{ fieldError('body') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary" :disabled="!canSend">
                            <span v-if="sending" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                            {{ sending ? t('app.announcements.sending') : t('app.announcements.send') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
