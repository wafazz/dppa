<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);

const DAY_OPTIONS = [1, 3, 7, 14, 30];

const expiryOptions = computed(() => DAY_OPTIONS.map((days) => ({
    id: String(days),
    text: t('app.invitations.days', { count: days }),
})));

const saving = ref(false);
const errors = ref({});
const createdUrl = ref('');
const createdEmail = ref('');
const createdEmailed = ref(false);
const copied = ref(false);
const form = reactive({ note: '', recipient_email: '', expires_in_days: '7', max_uses: 1 });

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        createdUrl.value = '';
        createdEmail.value = '';
        createdEmailed.value = false;
        copied.value = false;
        Object.assign(form, { note: '', recipient_email: '', expires_in_days: '7', max_uses: 1 });
    },
);

const fieldError = (name) => firstError(errors.value, name);

async function submit() {
    const confirmed = await confirmAction({
        title: t('app.invitations.confirm_title'),
        text: t('app.invitations.confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const response = await api('/admin/api/invitations', {
            method: 'POST',
            body: {
                note: form.note.trim() === '' ? null : form.note.trim(),
                recipient_email: form.recipient_email.trim() === '' ? null : form.recipient_email.trim(),
                expires_in_days: Number(form.expires_in_days),
                max_uses: Number(form.max_uses),
            },
        });

        createdUrl.value = response.data.url;
        createdEmail.value = form.recipient_email.trim();
        createdEmailed.value = response.emailed === true;
        notifySuccess(t('app.invitations.created'));
        emit('saved', response.data);
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

async function copyLink() {
    try {
        await navigator.clipboard.writeText(createdUrl.value);
        copied.value = true;
    } catch {
        notifyError(t('app.invitations.copy_failed'));
    }
}
</script>

<template>
    <AppModal :open="open" :title="t('app.invitations.modal_title')" @close="emit('close')">
        <div v-if="createdUrl" class="modal-body">
            <p v-if="createdEmailed" class="alert alert-success small py-2">{{ t('app.invitations.emailed', { email: createdEmail }) }}</p>
            <p v-else-if="createdEmail" class="alert alert-warning small py-2">{{ t('app.invitations.not_emailed') }}</p>
            <p class="mb-2">{{ t('app.invitations.ready') }}</p>
            <div class="input-group mb-2">
                <input :value="createdUrl" readonly class="form-control" :aria-label="t('app.invitations.link_label')">
                <button type="button" class="btn btn-outline-primary" @click="copyLink">{{ copied ? t('app.common.copied') : t('app.common.copy') }}</button>
            </div>
            <p class="small text-body-secondary mb-0">{{ t('app.invitations.can_revisit') }}</p>
        </div>
        <div v-if="createdUrl" class="modal-footer">
            <button type="button" class="btn btn-primary" @click="emit('close')">{{ t('app.common.done') }}</button>
        </div>

        <form v-else novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="invitation-note">{{ t('app.invitations.field_note') }}</label>
                    <input id="invitation-note" v-model="form.note" data-autofocus type="text" maxlength="255"
                           class="form-control" :class="{ 'is-invalid': fieldError('note') }" :placeholder="t('app.invitations.note_placeholder')">
                    <div class="invalid-feedback">{{ fieldError('note') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="invitation-recipient">{{ t('app.invitations.field_recipient') }}</label>
                    <input id="invitation-recipient" v-model="form.recipient_email" type="email" maxlength="190"
                           class="form-control" :class="{ 'is-invalid': fieldError('recipient_email') }">
                    <div class="invalid-feedback">{{ fieldError('recipient_email') }}</div>
                    <div class="form-text">{{ t('app.invitations.recipient_hint') }}</div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="invitation-expiry">{{ t('app.invitations.field_expiry') }} <span class="text-danger">*</span></label>
                        <AppSelect2 id="invitation-expiry" v-model="form.expires_in_days" :options="expiryOptions"
                                    :placeholder="t('app.invitations.expiry_placeholder')" :invalid="!!fieldError('expires_in_days')" />
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="invitation-uses">{{ t('app.invitations.field_uses') }} <span class="text-danger">*</span></label>
                        <input id="invitation-uses" v-model="form.max_uses" type="number" min="1" max="20"
                               class="form-control" :class="{ 'is-invalid': fieldError('max_uses') }">
                        <div class="invalid-feedback">{{ fieldError('max_uses') }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ saving ? t('app.invitations.creating') : t('app.invitations.create') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
