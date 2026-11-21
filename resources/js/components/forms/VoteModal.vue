<script setup>
import { reactive, ref, watch } from 'vue';
import AppModal from '../AppModal.vue';
import { api, ApiError, firstError } from '../../lib/http';
import { notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

/**
 * Set up or edit a vote: title, times, choices, and the accounts that may vote.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    vote: { type: Object, default: null },
    voters: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    positions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const errors = ref({});
const form = reactive(emptyForm());
const selectedVoters = ref(new Set());
const selectedPositions = ref(new Set());

function emptyForm() {
    return { title: '', description: '', opens_at: '', closes_at: '', options: ['', ''] };
}

function toLocalInput(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        errors.value = {};
        Object.assign(form, emptyForm());
        selectedVoters.value = new Set();
        selectedPositions.value = new Set();

        if (props.vote) {
            Object.assign(form, {
                title: props.vote.title,
                description: props.vote.description ?? '',
                opens_at: toLocalInput(props.vote.opens_at),
                closes_at: toLocalInput(props.vote.closes_at),
                options: props.vote.options.map((option) => option.label),
            });
            selectedVoters.value = new Set(props.vote.voter_ids ?? []);
            selectedPositions.value = new Set(props.vote.account_position_ids ?? []);
        }
    },
);

const fieldError = (name) => firstError(errors.value, name);

function togglePosition(id) {
    const next = new Set(selectedPositions.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedPositions.value = next;
}

function toggleVoter(id) {
    const next = new Set(selectedVoters.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedVoters.value = next;
}

const includesSelf = () => props.currentUserId !== null && selectedVoters.value.has(props.currentUserId);

function toggleSelf() {
    toggleVoter(props.currentUserId);
}

function selectAll() {
    selectedVoters.value = new Set(props.voters.map((voter) => voter.id));
}

function clearVoters() {
    selectedVoters.value = new Set();
}

function addOption() {
    form.options.push('');
}

function removeOption(index) {
    form.options.splice(index, 1);
}

async function submit() {
    saving.value = true;
    errors.value = {};

    const body = {
        title: form.title,
        description: form.description,
        opens_at: form.opens_at || null,
        closes_at: form.closes_at,
        options: form.options.map((label) => label.trim()).filter((label) => label !== ''),
        voter_ids: [...selectedVoters.value],
        account_position_ids: [...selectedPositions.value],
    };

    try {
        const response = props.vote
            ? await api(`/admin/api/votes/${props.vote.id}`, { method: 'PUT', body })
            : await api('/admin/api/votes', { method: 'POST', body });

        notifySuccess(t('app.votes.save'));
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
    <AppModal :open="open" :title="vote ? t('app.votes.edit') : t('app.votes.new')" size="modal-lg" @close="emit('close')">
        <form novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="vote-title">{{ t('app.votes.field_title') }} <span class="text-danger">*</span></label>
                        <input id="vote-title" v-model="form.title" data-autofocus type="text" maxlength="190" required class="form-control"
                               :class="{ 'is-invalid': fieldError('title') }">
                        <div class="invalid-feedback">{{ fieldError('title') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="vote-description">{{ t('app.votes.field_description') }}</label>
                        <textarea id="vote-description" v-model="form.description" rows="2" maxlength="2000" class="form-control"></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="vote-opens">{{ t('app.votes.field_opens') }}</label>
                        <input id="vote-opens" v-model="form.opens_at" type="datetime-local" class="form-control"
                               :class="{ 'is-invalid': fieldError('opens_at') }">
                        <div class="invalid-feedback">{{ fieldError('opens_at') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="vote-closes">{{ t('app.votes.field_closes') }} <span class="text-danger">*</span></label>
                        <input id="vote-closes" v-model="form.closes_at" type="datetime-local" required class="form-control"
                               :class="{ 'is-invalid': fieldError('closes_at') }">
                        <div class="invalid-feedback">{{ fieldError('closes_at') }}</div>
                    </div>

                    <div class="col-12">
                        <div class="fw-semibold mb-1">{{ t('app.votes.field_options') }} <span class="text-danger">*</span></div>
                        <div v-for="(option, index) in form.options" :key="index" class="input-group mb-2">
                            <span class="input-group-text">{{ index + 1 }}</span>
                            <input v-model="form.options[index]" type="text" maxlength="190" class="form-control"
                                   :placeholder="t('app.votes.option_placeholder')" :aria-label="`${t('app.votes.option_placeholder')} ${index + 1}`">
                            <button v-if="form.options.length > 2" type="button" class="btn btn-outline-danger" @click="removeOption(index)">&times;</button>
                        </div>
                        <button v-if="form.options.length < 10" type="button" class="btn btn-sm btn-outline-primary" @click="addOption">
                            {{ t('app.votes.add_option') }}
                        </button>
                        <div v-if="fieldError('options')" class="invalid-feedback d-block">{{ fieldError('options') }}</div>
                    </div>

                    <div class="col-12">
                        <div class="fw-semibold mb-1">{{ t('app.votes.field_positions') }}</div>
                        <div class="small text-body-secondary mb-2">{{ t('app.votes.positions_hint') }}</div>
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <div v-for="position in positions" :key="position.id" class="form-check">
                                <input :id="`position-${position.id}`" class="form-check-input" type="checkbox"
                                       :checked="selectedPositions.has(position.id)" @change="togglePosition(position.id)">
                                <label class="form-check-label" :for="`position-${position.id}`">{{ position.name }}</label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">{{ t('app.votes.field_voters') }}</span>
                            <span class="small">
                                <span class="text-body-secondary me-2">{{ t('app.votes.voters_count', { count: selectedVoters.size }) }}</span>
                                <button type="button" class="btn btn-link btn-sm p-0 me-2" @click="selectAll">{{ t('app.votes.select_all') }}</button>
                                <button type="button" class="btn btn-link btn-sm p-0" @click="clearVoters">{{ t('app.votes.clear_all') }}</button>
                            </span>
                        </div>
                        <div v-if="currentUserId !== null" class="form-check form-switch mb-2">
                            <input id="vote-include-self" class="form-check-input" type="checkbox" role="switch"
                                   :checked="includesSelf()" @change="toggleSelf">
                            <label class="form-check-label" for="vote-include-self">{{ t('app.votes.include_self') }}</label>
                        </div>
                        <div class="border rounded p-2" style="max-height: 240px; overflow-y: auto">
                            <div v-for="voter in voters" :key="voter.id" class="form-check">
                                <input :id="`voter-${voter.id}`" class="form-check-input" type="checkbox"
                                       :checked="selectedVoters.has(voter.id)" @change="toggleVoter(voter.id)">
                                <label class="form-check-label" :for="`voter-${voter.id}`">
                                    {{ voter.name }} <span class="small text-body-secondary">· {{ voter.account_type_label }}</span>
                                </label>
                            </div>
                        </div>
                        <div v-if="fieldError('voter_ids')" class="invalid-feedback d-block">{{ fieldError('voter_ids') }}</div>
                        <div v-if="fieldError('account_position_ids')" class="invalid-feedback d-block">{{ fieldError('account_position_ids') }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="emit('close')">{{ t('app.common.cancel') }}</button>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    {{ t('app.votes.save') }}
                </button>
            </div>
        </form>
    </AppModal>
</template>
