<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** The member being upgraded, or null when the dialog is closed. */
    member: { type: Object, default: null },
    /** Only a superadmin may create admin accounts. */
    isSuperadmin: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const positions = ref([]);
const form = reactive(emptyForm());

function emptyForm() {
    return {
        account_type: 'staff',
        role: 'committee',
        can_invite: false,
        account_position_id: '',
    };
}

const typeOptions = computed(() => (props.isSuperadmin
    ? [{ id: 'staff', text: t('app.members.upgrade.type_staff') }, { id: 'admin', text: t('app.members.upgrade.type_admin') }]
    : [{ id: 'staff', text: t('app.members.upgrade.type_staff') }]));

const roleOptions = computed(() => [
    { id: 'committee', text: t('app.accounts.role_committee') },
    { id: 'viewer', text: t('app.accounts.role_viewer') },
]);

const positionOptions = computed(() => positions.value
    .filter((position) => position.is_active)
    .map((position) => ({
        id: position.id,
        text: t('app.accounts.position_option', { name: position.name_bm, level: position.level }),
    })));

const isStaff = computed(() => form.account_type === 'staff');
const fieldError = (name) => firstError(errors.value, name);

async function loadPositions() {
    try {
        const response = await api('/admin/api/account-positions');
        positions.value = response.data;
    } catch (error) {
        notifyError(error.message);
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        Object.assign(form, emptyForm());
        loadPositions();
    },
);

async function submit() {
    const confirmed = await confirmAction({
        title: t('app.members.upgrade.confirm_title'),
        text: t('app.members.upgrade.confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    const body = {
        account_type: form.account_type,
        role: isStaff.value ? form.role : null,
        can_invite: isStaff.value ? form.can_invite : false,
        account_position_id: isStaff.value && form.account_position_id !== '' ? Number(form.account_position_id) : null,
    };

    try {
        const response = await api(`/admin/api/members/${props.member.id}/upgrade`, { method: 'POST', body });

        notifySuccess(response.message ?? t('app.members.upgrade.done'));
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
    <AppModal :open="open" :title="t('app.members.upgrade.title')" size="modal-lg" @close="emit('close')">
        <form v-if="member" novalidate @submit.prevent="submit">
            <div class="modal-body">
                <p class="text-body-secondary small mb-3">
                    <strong>{{ member.applicant_name }}</strong>. {{ t('app.members.upgrade.intro') }}
                </p>

                <div class="alert alert-danger py-2 small" v-if="fieldError('account')">{{ fieldError('account') }}</div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="upgrade-type">{{ t('app.members.upgrade.field_type') }} <span class="text-danger">*</span></label>
                        <AppSelect2 id="upgrade-type" v-model="form.account_type" :options="typeOptions" :invalid="!!fieldError('account_type')" />
                        <div class="invalid-feedback d-block" v-if="fieldError('account_type')">{{ fieldError('account_type') }}</div>
                    </div>

                    <template v-if="isStaff">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="upgrade-role">{{ t('app.members.upgrade.field_role') }} <span class="text-danger">*</span></label>
                            <AppSelect2 id="upgrade-role" v-model="form.role" :options="roleOptions" :invalid="!!fieldError('role')" />
                            <div class="invalid-feedback d-block" v-if="fieldError('role')">{{ fieldError('role') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="upgrade-position">{{ t('app.members.upgrade.field_position') }}</label>
                            <AppSelect2 id="upgrade-position" v-model="form.account_position_id" :options="positionOptions"
                                        :placeholder="t('app.accounts.position_placeholder')" :invalid="!!fieldError('account_position_id')" />
                            <div class="invalid-feedback d-block" v-if="fieldError('account_position_id')">{{ fieldError('account_position_id') }}</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="upgrade-can-invite" v-model="form.can_invite" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="upgrade-can-invite">{{ t('app.members.upgrade.can_invite') }}</label>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ t('app.members.upgrade.button') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
