<script setup>
import { onMounted, ref } from 'vue';
import AccountModal from '../components/forms/AccountModal.vue';
import AccountPositionModal from '../components/forms/AccountPositionModal.vue';
import { api } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    currentUser: { type: Object, required: true },
});

const activeTab = ref('accounts');
const accounts = ref([]);
const positions = ref([]);
const loading = ref(false);
const loadError = ref('');

const showAccount = ref(false);
const accountToEdit = ref(null);
const showPosition = ref(false);
const positionToEdit = ref(null);

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const [accountResponse, positionResponse] = await Promise.all([
            api('/admin/api/accounts'),
            api('/admin/api/account-positions'),
        ]);

        accounts.value = accountResponse.data;
        positions.value = positionResponse.data;
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

function openAccount(account = null) {
    accountToEdit.value = account;
    showAccount.value = true;
}

async function verifyBusiness(account) {
    const confirmed = await confirmAction({
        title: t('app.accounts.verify_business'),
        text: `${account.name} · ${account.business_name ?? ''}`,
    });

    if (!confirmed) {
        return;
    }

    try {
        await api(`/admin/api/accounts/${account.id}/verify-business`, { method: 'POST' });
        notifySuccess(t('app.accounts.business_verified_done'));
        load();
    } catch (error) {
        notifyError(error.message);
    }
}

function openPosition(position = null) {
    positionToEdit.value = position;
    showPosition.value = true;
}

onMounted(() => {
    // Sidebar submenus link here with ?tab=positions for management positions.
    if (new URLSearchParams(window.location.search).get('tab') === 'positions') {
        activeTab.value = 'positions';
    }

    load();
});
</script>

<template>
    <div>
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button" role="tab" class="nav-link" :class="{ active: activeTab === 'accounts' }"
                        :aria-selected="activeTab === 'accounts'" @click="activeTab = 'accounts'">
                    {{ t('app.accounts.tab_accounts') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" role="tab" class="nav-link" :class="{ active: activeTab === 'positions' }"
                        :aria-selected="activeTab === 'positions'" @click="activeTab = 'positions'">
                    {{ t('app.accounts.tab_positions') }}
                </button>
            </li>
        </ul>

        <div v-if="loadError" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.accounts.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
        </div>

        <div v-if="activeTab === 'accounts'" class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ t('app.accounts.header') }}</span>
                <button type="button" class="btn btn-sm btn-primary" @click="openAccount()">{{ t('app.accounts.add') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.accounts.col_name') }}</th>
                            <th>{{ t('app.accounts.col_email') }}</th>
                            <th>{{ t('app.accounts.col_type') }}</th>
                            <th>{{ t('app.accounts.col_role') }}</th>
                            <th>{{ t('app.accounts.col_position') }}</th>
                            <th>{{ t('app.accounts.col_invite') }}</th>
                            <th>{{ t('app.accounts.col_status') }}</th>
                            <th><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="text-center py-4">{{ t('app.accounts.loading') }}</td>
                        </tr>
                        <tr v-else-if="!accounts.length">
                            <td colspan="8" class="text-center py-4 text-body-secondary">{{ t('app.accounts.empty') }}</td>
                        </tr>
                        <template v-else>
                            <tr v-for="account in accounts" :key="account.id">
                                <td class="fw-semibold">
                                    {{ account.name }}
                                    <span v-if="account.id === currentUser.id" class="badge text-bg-secondary ms-1">{{ t('app.accounts.you') }}</span>
                                    <span v-if="account.business_status === 'verified'" class="badge text-bg-success ms-1">{{ t('app.accounts.business_verified_label') }}</span>
                                    <span v-else-if="account.business_status === 'submitted'" class="badge text-bg-warning ms-1">{{ t('app.accounts.business_submitted_label') }}</span>
                                </td>
                                <td>{{ account.email }}</td>
                                <td>{{ account.account_type_label }}</td>
                                <td>{{ account.role_label ?? '—' }}</td>
                                <td>{{ account.account_position?.name_bm ?? '—' }}</td>
                                <td>{{ account.account_type === 'staff' ? (account.can_invite ? t('app.common.yes') : t('app.common.no')) : t('app.common.yes') }}</td>
                                <td>
                                    <span class="badge" :class="account.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                        {{ account.is_active ? t('app.common.active') : t('app.accounts.inactive_label') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a v-if="account.has_business_document" class="btn btn-sm btn-outline-secondary me-1"
                                       :href="`/admin/api/accounts/${account.id}/business-document`" target="_blank" rel="noopener">{{ t('app.accounts.ssm_view') }}</a>
                                    <button v-if="account.can_verify_business" type="button" class="btn btn-sm btn-outline-success me-1"
                                            @click="verifyBusiness(account)">{{ t('app.accounts.verify_business') }}</button>
                                    <button v-if="account.can_manage" type="button" class="btn btn-sm btn-outline-primary"
                                            @click="openAccount(account)">{{ t('app.common.edit') }}</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ t('app.accounts.positions_header') }}</span>
                <button v-if="can.manageAccountPositions" type="button" class="btn btn-sm btn-primary" @click="openPosition()">{{ t('app.accounts.add_position') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('app.accounts.col_level') }}</th>
                            <th>{{ t('app.positions.name_bm') }}</th>
                            <th>{{ t('app.positions.name_en') }}</th>
                            <th>{{ t('app.positions.status') }}</th>
                            <th v-if="can.manageAccountPositions"><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!positions.length">
                            <td :colspan="can.manageAccountPositions ? 5 : 4" class="text-center py-4 text-body-secondary">{{ t('app.accounts.positions_empty') }}</td>
                        </tr>
                        <tr v-for="position in positions" :key="position.id">
                            <td>{{ position.level }}</td>
                            <td class="fw-semibold">{{ position.name_bm }}</td>
                            <td>{{ position.name_en ?? '—' }}</td>
                            <td>
                                <span class="badge" :class="position.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ position.is_active ? t('app.common.active') : t('app.common.inactive') }}
                                </span>
                            </td>
                            <td v-if="can.manageAccountPositions" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="openPosition(position)">{{ t('app.common.edit') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AccountModal
            :open="showAccount"
            :account="accountToEdit"
            :positions="positions"
            :current-user="currentUser"
            @close="showAccount = false"
            @saved="load"
        />
        <AccountPositionModal
            :open="showPosition"
            :position="positionToEdit"
            @close="showPosition = false"
            @saved="load"
        />
    </div>
</template>
