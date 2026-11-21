<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import AppRichText from '../AppRichText.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    member: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const form = reactive(emptyForm());
const openingCategoryId = ref('');

function emptyForm() {
    return {
        applicant_name: '',
        business_name: '',
        business_category_id: '',
        business_address: '',
        phone: '',
        email: '',
        ssm_no: '',
        business_description: '',
        business_needs: '',
    };
}

function fromMember(member) {
    return {
        applicant_name: member.applicant_name ?? '',
        business_name: member.business_name ?? '',
        business_category_id: String(member.business_category?.id ?? member.business_category_id ?? ''),
        business_address: member.business_address ?? '',
        phone: member.phone ?? '',
        email: member.email ?? '',
        ssm_no: member.ssm_no ?? '',
        business_description: member.business_description ?? '',
        business_needs: member.business_needs ?? '',
    };
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.member) {
            errors.value = {};
            Object.assign(form, fromMember(props.member));
            openingCategoryId.value = String(form.business_category_id);
        }
    },
);

// Options are fixed when the modal opens, so picking a category does not rebuild the dropdown.
const categoryOptions = computed(() => props.categories
    .filter((category) => category.is_active || String(category.id) === openingCategoryId.value)
    .map((category) => ({ id: category.id, text: category.name_bm })));

const fieldError = (name) => firstError(errors.value, name);

async function submit() {
    const confirmed = await confirmAction({
        title: t('app.members.edit.confirm_title'),
        text: t('app.members.edit.confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const response = await api(`/admin/api/members/${props.member.id}`, {
            method: 'PUT',
            body: {
                ...form,
                business_category_id: Number(form.business_category_id),
                ssm_no: form.ssm_no.trim() === '' ? null : form.ssm_no.trim(),
            },
        });

        notifySuccess(t('app.members.edit.saved'));
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
    <AppModal :open="open" :title="t('app.members.edit.title')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="edit-applicant-name">{{ t('app.members.edit.applicant_name') }} <span class="text-danger">*</span></label>
                        <input id="edit-applicant-name" v-model="form.applicant_name" data-autofocus type="text" maxlength="150"
                               class="form-control" :class="{ 'is-invalid': fieldError('applicant_name') }">
                        <div class="invalid-feedback">{{ fieldError('applicant_name') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="edit-business-name">{{ t('app.members.edit.business_name') }} <span class="text-danger">*</span></label>
                        <input id="edit-business-name" v-model="form.business_name" type="text" maxlength="200"
                               class="form-control" :class="{ 'is-invalid': fieldError('business_name') }">
                        <div class="invalid-feedback">{{ fieldError('business_name') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="edit-category">{{ t('app.members.edit.category') }} <span class="text-danger">*</span></label>
                        <AppSelect2 id="edit-category" v-model="form.business_category_id" :options="categoryOptions"
                                    :placeholder="t('app.members.edit.choose_category')" :invalid="!!fieldError('business_category_id')" />
                        <div class="invalid-feedback d-block" v-if="fieldError('business_category_id')">{{ fieldError('business_category_id') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="edit-ssm">{{ t('app.members.edit.ssm') }}</label>
                        <input id="edit-ssm" v-model="form.ssm_no" type="text" maxlength="50"
                               class="form-control" :class="{ 'is-invalid': fieldError('ssm_no') }">
                        <div class="invalid-feedback">{{ fieldError('ssm_no') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="edit-address">{{ t('app.members.edit.address') }} <span class="text-danger">*</span></label>
                        <input id="edit-address" v-model="form.business_address" type="text" maxlength="500"
                               class="form-control" :class="{ 'is-invalid': fieldError('business_address') }">
                        <div class="invalid-feedback">{{ fieldError('business_address') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="edit-phone">{{ t('app.members.edit.phone') }} <span class="text-danger">*</span></label>
                        <input id="edit-phone" v-model="form.phone" type="tel" maxlength="30"
                               class="form-control" :class="{ 'is-invalid': fieldError('phone') }">
                        <div class="invalid-feedback">{{ fieldError('phone') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="edit-email">{{ t('app.members.edit.email') }} <span class="text-danger">*</span></label>
                        <input id="edit-email" v-model="form.email" type="email" maxlength="190"
                               class="form-control" :class="{ 'is-invalid': fieldError('email') }">
                        <div class="invalid-feedback">{{ fieldError('email') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="edit-description">{{ t('app.members.edit.description') }} <span class="text-danger">*</span></label>
                        <AppRichText id="edit-description" v-model="form.business_description"
                                     :placeholder="t('app.members.edit.description_placeholder')" :invalid="!!fieldError('business_description')" />
                        <div class="invalid-feedback d-block" v-if="fieldError('business_description')">{{ fieldError('business_description') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="edit-needs">{{ t('app.members.edit.needs') }} <span class="text-danger">*</span></label>
                        <AppRichText id="edit-needs" v-model="form.business_needs"
                                     :placeholder="t('app.members.edit.needs_placeholder')" :invalid="!!fieldError('business_needs')" />
                        <div class="invalid-feedback d-block" v-if="fieldError('business_needs')">{{ fieldError('business_needs') }}</div>
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
