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
    /** Existing appointment when editing; null when creating. */
    appointment: { type: Object, default: null },
    /** Member preselected when creating from a member profile. */
    presetMember: { type: Object, default: null },
    positions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const form = reactive(emptyForm());

const isEditing = computed(() => props.appointment !== null);

function todayIsoDate() {
    return new Date().toLocaleDateString('en-CA');
}

function emptyForm() {
    return {
        member_id: '',
        chamber_position_id: '',
        term_start: todayIsoDate(),
        term_end: '',
        is_current: true,
        notes: '',
    };
}

function memberLabel(member) {
    return `${member.member_no ? member.member_no + ' - ' : ''}${member.applicant_name} (${member.business_name})`;
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};

        if (props.appointment) {
            Object.assign(form, {
                member_id: String(props.appointment.member_id),
                chamber_position_id: String(props.appointment.chamber_position_id),
                term_start: props.appointment.term_start ?? '',
                term_end: props.appointment.term_end ?? '',
                is_current: Boolean(props.appointment.is_current),
                notes: props.appointment.notes ?? '',
            });
        } else {
            Object.assign(form, emptyForm(), {
                member_id: props.presetMember ? String(props.presetMember.id) : '',
            });
        }
    },
);

const memberOptions = computed(() => {
    const member = props.appointment?.member ?? props.presetMember;

    return member ? [{ id: member.id, text: memberLabel(member) }] : [];
});

const positionOptions = computed(() => props.positions
    .filter((position) => position.is_active || String(position.id) === String(form.chamber_position_id))
    .map((position) => ({ id: position.id, text: position.name_bm })));

const fieldError = (name) => firstError(errors.value, name);

async function submit() {
    const confirmed = await confirmAction({
        title: isEditing.value ? t('app.members.appointment.confirm_edit_title') : t('app.members.appointment.confirm_new_title'),
        text: t('app.members.appointment.confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    const body = {
        member_id: Number(form.member_id),
        chamber_position_id: Number(form.chamber_position_id),
        term_start: form.term_start,
        term_end: form.term_end || null,
        is_current: form.is_current,
        notes: form.notes || null,
    };

    try {
        const response = isEditing.value
            ? await api(`/admin/api/appointments/${props.appointment.id}`, { method: 'PUT', body })
            : await api('/admin/api/appointments', { method: 'POST', body });

        notifySuccess(isEditing.value ? t('app.members.appointment.saved_edit') : t('app.members.appointment.saved_new'));
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
    <AppModal :open="open" :title="isEditing ? t('app.members.appointment.title_edit') : t('app.members.appointment.title_new')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="appointment-member">{{ t('app.members.appointment.member') }} <span class="text-danger">*</span></label>
                        <AppSelect2
                            id="appointment-member"
                            v-model="form.member_id"
                            :options="memberOptions"
                            :ajax-url="isEditing ? null : '/admin/api/members/options'"
                            :disabled="isEditing"
                            :placeholder="t('app.members.appointment.member_placeholder')"
                            :invalid="!!fieldError('member_id')"
                        />
                        <div class="invalid-feedback d-block" v-if="fieldError('member_id')">{{ fieldError('member_id') }}</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="appointment-position">{{ t('app.members.appointment.position') }} <span class="text-danger">*</span></label>
                        <AppSelect2 id="appointment-position" v-model="form.chamber_position_id" :options="positionOptions"
                                    :placeholder="t('app.members.appointment.position_placeholder')" :invalid="!!fieldError('chamber_position_id')" />
                        <div class="invalid-feedback d-block" v-if="fieldError('chamber_position_id')">{{ fieldError('chamber_position_id') }}</div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="appointment-start">{{ t('app.members.appointment.start') }} <span class="text-danger">*</span></label>
                        <input id="appointment-start" v-model="form.term_start" type="date" class="form-control"
                               :class="{ 'is-invalid': fieldError('term_start') }" data-autofocus>
                        <div class="invalid-feedback">{{ fieldError('term_start') }}</div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="appointment-end">{{ t('app.members.appointment.end') }}</label>
                        <input id="appointment-end" v-model="form.term_end" type="date" class="form-control"
                               :class="{ 'is-invalid': fieldError('term_end') }">
                        <div class="invalid-feedback">{{ fieldError('term_end') }}</div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input id="appointment-current" v-model="form.is_current" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="appointment-current">{{ t('app.members.appointment.current') }}</label>
                        </div>
                        <div class="invalid-feedback d-block" v-if="fieldError('is_current')">{{ fieldError('is_current') }}</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="appointment-notes">{{ t('app.members.appointment.notes') }}</label>
                        <AppRichText id="appointment-notes" v-model="form.notes" :placeholder="t('app.members.appointment.notes_placeholder')" />
                        <div class="invalid-feedback d-block" v-if="fieldError('notes')">{{ fieldError('notes') }}</div>
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
