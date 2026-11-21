<script setup>
import { onMounted, ref } from 'vue';
import InvitationModal from '../components/forms/InvitationModal.vue';
import { api } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { formatDateTime } from '../lib/format';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    currentUser: { type: Object, required: true },
});

const invitations = ref([]);
const meta = ref(null);
const page = ref(1);
const loading = ref(false);
const loadError = ref('');
const showForm = ref(false);

const registrationOpen = ref(true);
const switching = ref(false);

function statusClass(code) {
    return {
        active: 'text-bg-success',
        used_up: 'text-bg-secondary',
        expired: 'text-bg-warning',
        revoked: 'text-bg-danger',
    }[code] ?? 'text-bg-secondary';
}

async function loadRegistration() {
    if (!props.can.manageRegistration) {
        return;
    }

    try {
        const response = await api('/admin/api/registration');
        registrationOpen.value = response.data.open;
    } catch (error) {
        notifyError(error.message);
    }
}

// The switch stays where it was if the administrator cancels the confirmation.
async function toggleRegistration(event) {
    const next = event.target.checked;

    const confirmed = await confirmAction({
        title: next ? t('app.invitations.registration_confirm_on') : t('app.invitations.registration_confirm_off'),
        text: t('app.invitations.registration_confirm_text'),
        danger: !next,
    });

    if (!confirmed) {
        event.target.checked = registrationOpen.value;
        return;
    }

    switching.value = true;

    try {
        const response = await api('/admin/api/registration', { method: 'PUT', body: { open: next } });
        registrationOpen.value = response.data.open;
        notifySuccess(t('app.invitations.registration_saved'));
    } catch (error) {
        event.target.checked = registrationOpen.value;
        notifyError(error.message);
    } finally {
        switching.value = false;
    }
}

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api(`/admin/api/invitations?page=${page.value}`);
        invitations.value = response.data;
        meta.value = response.meta;
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function revoke(invitation) {
    const confirmed = await confirmAction({
        title: t('app.invitations.revoke_title'),
        text: t('app.invitations.revoke_text'),
        danger: true,
        confirmButtonText: t('app.invitations.revoke_button'),
    });

    if (!confirmed) {
        return;
    }

    try {
        await api(`/admin/api/invitations/${invitation.id}/revoke`, { method: 'POST' });
        notifySuccess(t('app.invitations.revoked'));
        load();
    } catch (error) {
        notifyError(error.message);
    }
}

async function copy(url) {
    try {
        await navigator.clipboard.writeText(url);
        notifySuccess(t('app.invitations.link_copied'));
    } catch {
        notifyError(t('app.invitations.copy_failed'));
    }
}

function goToPage(target) {
    if (!meta.value || target < 1 || target > meta.value.last_page) {
        return;
    }

    page.value = target;
    load();
}

onMounted(() => {
    loadRegistration();
    load();
});
</script>

<template>
    <div>
        <div v-if="can.manageRegistration" class="card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-semibold">{{ t('app.invitations.registration_title') }}</div>
                    <div class="small text-body-secondary">
                        {{ registrationOpen ? t('app.invitations.registration_on_note') : t('app.invitations.registration_off_note') }}
                    </div>
                </div>
                <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                    <input id="registration-switch" class="form-check-input mt-0" type="checkbox" role="switch"
                           style="width: 2.6em; height: 1.4em" :checked="registrationOpen" :disabled="switching"
                           @change="toggleRegistration">
                    <label class="form-check-label fw-semibold" for="registration-switch">
                        {{ registrationOpen ? t('app.invitations.registration_on') : t('app.invitations.registration_off') }}
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ t('app.invitations.header') }}</span>
                <button type="button" class="btn btn-sm btn-primary" @click="showForm = true">{{ t('app.invitations.new') }}</button>
            </div>

            <div v-if="loadError" class="alert alert-danger m-3 d-flex flex-wrap justify-content-between align-items-center" role="alert">
                <span>{{ t('app.invitations.load_error', { message: loadError }) }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.invitations.col_note') }}</th>
                            <th>{{ t('app.invitations.col_created_by') }}</th>
                            <th>{{ t('app.invitations.col_expires') }}</th>
                            <th>{{ t('app.invitations.col_uses') }}</th>
                            <th>{{ t('app.invitations.col_status') }}</th>
                            <th><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="text-center py-4">{{ t('app.invitations.loading') }}</td>
                        </tr>
                        <tr v-else-if="!invitations.length">
                            <td colspan="6" class="text-center py-4 text-body-secondary">{{ t('app.invitations.empty') }}</td>
                        </tr>
                        <template v-else>
                            <tr v-for="invitation in invitations" :key="invitation.id">
                                <td>{{ invitation.note ?? '—' }}</td>
                                <td>{{ invitation.created_by ?? '—' }}</td>
                                <td class="text-nowrap">{{ formatDateTime(invitation.expires_at) }}</td>
                                <td>{{ invitation.used_count }} / {{ invitation.max_uses }}</td>
                                <td><span class="badge" :class="statusClass(invitation.status)">{{ invitation.status_label }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button v-if="invitation.is_usable" type="button" class="btn btn-sm btn-outline-primary me-1"
                                            @click="copy(invitation.url)">{{ t('app.invitations.copy_link') }}</button>
                                    <button v-if="invitation.is_usable" type="button" class="btn btn-sm btn-outline-danger"
                                            @click="revoke(invitation)">{{ t('app.invitations.revoke') }}</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && meta.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">{{ t('app.common.previous') }}</button>
                <span class="small">{{ t('app.common.page_of', { current: meta.current_page, last: meta.last_page }) }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">{{ t('app.common.next') }}</button>
            </div>

            <InvitationModal :open="showForm" @close="showForm = false" @saved="load" />
        </div>
    </div>
</template>
