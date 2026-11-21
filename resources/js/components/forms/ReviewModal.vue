<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import AppRichText from '../AppRichText.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { statusOptions } from '../../lib/format';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    member: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const status = ref('');
const note = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.member) {
            return;
        }

        errors.value = {};
        note.value = '';
        status.value = props.member.status === 'pending' ? 'approved' : 'pending';
    },
);

const decisionOptions = computed(() => statusOptions().filter((option) => option.id !== props.member?.status));
const fieldError = (name) => firstError(errors.value, name);
const isRejecting = computed(() => status.value === 'rejected');

async function submit() {
    const titles = {
        approved: t('app.members.review.confirm_approve'),
        rejected: t('app.members.review.confirm_reject'),
        pending: t('app.members.review.confirm_pending'),
    };

    const confirmed = await confirmAction({
        title: titles[status.value] ?? t('app.members.review.confirm_default'),
        text: status.value === 'approved'
            ? t('app.members.review.confirm_approve_text')
            : t('app.members.review.confirm_other_text'),
        danger: isRejecting.value,
        confirmButtonText: isRejecting.value ? t('app.members.review.confirm_reject_button') : t('app.common.yes_continue'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const response = await api(`/admin/api/members/${props.member.id}/status`, {
            method: 'POST',
            body: { status: status.value, note: note.value || null },
        });

        const memberNo = response.data.member_no;
        notifySuccess(status.value === 'approved' && memberNo
            ? t('app.members.review.approved_with_number', { number: memberNo })
            : t('app.members.review.updated'));

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
    <AppModal :open="open" :title="t('app.members.review.title')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <p class="mb-3">
                    <strong>{{ member?.applicant_name }}</strong> · {{ member?.business_name }}<br>
                    <span class="text-body-secondary small">
                        {{ t('app.members.review.current_status', { status: member?.status_label }) }}
                    </span>
                </p>

                <div class="mb-3">
                    <label class="form-label" for="review-status">{{ t('app.members.review.decision') }} <span class="text-danger">*</span></label>
                    <AppSelect2 id="review-status" v-model="status" :options="decisionOptions" :placeholder="t('app.members.review.choose')"
                                :invalid="!!fieldError('status')" />
                    <div class="invalid-feedback d-block" v-if="fieldError('status')">{{ fieldError('status') }}</div>
                </div>

                <div>
                    <label class="form-label" for="review-note">
                        {{ isRejecting ? t('app.members.review.note') : t('app.members.review.note_optional') }}
                        <span v-if="isRejecting" class="text-danger">*</span>
                    </label>
                    <AppRichText id="review-note" v-model="note" :placeholder="t('app.members.review.note_placeholder')"
                                 :invalid="!!fieldError('note')" />
                    <div class="invalid-feedback d-block" v-if="fieldError('note')">{{ fieldError('note') }}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn" :class="isRejecting ? 'btn-danger' : 'btn-primary'" :disabled="saving || !status">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ saving ? t('app.common.saving') : t('app.members.review.submit') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
