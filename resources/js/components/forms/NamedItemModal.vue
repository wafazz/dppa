<script setup>
import { reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

/**
 * Add or edit a named list item (chamber position or business category).
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    endpoint: { type: String, required: true },
    item: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const form = reactive({ name_bm: '', name_en: '', sort_order: 0, is_active: true });

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        Object.assign(form, {
            name_bm: props.item?.name_bm ?? '',
            name_en: props.item?.name_en ?? '',
            sort_order: props.item?.sort_order ?? 0,
            is_active: props.item ? Boolean(props.item.is_active) : true,
        });
    },
);

const fieldError = (name) => firstError(errors.value, name);

async function submit() {
    const confirmed = await confirmAction({
        title: props.item ? t('app.feedback.confirm_edit') : t('app.feedback.confirm_new'),
        text: t('app.feedback.confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const body = {
            ...form,
            name_en: form.name_en.trim() === '' ? null : form.name_en.trim(),
            sort_order: Number(form.sort_order),
        };

        const response = props.item
            ? await api(`${props.endpoint}/${props.item.id}`, { method: 'PUT', body })
            : await api(props.endpoint, { method: 'POST', body });

        notifySuccess(t('app.feedback.named_saved'));
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
    <AppModal :open="open" :title="title" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="named-item-bm">{{ t('app.named.name_bm') }} <span class="text-danger">*</span></label>
                    <input id="named-item-bm" v-model="form.name_bm" data-autofocus type="text" maxlength="150"
                           class="form-control" :class="{ 'is-invalid': fieldError('name_bm') }">
                    <div class="invalid-feedback">{{ fieldError('name_bm') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="named-item-en">{{ t('app.named.name_en') }}</label>
                    <input id="named-item-en" v-model="form.name_en" type="text" maxlength="150"
                           class="form-control" :class="{ 'is-invalid': fieldError('name_en') }">
                    <div class="invalid-feedback">{{ fieldError('name_en') }}</div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="named-item-order">{{ t('app.named.order') }}</label>
                        <input id="named-item-order" v-model="form.sort_order" type="number" min="0" max="999"
                               class="form-control" :class="{ 'is-invalid': fieldError('sort_order') }">
                        <div class="invalid-feedback">{{ fieldError('sort_order') }}</div>
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input id="named-item-active" v-model="form.is_active" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="named-item-active">{{ t('app.named.active') }}</label>
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
