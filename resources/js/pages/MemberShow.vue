<script setup>
import { computed, onMounted, ref } from 'vue';
import MemberEditModal from '../components/forms/MemberEditModal.vue';
import ReviewModal from '../components/forms/ReviewModal.vue';
import AppointmentModal from '../components/forms/AppointmentModal.vue';
import { api } from '../lib/http';
import { notifyError, notifySuccess } from '../lib/feedback';
import { formatDate, formatDateTime, statusBadgeClass } from '../lib/format';
import { t } from '../lib/i18n';

const props = defineProps({
    memberId: { type: Number, required: true },
    can: { type: Object, default: () => ({}) },
});

const member = ref(null);
const duplicates = ref([]);
const categories = ref([]);
const positions = ref([]);
const loading = ref(true);
const loadError = ref('');

const showEdit = ref(false);
const showReview = ref(false);
const showAppointment = ref(false);
const accountBusy = ref(false);
const verificationBusy = ref(false);
const accountMessage = ref('');
const setupUrl = ref('');
const appointmentToEdit = ref(null);

const canAssign = computed(() => props.can.manageAppointments && member.value?.status === 'approved');

const appointmentPreset = computed(() => (member.value
    ? {
        id: member.value.id,
        applicant_name: member.value.applicant_name,
        business_name: member.value.business_name,
        member_no: member.value.member_no,
    }
    : null));

async function loadMember() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api(`/admin/api/members/${props.memberId}`);
        member.value = response.data;
        duplicates.value = response.possible_duplicates?.data ?? [];
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function loadReferenceData() {
    try {
        const [categoryResponse, positionResponse] = await Promise.all([
            api('/admin/api/categories'),
            api('/admin/api/positions'),
        ]);

        categories.value = categoryResponse.data;
        positions.value = positionResponse.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

async function sendSetupLink() {
    accountBusy.value = true;
    accountMessage.value = '';
    setupUrl.value = '';

    try {
        const response = await api(`/admin/api/members/${props.memberId}/account/setup-link`, { method: 'POST' });

        if (response.data.emailed) {
            accountMessage.value = t('app.members.account.sent_email');
        } else {
            setupUrl.value = response.data.url;
            accountMessage.value = t('app.members.account.sent_manual');
        }

        await loadMember();
    } catch (error) {
        accountMessage.value = error.message;
    } finally {
        accountBusy.value = false;
    }
}

async function resendVerification() {
    verificationBusy.value = true;

    try {
        const response = await api(`/admin/api/members/${props.memberId}/verification/resend`, { method: 'POST' });
        notifySuccess(response.message);
    } catch (error) {
        notifyError(error.message);
    } finally {
        verificationBusy.value = false;
    }
}

function openAppointment(appointment = null) {
    appointmentToEdit.value = appointment;
    showAppointment.value = true;
}

onMounted(() => {
    loadMember();
    loadReferenceData();
});
</script>

<template>
    <div>
        <div v-if="loading && !member" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 mb-0">{{ t('app.members.detail.loading') }}</p>
            </div>
        </div>

        <div v-else-if="loadError && !member" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.members.detail.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="loadMember">{{ t('app.common.retry') }}</button>
        </div>

        <template v-else-if="member">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="/admin/members" class="small">&larr; {{ t('app.members.detail.back') }}</a>
                    <h2 class="h4 mb-0 mt-1">
                        {{ member.applicant_name }}
                        <span class="badge ms-2" :class="statusBadgeClass(member.status)">{{ member.status_label }}</span>
                    </h2>
                    <div class="text-body-secondary">
                        {{ member.business_name }} · {{ t('app.members.detail.member_no') }}: <strong>{{ member.member_no ?? t('app.members.detail.not_issued') }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button v-if="can.manageMembers" type="button" class="btn btn-outline-primary" @click="showEdit = true">{{ t('app.common.edit') }}</button>
                    <button v-if="can.manageMembers" type="button" class="btn btn-primary" @click="showReview = true">{{ t('app.members.detail.review') }}</button>
                    <button v-if="canAssign" type="button" class="btn btn-success" @click="openAppointment()">{{ t('app.members.detail.assign') }}</button>
                </div>
            </div>

            <div v-if="loadError" class="alert alert-warning" role="alert">{{ loadError }}</div>

            <div v-if="duplicates.length" class="alert alert-warning" role="alert">
                <strong>{{ t('app.members.detail.duplicates_title') }}</strong> — {{ t('app.members.detail.duplicates_text') }}
                <ul class="mb-0 mt-2">
                    <li v-for="duplicate in duplicates" :key="duplicate.id">
                        <a :href="`/admin/members/${duplicate.id}`">{{ duplicate.applicant_name }} · {{ duplicate.business_name }}</a>
                        <span class="small text-body-secondary">({{ duplicate.status_label }})</span>
                    </li>
                </ul>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ t('app.members.detail.info_title') }}</div>
                        <div class="card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-5">{{ t('app.members.detail.label.applicant') }}</dt><dd class="col-7">{{ member.applicant_name }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.business') }}</dt><dd class="col-7">{{ member.business_name }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.category') }}</dt><dd class="col-7">{{ member.business_category?.name_bm ?? '—' }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.address') }}</dt><dd class="col-7">{{ member.business_address }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.phone') }}</dt><dd class="col-7">{{ member.phone }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.email') }}</dt>
                                <dd class="col-7">
                                    {{ member.email }}
                                    <span class="badge ms-1" :class="member.email_verified_at ? 'text-bg-success' : 'text-bg-warning'">
                                        {{ member.email_verified_at ? t('app.members.verification.badge_verified') : t('app.members.verification.badge_unverified') }}
                                    </span>
                                    <div v-if="!member.email_verified_at && can.manageMembers" class="mt-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" :disabled="verificationBusy" @click="resendVerification">
                                            {{ t('app.members.verification.resend') }}
                                        </button>
                                    </div>
                                </dd>
                                <dt class="col-5">{{ t('app.members.detail.label.ssm') }}</dt><dd class="col-7">{{ member.ssm_no ?? '—' }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.applied') }}</dt><dd class="col-7">{{ formatDateTime(member.applied_at) }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.approved') }}</dt><dd class="col-7">{{ formatDateTime(member.approved_at) }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.rejected') }}</dt><dd class="col-7">{{ formatDateTime(member.rejected_at) }}</dd>
                                <dt class="col-5">{{ t('app.members.detail.label.consent') }}</dt>
                                <dd class="col-7">{{ formatDateTime(member.consent_given_at) }} ({{ t('app.members.detail.consent_version', { version: member.consent_version }) }})</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                            <span>{{ t('app.members.account.card_title') }}</span>
                            <span v-if="member.account" class="badge" :class="member.account.has_password ? 'text-bg-success' : 'text-bg-warning'">
                                {{ member.account.has_password ? t('app.members.account.active') : t('app.members.account.awaiting') }}
                            </span>
                        </div>
                        <div class="card-body small">
                            <p v-if="!member.account" class="mb-2 text-body-secondary">{{ t('app.members.account.none') }}</p>
                            <p v-else class="mb-2">{{ t('app.members.account.email_label') }}: <strong>{{ member.account.email }}</strong></p>
                            <div v-if="can.manageMembers && member.status === 'approved'" class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" :disabled="accountBusy" @click="sendSetupLink">
                                    {{ member.account?.has_password ? t('app.members.account.resend') : t('app.members.account.create') }}
                                </button>
                            </div>
                            <div v-if="accountMessage" class="alert alert-info small mt-2 mb-0" role="status">
                                {{ accountMessage }}
                                <div v-if="setupUrl" class="input-group input-group-sm mt-2">
                                    <input type="text" class="form-control" :value="setupUrl" readonly @focus="$event.target.select()">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">{{ t('app.members.detail.description_title') }}</div>
                        <div class="card-body rich-text-content" v-html="member.business_description"></div>
                    </div>
                    <div class="card">
                        <div class="card-header fw-semibold">{{ t('app.members.detail.needs_title') }}</div>
                        <div class="card-body rich-text-content" v-html="member.business_needs"></div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header fw-semibold">{{ t('app.members.detail.positions_title') }}</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ t('app.members.detail.term.position') }}</th>
                                        <th>{{ t('app.members.detail.term.start') }}</th>
                                        <th>{{ t('app.members.detail.term.end') }}</th>
                                        <th>{{ t('app.members.detail.term.status') }}</th>
                                        <th>{{ t('app.members.detail.term.notes') }}</th>
                                        <th v-if="can.manageAppointments"><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!member.appointments?.length">
                                        <td :colspan="can.manageAppointments ? 6 : 5" class="text-center py-4 text-body-secondary">
                                            {{ t('app.members.detail.positions_empty') }}
                                        </td>
                                    </tr>
                                    <tr v-for="appointment in member.appointments" :key="appointment.id" :class="{ 'table-success': appointment.is_current }">
                                        <td>{{ appointment.chamber_position?.name_bm }}</td>
                                        <td>{{ formatDate(appointment.term_start) }}</td>
                                        <td>{{ formatDate(appointment.term_end) }}</td>
                                        <td>
                                            <span v-if="appointment.is_current" class="badge text-bg-success">{{ t('app.members.detail.term.current') }}</span>
                                            <span v-else class="badge text-bg-secondary">{{ t('app.members.detail.term.history') }}</span>
                                        </td>
                                        <td class="small rich-text-content" v-html="appointment.notes ?? '—'"></td>
                                        <td v-if="can.manageAppointments" class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary" @click="openAppointment(appointment)">{{ t('app.common.edit') }}</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header fw-semibold">{{ t('app.members.detail.status_history') }}</div>
                        <ul class="list-group list-group-flush">
                            <li v-if="!member.status_logs?.length" class="list-group-item text-center text-body-secondary py-4">
                                {{ t('app.members.detail.status_empty') }}
                            </li>
                            <li v-for="log in member.status_logs" :key="log.id" class="list-group-item">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <span>
                                        <strong>{{ log.from_status ?? t('app.members.detail.new') }}</strong> → <strong>{{ log.to_status_label }}</strong>
                                        <span class="small text-body-secondary">{{ t('app.members.detail.changed_by', { name: log.changed_by ?? t('app.members.detail.system') }) }}</span>
                                    </span>
                                    <span class="small text-body-secondary">{{ formatDateTime(log.created_at) }}</span>
                                </div>
                                <div v-if="log.note" class="small mt-1 rich-text-content" v-html="log.note"></div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <MemberEditModal
                :open="showEdit"
                :member="member"
                :categories="categories"
                @close="showEdit = false"
                @saved="loadMember"
            />
            <ReviewModal
                :open="showReview"
                :member="member"
                @close="showReview = false"
                @saved="loadMember"
            />
            <AppointmentModal
                :open="showAppointment"
                :appointment="appointmentToEdit"
                :preset-member="appointmentPreset"
                :positions="positions"
                @close="showAppointment = false"
                @saved="loadMember"
            />
        </template>
    </div>
</template>
