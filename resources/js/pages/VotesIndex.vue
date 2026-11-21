<script setup>
import { computed, onMounted, ref } from 'vue';
import AppModal from '../components/AppModal.vue';
import VoteModal from '../components/forms/VoteModal.vue';
import { api, ApiError, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { formatDateTime } from '../lib/format';
import { t } from '../lib/i18n';

const props = defineProps({
    canCreateVotes: { type: Boolean, default: false },
    isManagement: { type: Boolean, default: false },
    currentUser: { type: Object, default: null },
});

const votes = ref([]);
const voters = ref([]);
const positions = ref([]);
const creators = ref([]);
const loadError = ref('');
const loading = ref(false);

const showForm = ref(false);
const voteToEdit = ref(null);

const ballotVote = ref(null);
const ballotChoice = ref(null);
const ballotErrors = ref({});
const casting = ref(false);

const creatorErrors = ref('');

const statusClass = {
    scheduled: 'text-bg-secondary',
    open: 'text-bg-success',
    closed: 'text-bg-dark',
    cancelled: 'text-bg-danger',
};

// The sidebar submenu opens this page with ?status=open or ?status=closed.
const statusFilter = ref('');
const filteredVotes = computed(() => (statusFilter.value
    ? votes.value.filter((vote) => vote.status === statusFilter.value)
    : votes.value));
const hasVotes = computed(() => filteredVotes.value.length > 0);

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api('/admin/api/votes');
        votes.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function loadVoters() {
    try {
        voters.value = (await api('/admin/api/votes/voters')).data;
    } catch (error) {
        notifyError(error.message);
    }
}

async function loadPositions() {
    try {
        positions.value = (await api('/admin/api/votes/positions')).data;
    } catch (error) {
        notifyError(error.message);
    }
}

async function loadCreators() {
    if (!props.isManagement) {
        return;
    }

    try {
        creators.value = (await api('/admin/api/votes/creators')).data;
    } catch (error) {
        creatorErrors.value = error.message;
    }
}

function openNew() {
    voteToEdit.value = null;
    showForm.value = true;
}

function openEdit(vote) {
    voteToEdit.value = vote;
    showForm.value = true;
}

function openBallot(vote) {
    ballotVote.value = vote;
    ballotChoice.value = null;
    ballotErrors.value = {};
}

async function castVote() {
    if (ballotChoice.value === null) {
        return;
    }

    const confirmed = await confirmAction({ title: t('app.votes.submit'), text: t('app.votes.choose') });

    if (!confirmed) {
        return;
    }

    casting.value = true;
    ballotErrors.value = {};

    try {
        await api(`/admin/api/votes/${ballotVote.value.id}/ballot`, { method: 'POST', body: { option_id: ballotChoice.value } });
        notifySuccess(t('app.votes.voted'));
        ballotVote.value = null;
        await load();
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            ballotErrors.value = error.errors;
        } else {
            notifyError(error.message);
        }
    } finally {
        casting.value = false;
    }
}

async function runAction(url, confirmText, successText) {
    const confirmed = await confirmAction({ title: confirmText, text: t('app.votes.title') });

    if (!confirmed) {
        return;
    }

    try {
        await api(url, { method: 'POST' });
        notifySuccess(successText);
        await load();
    } catch (error) {
        notifyError(error.message);
    }
}

function closeVote(vote) {
    return runAction(`/admin/api/votes/${vote.id}/close`, t('app.votes.close'), t('app.votes.close'));
}

function cancelVote(vote) {
    return runAction(`/admin/api/votes/${vote.id}/cancel`, t('app.votes.cancel_vote'), t('app.votes.cancel_vote'));
}

async function setCreator(account, allowed) {
    creatorErrors.value = '';

    try {
        await api(`/admin/api/votes/creators/${account.id}`, { method: 'PUT', body: { can_create_votes: allowed } });
        account.can_create_votes = allowed;
    } catch (error) {
        creatorErrors.value = firstError(error.errors ?? {}, 'can_create_votes') ?? error.message;
    }
}

function percent(votesCount, total) {
    return total > 0 ? Math.round((votesCount / total) * 100) : 0;
}

onMounted(() => {
    const requested = new URLSearchParams(window.location.search).get('status');

    if (['open', 'closed'].includes(requested)) {
        statusFilter.value = requested;
    }

    load();
    if (props.canCreateVotes) {
        loadVoters();
        loadPositions();
    }
    loadCreators();
});
</script>

<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="text-body-secondary small mb-0">{{ t('app.votes.intro') }}</p>
            <button v-if="canCreateVotes" type="button" class="btn btn-primary" @click="openNew">{{ t('app.votes.new') }}</button>
        </div>

        <div v-if="loadError" class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
            <span>{{ t('app.votes.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
        </div>

        <div v-else-if="loading && !hasVotes" class="text-body-secondary small">{{ t('app.common.loading') }}</div>

        <div v-else-if="!hasVotes" class="card">
            <div class="card-body text-center py-5 text-body-secondary">{{ t('app.votes.empty') }}</div>
        </div>

        <div v-else class="row g-3">
            <div v-for="vote in filteredVotes" :key="vote.id" class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ vote.title }}</div>
                            <div class="small text-body-secondary">{{ t('app.votes.created_by', { name: vote.creator_name ?? '—' }) }}</div>
                        </div>
                        <span class="badge" :class="statusClass[vote.status]">{{ vote.status_label }}</span>
                    </div>

                    <div class="card-body">
                        <p v-if="vote.description" class="small mb-2" style="white-space: pre-line">{{ vote.description }}</p>
                        <div v-if="vote.account_positions?.length" class="small mb-2">
                            <span class="text-body-secondary">{{ t('app.votes.field_positions') }}:</span>
                            <span v-for="position in vote.account_positions" :key="position.id" class="badge text-bg-light border me-1">{{ position.name }}</span>
                        </div>
                        <div class="small text-body-secondary mb-2">
                            <span v-if="vote.status === 'closed'">{{ formatDateTime(vote.closed_at ?? vote.closes_at) }}</span>
                            <span v-else>{{ t('app.votes.closes_in', { time: formatDateTime(vote.closes_at) }) }}</span>
                        </div>

                        <template v-if="vote.results">
                        <div v-if="vote.results.closed" class="mb-2">
                            <div class="fw-semibold small mb-1">{{ t('app.votes.results') }}</div>
                            <div v-for="option in vote.results.options" :key="option.id" class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span>{{ option.label }}</span>
                                    <span>{{ option.votes }} ({{ percent(option.votes, vote.results.total) }}%)</span>
                                </div>
                                <div class="progress" style="height: 6px">
                                    <div class="progress-bar" :style="{ width: percent(option.votes, vote.results.total) + '%' }"></div>
                                </div>
                            </div>
                            <div class="small text-body-secondary">{{ t('app.votes.total') }}: {{ vote.results.total }}</div>
                        </div>
                        <div v-else-if="vote.status !== 'cancelled'" class="small text-body-secondary mb-2">
                            {{ t('app.votes.results_hidden') }}
                        </div>
                        <div class="small text-body-secondary">
                            {{ t('app.votes.turnout', { voted: vote.results.turnout, eligible: vote.results.eligible }) }}
                        </div>
                        </template>
                        <div v-else class="small text-body-secondary mb-2">{{ t('app.votes.progress_private') }}</div>

                        <div v-if="vote.is_eligible" class="small mb-2">
                            <span v-if="vote.has_voted" class="text-success">{{ t('app.votes.voted') }}</span>
                        </div>
                    </div>

                    <div v-if="vote.can_vote || vote.can_edit || vote.can_manage" class="card-footer d-flex flex-wrap gap-2">
                        <button v-if="vote.can_vote" type="button" class="btn btn-sm btn-primary" @click="openBallot(vote)">{{ t('app.votes.vote_now') }}</button>
                        <button v-if="vote.can_edit" type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(vote)">{{ t('app.votes.edit') }}</button>
                        <button v-if="vote.can_manage && vote.status === 'open'" type="button" class="btn btn-sm btn-outline-dark" @click="closeVote(vote)">{{ t('app.votes.close') }}</button>
                        <button v-if="vote.can_manage" type="button" class="btn btn-sm btn-outline-danger" @click="cancelVote(vote)">{{ t('app.votes.cancel_vote') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isManagement" class="card mt-4">
            <div class="card-header fw-semibold">{{ t('app.votes.creators_title') }}</div>
            <div class="card-body">
                <p class="small text-body-secondary">{{ t('app.votes.creators_help') }}</p>
                <div v-if="creatorErrors" class="alert alert-danger small py-2">{{ creatorErrors }}</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('app.votes.column_name') }}</th>
                                <th>{{ t('app.votes.column_type') }}</th>
                                <th>{{ t('app.votes.column_allowed') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="account in creators" :key="account.id">
                                <td>{{ account.name }}</td>
                                <td class="small">{{ account.account_type_label }}</td>
                                <td>
                                    <span v-if="account.always_allowed" class="badge text-bg-secondary">{{ t('app.votes.creators_always') }}</span>
                                    <div v-else class="form-check form-switch mb-0">
                                        <input :id="`creator-${account.id}`" class="form-check-input" type="checkbox" role="switch"
                                               :checked="account.can_create_votes" @change="setCreator(account, $event.target.checked)">
                                        <label class="form-check-label small" :for="`creator-${account.id}`">{{ account.can_create_votes ? '✓' : '' }}</label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <VoteModal
            :open="showForm"
            :vote="voteToEdit"
            :voters="voters"
            :positions="positions"
            :current-user-id="currentUser?.id ?? null"
            @close="showForm = false"
            @saved="load"
        />

        <AppModal :open="ballotVote !== null" :title="ballotVote?.title ?? ''" size="modal-lg" @close="ballotVote = null">
            <form novalidate @submit.prevent="castVote">
                <div class="modal-body">
                    <p class="small text-body-secondary">{{ t('app.votes.choose') }}</p>
                    <div v-for="option in ballotVote?.options ?? []" :key="option.id" class="form-check mb-2">
                        <input :id="`choice-${option.id}`" v-model="ballotChoice" class="form-check-input" type="radio" name="choice" :value="option.id">
                        <label class="form-check-label" :for="`choice-${option.id}`">{{ option.label }}</label>
                    </div>
                    <div v-if="firstError(ballotErrors, 'option_id')" class="invalid-feedback d-block">{{ firstError(ballotErrors, 'option_id') }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" :disabled="casting" @click="ballotVote = null">{{ t('app.common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="casting || ballotChoice === null">{{ t('app.votes.submit') }}</button>
                </div>
            </form>
        </AppModal>
    </div>
</template>
