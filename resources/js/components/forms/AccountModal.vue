<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import AppSelect2 from '../AppSelect2.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Existing account when editing; null when creating. */
    account: { type: Object, default: null },
    /** Management positions visible to the signed-in user. */
    positions: { type: Array, default: () => [] },
    currentUser: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const setupUrl = ref('');
const form = reactive(emptyForm());
const isEditing = computed(() => props.account !== null);

function emptyForm() {
    return {
        name: '',
        email: '',
        account_type: 'staff',
        password: '',
        send_setup_link: true,
        role: 'committee',
        can_invite: false,
        account_position_id: '',
        is_active: true,
    };
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        setupUrl.value = '';
        Object.assign(form, emptyForm());

        if (props.account) {
            Object.assign(form, {
                name: props.account.name,
                email: props.account.email,
                account_type: props.account.account_type,
                role: props.account.role ?? 'committee',
                can_invite: props.account.can_invite,
                account_position_id: props.account.account_position?.id ? String(props.account.account_position.id) : '',
                is_active: props.account.is_active,
            });
        }
    },
);

// Only the superadmin may create admins; admins may create staff accounts only.
const typeOptions = computed(() => (props.currentUser.isSuperadmin
    ? [{ id: 'admin', text: t('app.enums.account_type.admin') }, { id: 'staff', text: t('app.enums.account_type.staff') }]
    : [{ id: 'staff', text: t('app.enums.account_type.staff') }]));

const roleOptions = computed(() => [
    { id: 'committee', text: t('app.accounts.role_committee') },
    { id: 'viewer', text: t('app.accounts.role_viewer') },
]);

// Admins may give only positions below their own rank. The superadmin may give any position.
const positionOptions = computed(() => props.positions
    .filter((position) => position.is_active || String(position.id) === form.account_position_id)
    .filter((position) => props.currentUser.isSuperadmin
        || String(position.id) === form.account_position_id
        || (props.currentUser.level !== null && position.level > props.currentUser.level))
    .map((position) => ({
        id: position.id,
        text: t('app.accounts.position_option', { name: position.name_bm, level: position.level }),
    })));

const isStaff = computed(() => form.account_type === 'staff');
const fieldError = (name) => firstError(errors.value, name);

async function submit() {
    const confirmed = await confirmAction({
        title: isEditing.value ? t('app.accounts.confirm_edit_title') : t('app.accounts.confirm_new_title'),
        text: t('app.accounts.confirm_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    const body = {
        name: form.name,
        email: form.email,
        role: isStaff.value ? form.role : null,
        can_invite: isStaff.value ? form.can_invite : false,
        account_position_id: isStaff.value && form.account_position_id !== '' ? Number(form.account_position_id) : null,
    };

    // A set-up link replaces the password, so the account holder chooses it.
    const usesSetupLink = !isEditing.value && form.send_setup_link;

    if (usesSetupLink) {
        body.send_setup_link = true;
    } else if (form.password !== '') {
        body.password = form.password;
    }

    try {
        const response = isEditing.value
            ? await api(`/admin/api/accounts/${props.account.id}`, { method: 'PUT', body: { ...body, is_active: form.is_active } })
            : await api('/admin/api/accounts', { method: 'POST', body: { ...body, account_type: form.account_type } });

        const setup = response.setup ?? null;

        if (setup?.emailed) {
            notifySuccess(t('app.accounts.saved_new_emailed'));
        } else {
            notifySuccess(isEditing.value ? t('app.accounts.saved_edit') : t('app.accounts.saved_new'));
        }

        emit('saved', response.data);

        // E-mail is not connected: keep the dialog open so the link can be copied once.
        if (setup && !setup.emailed && setup.url) {
            setupUrl.value = setup.url;
            return;
        }

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
    <AppModal :open="open" :title="isEditing ? t('app.accounts.edit_title') : t('app.accounts.new_title')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="account-name">{{ t('app.accounts.field_name') }} <span class="text-danger">*</span></label>
                        <input id="account-name" v-model="form.name" data-autofocus type="text" maxlength="150"
                               class="form-control" :class="{ 'is-invalid': fieldError('name') }">
                        <div class="invalid-feedback">{{ fieldError('name') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="account-email">{{ t('app.accounts.field_email') }} <span class="text-danger">*</span></label>
                        <input id="account-email" v-model="form.email" type="email" maxlength="190"
                               class="form-control" :class="{ 'is-invalid': fieldError('email') }">
                        <div class="invalid-feedback">{{ fieldError('email') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="account-type">{{ t('app.accounts.field_type') }} <span class="text-danger">*</span></label>
                        <AppSelect2 id="account-type" v-model="form.account_type" :options="typeOptions" :disabled="isEditing"
                                    :placeholder="t('app.accounts.type_placeholder')" :invalid="!!fieldError('account_type')" />
                    </div>
                    <div v-if="!isEditing" class="col-12">
                        <div class="form-check form-switch">
                            <input id="account-send-link" v-model="form.send_setup_link" class="form-check-input" type="checkbox" role="switch">
                            <label class="form-check-label" for="account-send-link">{{ t('app.accounts.field_send_link') }}</label>
                        </div>
                        <div class="form-text">{{ t('app.accounts.send_link_hint') }}</div>
                    </div>
                    <div v-if="isEditing || !form.send_setup_link" class="col-12 col-md-6">
                        <label class="form-label" for="account-password">
                            {{ isEditing ? t('app.accounts.field_password_edit') : t('app.accounts.field_password') }}
                            <span v-if="!isEditing" class="text-danger">*</span>
                        </label>
                        <input id="account-password" v-model="form.password" type="password" autocomplete="new-password" maxlength="255"
                               class="form-control" :class="{ 'is-invalid': fieldError('password') }">
                        <div class="invalid-feedback">{{ fieldError('password') }}</div>
                        <div class="form-text">{{ t('app.accounts.password_hint') }}</div>
                    </div>

                    <template v-if="isStaff">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="account-role">{{ t('app.accounts.field_role') }} <span class="text-danger">*</span></label>
                            <AppSelect2 id="account-role" v-model="form.role" :options="roleOptions" :placeholder="t('app.accounts.role_placeholder')"
                                        :invalid="!!fieldError('role')" />
                            <div class="invalid-feedback d-block" v-if="fieldError('role')">{{ fieldError('role') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="account-position">{{ t('app.accounts.field_position') }}</label>
                            <AppSelect2 id="account-position" v-model="form.account_position_id" :options="positionOptions"
                                        :placeholder="t('app.accounts.position_placeholder')" :invalid="!!fieldError('account_position_id')" />
                            <div class="invalid-feedback d-block" v-if="fieldError('account_position_id')">{{ fieldError('account_position_id') }}</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input id="account-can-invite" v-model="form.can_invite" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="account-can-invite">{{ t('app.accounts.can_invite') }}</label>
                            </div>
                        </div>
                    </template>

                    <div v-if="isEditing" class="col-12">
                        <div class="form-check">
                            <input id="account-active" v-model="form.is_active" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="account-active">{{ t('app.accounts.is_active') }}</label>
                        </div>
                        <div class="invalid-feedback d-block" v-if="fieldError('is_active')">{{ fieldError('is_active') }}</div>
                    </div>
                </div>
            </div>
            <div v-if="setupUrl" class="modal-body pt-0">
                <div class="alert alert-info small mb-0">
                    <div class="fw-semibold mb-1">{{ t('app.accounts.link_title') }}</div>
                    <p class="mb-2">{{ t('app.accounts.link_hint') }}</p>
                    <input type="text" class="form-control form-control-sm" :value="setupUrl" readonly @focus="$event.target.select()">
                </div>
            </div>
            <div class="modal-footer">
                <template v-if="setupUrl">
                    <button type="button" class="btn btn-primary" @click="emit('close')">{{ t('app.accounts.link_done') }}</button>
                </template>
                <template v-else>
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        {{ saving ? t('app.common.saving') : t('app.common.save') }}
                    </button>
                </template>
            </div>
        </form>
    </AppModal>
</template>
