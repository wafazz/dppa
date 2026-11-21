<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AvatarUpload from '../components/forms/AvatarUpload.vue';
import ProfilePersonalModal from '../components/forms/ProfilePersonalModal.vue';
import ProfileBusinessModal from '../components/forms/ProfileBusinessModal.vue';
import PasswordStrengthMeter from '../components/forms/PasswordStrengthMeter.vue';
import TwoFactorCard from '../components/forms/TwoFactorCard.vue';
import { api, ApiError, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { formatDate } from '../lib/format';
import { t } from '../lib/i18n';

const profile = ref(null);
const categories = ref([]);
const loadError = ref('');

const showPersonal = ref(false);
const showBusiness = ref(false);

const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref({});
const savingPassword = ref(false);
const passwordMismatch = computed(() => passwordForm.password_confirmation !== ''
    && passwordForm.password_confirmation !== passwordForm.password);

const businessLocked = computed(() => profile.value?.business_status === 'verified');
const businessStatusClass = computed(() => ({
    verified: 'text-bg-success',
    submitted: 'text-bg-warning',
    draft: 'text-bg-secondary',
}[profile.value?.business_status] ?? 'text-bg-secondary'));
const businessStatusLabel = computed(() => t(`app.profile.business_status_${profile.value?.business_status ?? 'draft'}`));
const addressLines = computed(() => {
    if (!profile.value) {
        return [];
    }

    const cityLine = [profile.value.postcode, profile.value.city].filter(Boolean).join(' ');

    return [profile.value.address_line1, profile.value.address_line2, cityLine, profile.value.state].filter(Boolean);
});

async function load() {
    loadError.value = '';

    try {
        const [profileResponse, categoryResponse] = await Promise.all([
            api('/admin/api/profile'),
            api('/admin/api/categories'),
        ]);

        profile.value = profileResponse.data;
        categories.value = categoryResponse.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

function onProfileSaved(data) {
    profile.value = { ...profile.value, ...data };
}

function onAvatarChanged(data) {
    profile.value = { ...profile.value, avatar_url: data.avatar_url };
}

async function savePassword() {
    const confirmed = await confirmAction({ title: t('app.profile.save_password'), text: t('app.profile.password_title') });

    if (!confirmed) {
        return;
    }

    savingPassword.value = true;
    passwordErrors.value = {};

    try {
        const response = await api('/admin/api/profile/password', { method: 'PUT', body: { ...passwordForm } });
        Object.assign(passwordForm, { current_password: '', password: '', password_confirmation: '' });
        notifySuccess(response.message);
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            passwordErrors.value = error.errors;
        } else {
            notifyError(error.message);
        }
    } finally {
        savingPassword.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div v-if="loadError" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.profile.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
        </div>

        <div v-else-if="!profile" class="card">
            <div class="card-body text-center py-5 text-body-secondary">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                {{ t('app.common.loading') }}
            </div>
        </div>

        <div v-else class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ t('app.profile.personal_title') }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="showPersonal = true">{{ t('app.profile.edit') }}</button>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-5">{{ t('app.profile.name') }}</dt>
                            <dd class="col-7">{{ profile.name }}</dd>
                            <dt class="col-5">{{ t('app.profile.email') }}</dt>
                            <dd class="col-7">{{ profile.email }}</dd>
                            <dt class="col-5">{{ t('app.profile.address_line1') }}</dt>
                            <dd class="col-7">
                                <template v-if="addressLines.length">
                                    <div v-for="line in addressLines" :key="line">{{ line }}</div>
                                </template>
                                <span v-else class="text-body-secondary">{{ t('app.profile.not_provided') }}</span>
                            </dd>
                            <dt class="col-5">{{ t('app.profile.account_type') }}</dt>
                            <dd class="col-7">{{ profile.account_type_label }}</dd>
                            <template v-if="profile.role_label">
                                <dt class="col-5">{{ t('app.profile.role') }}</dt>
                                <dd class="col-7">{{ profile.role_label }}</dd>
                            </template>
                            <template v-if="profile.position_name">
                                <dt class="col-5">{{ t('app.profile.position') }}</dt>
                                <dd class="col-7">{{ profile.position_name }}</dd>
                            </template>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">
                            {{ t('app.profile.business_title') }}
                            <span class="badge ms-1" :class="businessStatusClass">{{ businessStatusLabel }}</span>
                        </span>
                        <button v-if="!businessLocked" type="button" class="btn btn-sm btn-outline-primary" @click="showBusiness = true">
                            {{ t('app.profile.edit') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="small text-body-secondary mb-3">
                            {{ businessLocked ? t('app.profile.business_locked_note') : t('app.profile.business_pending_note') }}
                        </p>
                        <dl class="row mb-0 small">
                            <dt class="col-5">{{ t('app.profile.business_name') }}</dt>
                            <dd class="col-7">{{ profile.business_name || t('app.profile.not_provided') }}</dd>
                            <dt class="col-5">{{ t('app.profile.ssm_no') }}</dt>
                            <dd class="col-7">{{ profile.ssm_no || t('app.profile.not_provided') }}</dd>
                            <dt class="col-5">{{ t('app.profile.business_category') }}</dt>
                            <dd class="col-7">{{ profile.business_category_name || t('app.profile.not_provided') }}</dd>
                            <dt class="col-5">{{ t('app.profile.business_phone') }}</dt>
                            <dd class="col-7">{{ profile.business_phone || t('app.profile.not_provided') }}</dd>
                            <dt class="col-5">{{ t('app.profile.business_ssm_title') }}</dt>
                            <dd class="col-7">
                                <a v-if="profile.has_business_document" href="/admin/api/profile/business/document" target="_blank" rel="noopener">
                                    {{ t('app.profile.business_ssm_view') }}
                                </a>
                                <span v-else class="text-body-secondary">{{ t('app.profile.business_ssm_none') }}</span>
                            </dd>
                            <template v-if="businessLocked">
                                <dt class="col-5">{{ t('app.accounts.business_verified_label') }}</dt>
                                <dd class="col-7">{{ formatDate(profile.business_verified_at) }}</dd>
                            </template>
                            <template v-else-if="profile.business_status === 'submitted'">
                                <dt class="col-5">{{ t('app.profile.business_status_submitted') }}</dt>
                                <dd class="col-7">{{ formatDate(profile.business_submitted_at) }}</dd>
                            </template>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <AvatarUpload :avatar-url="profile.avatar_url" @changed="onAvatarChanged" />
            </div>

            <div class="col-12 col-lg-6">
                <TwoFactorCard />
            </div>

            <div class="col-12 col-lg-6">
                <form class="card h-100" novalidate @submit.prevent="savePassword">
                    <div class="card-header fw-semibold">{{ t('app.profile.password_title') }}</div>
                    <div class="card-body">
                        <p class="small text-body-secondary mb-3">{{ t('app.profile.password_hint') }}</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="profile-current-password">{{ t('app.profile.current_password') }}</label>
                                <input id="profile-current-password" v-model="passwordForm.current_password" type="password" autocomplete="current-password" required
                                       class="form-control" :class="{ 'is-invalid': firstError(passwordErrors, 'current_password') }">
                                <div v-if="firstError(passwordErrors, 'current_password')" class="invalid-feedback">{{ firstError(passwordErrors, 'current_password') }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="profile-new-password">{{ t('app.profile.new_password') }}</label>
                                <input id="profile-new-password" v-model="passwordForm.password" type="password" autocomplete="new-password" required minlength="12"
                                       class="form-control" :class="{ 'is-invalid': firstError(passwordErrors, 'password') }">
                                <div v-if="firstError(passwordErrors, 'password')" class="invalid-feedback">{{ firstError(passwordErrors, 'password') }}</div>
                                <PasswordStrengthMeter :password="passwordForm.password" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="profile-confirm-password">{{ t('app.profile.confirm_password') }}</label>
                                <input id="profile-confirm-password" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" required
                                       minlength="12" class="form-control" :class="{ 'is-invalid': passwordMismatch }">
                                <div v-if="passwordMismatch" class="invalid-feedback">{{ t('app.profile.passwords_mismatch') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" :disabled="savingPassword || !passwordForm.current_password || !passwordForm.password || passwordMismatch">
                            <span v-if="savingPassword" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                            {{ t('app.profile.save_password') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ProfilePersonalModal
            v-if="profile"
            :open="showPersonal"
            :profile="profile"
            @close="showPersonal = false"
            @saved="onProfileSaved"
        />
        <ProfileBusinessModal
            v-if="profile"
            :open="showBusiness"
            :profile="profile"
            :categories="categories"
            @close="showBusiness = false"
            @saved="onProfileSaved"
        />
    </div>
</template>
