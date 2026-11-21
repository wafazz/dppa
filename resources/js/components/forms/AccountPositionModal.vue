<script setup>
import { reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    position: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const form = reactive({ name_bm: '', name_en: '', level: 1, is_active: true });

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        Object.assign(form, {
            name_bm: props.position?.name_bm ?? '',
            name_en: props.position?.name_en ?? '',
            level: props.position?.level ?? 1,
            is_active: props.position ? Boolean(props.position.is_active) : true,
        });
    },
);

const fieldError = (name) => firstError(errors.value, name);

async function submit() {
    const confirmed = await confirmAction({
        title: props.position ? t('app.accounts.position_confirm_edit') : t('app.accounts.position_confirm_new'),
        text: t('app.accounts.position_confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    const body = {
        name_bm: form.name_bm,
        name_en: form.name_en.trim() === '' ? null : form.name_en.trim(),
        level: Number(form.level),
        is_active: form.is_active,
    };

    try {
        const response = props.position
            ? await api(`/admin/api/account-positions/${props.position.id}`, { method: 'PUT', body })
            : await api('/admin/api/account-positions', { method: 'POST', body });

        notifySuccess(t('app.accounts.position_saved'));
        emit('saved', response.data);
        emit('close');
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
</script>

<template>
    <AppModal :open="open" :title="position ? t('app.accounts.position_edit_title') : t('app.accounts.position_new_title')" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="ap-name-bm">{{ t('app.named.name_bm') }} <span class="text-danger">*</span></label>
                    <input id="ap-name-bm" v-model="form.name_bm" data-autofocus type="text" maxlength="150"
                           class="form-control" :class="{ 'is-invalid': fieldError('name_bm') }">
                    <div class="invalid-feedback">{{ fieldError('name_bm') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ap-name-en">{{ t('app.named.name_en') }}</label>
                    <input id="ap-name-en" v-model="form.name_en" type="text" maxlength="150"
                           class="form-control" :class="{ 'is-invalid': fieldError('name_en') }">
                    <div class="invalid-feedback">{{ fieldError('name_en') }}</div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="ap-level">{{ t('app.accounts.position_level') }} <span class="text-danger">*</span></label>
                        <input id="ap-level" v-model="form.level" type="number" min="1" max="999"
                               class="form-control" :class="{ 'is-invalid': fieldError('level') }">
                        <div class="invalid-feedback">{{ fieldError('level') }}</div>
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input id="ap-active" v-model="form.is_active" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="ap-active">{{ t('app.named.active') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ saving ? t('app.common.saving') : t('app.common.save') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
