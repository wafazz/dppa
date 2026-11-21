<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    profile: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const form = reactive(emptyForm());

const stateOptions = computed(() => (props.profile.states ?? []).map((state) => ({ id: state, text: state })));
const fieldError = (name) => firstError(errors.value, name);

function emptyForm() {
    return { name: '', address_line1: '', address_line2: '', postcode: '', city: '', state: '' };
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        Object.assign(form, emptyForm(), {
            name: props.profile.name ?? '',
            address_line1: props.profile.address_line1 ?? '',
            address_line2: props.profile.address_line2 ?? '',
            postcode: props.profile.postcode ?? '',
            city: props.profile.city ?? '',
            state: props.profile.state ?? '',
        });
    },
);

async function submit() {
    saving.value = true;
    errors.value = {};

    try {
        const response = await api('/admin/api/profile', { method: 'PUT', body: { ...form } });
        notifySuccess(t('app.profile.personal_saved'));
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
    <AppModal :open="open" :title="t('app.profile.personal_title')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="personal-name">{{ t('app.profile.name') }} <span class="text-danger">*</span></label>
                        <input id="personal-name" v-model="form.name" data-autofocus type="text" maxlength="150" required
                               class="form-control" :class="{ 'is-invalid': fieldError('name') }">
                        <div class="invalid-feedback">{{ fieldError('name') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="personal-address1">{{ t('app.profile.address_line1') }} <span class="text-danger">*</span></label>
                        <input id="personal-address1" v-model="form.address_line1" type="text" maxlength="190" required
                               class="form-control" :class="{ 'is-invalid': fieldError('address_line1') }">
                        <div class="invalid-feedback">{{ fieldError('address_line1') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="personal-address2">{{ t('app.profile.address_line2') }}</label>
                        <input id="personal-address2" v-model="form.address_line2" type="text" maxlength="190"
                               class="form-control" :class="{ 'is-invalid': fieldError('address_line2') }">
                        <div class="invalid-feedback">{{ fieldError('address_line2') }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="personal-postcode">{{ t('app.profile.postcode') }}</label>
                        <input id="personal-postcode" v-model="form.postcode" type="text" inputmode="numeric" maxlength="5"
                               class="form-control" :class="{ 'is-invalid': fieldError('postcode') }">
                        <div class="invalid-feedback">{{ fieldError('postcode') }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="personal-city">{{ t('app.profile.city') }}</label>
                        <input id="personal-city" v-model="form.city" type="text" maxlength="100"
                               class="form-control" :class="{ 'is-invalid': fieldError('city') }">
                        <div class="invalid-feedback">{{ fieldError('city') }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="personal-state">{{ t('app.profile.state') }}</label>
                        <AppSelect2 id="personal-state" v-model="form.state" :options="stateOptions"
                                    :placeholder="t('app.profile.state_choose')" :invalid="!!fieldError('state')" />
                        <div v-if="fieldError('state')" class="invalid-feedback d-block">{{ fieldError('state') }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn btn-primary" :disabled="saving || !form.name.trim() || !form.address_line1.trim()">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ saving ? t('app.common.saving') : t('app.common.save') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
