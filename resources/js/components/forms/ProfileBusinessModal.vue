<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import { api, ApiError, csrfToken, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    profile: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const MAX_SSM_BYTES = 5 * 1024 * 1024;

const saving = ref(false);
const uploading = ref(false);
const errors = ref({});
const form = reactive(emptyForm());
const fileInput = ref(null);

const categoryOptions = computed(() => props.categories
    .filter((category) => category.is_active)
    .map((category) => ({ id: category.id, text: category.name_bm })));
const fieldError = (name) => firstError(errors.value, name);
const busy = computed(() => saving.value || uploading.value);

function emptyForm() {
    return { business_name: '', ssm_no: '', business_category_id: '', business_phone: '' };
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        Object.assign(form, emptyForm(), {
            business_name: props.profile.business_name ?? '',
            ssm_no: props.profile.ssm_no ?? '',
            business_category_id: props.profile.business_category_id ? String(props.profile.business_category_id) : '',
            business_phone: props.profile.business_phone ?? '',
        });
    },
);

function requestBody() {
    return {
        ...form,
        business_category_id: form.business_category_id === '' ? null : Number(form.business_category_id),
    };
}

function handleError(error) {
    if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
        errors.value = error.errors;
    } else {
        notifyError(error.message);
    }
}

// Saving a draft keeps the details editable and does not send them to the administrator.
async function saveDraft() {
    saving.value = true;
    errors.value = {};

    try {
        const response = await api('/admin/api/profile/business', { method: 'PUT', body: requestBody() });
        notifySuccess(t('app.profile.business_draft_saved'));
        emit('saved', response.data);
        emit('close');
    } catch (error) {
        handleError(error);
    } finally {
        saving.value = false;
    }
}

// Submitting saves the current details first, then sends them. The server checks everything required.
async function submit() {
    const confirmed = await confirmAction({ title: t('app.profile.business_submit'), text: t('app.profile.business_submit_hint') });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        await api('/admin/api/profile/business', { method: 'PUT', body: requestBody() });
        const response = await api('/admin/api/profile/business/submit', { method: 'POST' });
        notifySuccess(t('app.profile.business_submitted_done'));
        emit('saved', response.data);
        emit('close');
    } catch (error) {
        handleError(error);
    } finally {
        saving.value = false;
    }
}

async function uploadDocument(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    if (file.size > MAX_SSM_BYTES) {
        errors.value = { ssm: [t('app.profile.business_ssm_too_large')] };
        return;
    }

    uploading.value = true;
    errors.value = {};

    try {
        const body = new FormData();
        body.append('ssm', file);

        const response = await fetch('/admin/api/profile/business/document', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body,
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const first = Object.values(data.errors ?? {})[0]?.[0];
            errors.value = { ssm: [first ?? data.message ?? t('app.feedback.server_error')] };
            return;
        }

        notifySuccess(t('app.profile.business_ssm_uploaded'));
        emit('saved', data.data);
    } catch {
        notifyError(t('app.feedback.server_error'));
    } finally {
        uploading.value = false;
    }
}
</script>

<template>
    <AppModal :open="open" :title="t('app.profile.business_title')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="saveDraft">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="business-name">{{ t('app.profile.business_name') }} <span class="text-danger">*</span></label>
                        <input id="business-name" v-model="form.business_name" data-autofocus type="text" maxlength="190"
                               class="form-control" :class="{ 'is-invalid': fieldError('business_name') }">
                        <div class="invalid-feedback">{{ fieldError('business_name') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="business-ssm">{{ t('app.profile.ssm_no') }} <span class="text-danger">*</span></label>
                        <input id="business-ssm" v-model="form.ssm_no" type="text" maxlength="60"
                               class="form-control" :class="{ 'is-invalid': fieldError('ssm_no') }">
                        <div class="invalid-feedback">{{ fieldError('ssm_no') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="business-category">{{ t('app.profile.business_category') }}</label>
                        <AppSelect2 id="business-category" v-model="form.business_category_id" :options="categoryOptions"
                                    :placeholder="t('app.profile.category_choose')" :invalid="!!fieldError('business_category_id')" />
                        <div v-if="fieldError('business_category_id')" class="invalid-feedback d-block">{{ fieldError('business_category_id') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="business-phone">{{ t('app.profile.business_phone') }}</label>
                        <input id="business-phone" v-model="form.business_phone" type="text" maxlength="30"
                               class="form-control" :class="{ 'is-invalid': fieldError('business_phone') }">
                        <div class="invalid-feedback">{{ fieldError('business_phone') }}</div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="fw-semibold mb-1">
                                {{ t('app.profile.business_ssm_title') }} <span class="text-danger">*</span>
                            </div>
                            <div class="small text-body-secondary mb-2">{{ t('app.profile.business_ssm_required') }}</div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span v-if="profile.has_business_document" class="small">
                                    <a href="/admin/api/profile/business/document" target="_blank" rel="noopener">
                                        {{ t('app.profile.business_ssm_view') }}
                                    </a>
                                    <span class="text-body-secondary">({{ profile.business_document_name }})</span>
                                </span>
                                <span v-else class="small text-body-secondary">{{ t('app.profile.business_ssm_none') }}</span>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" :disabled="busy" @click="fileInput?.click()">
                                    <span v-if="uploading" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                                    {{ t('app.profile.business_ssm_upload') }}
                                </button>
                                <input ref="fileInput" type="file" accept="application/pdf,image/jpeg,image/png" class="d-none" @change="uploadDocument">
                            </div>
                            <div v-if="fieldError('ssm')" class="invalid-feedback d-block">{{ fieldError('ssm') }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-wrap">
                <button type="button" class="btn btn-outline-secondary me-auto" :disabled="busy" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn btn-outline-primary" :disabled="busy">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ t('app.profile.business_save_draft') }}
                </button>
                <button type="button" class="btn btn-primary" :disabled="busy" @click="submit">
                    {{ t('app.profile.business_submit') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
