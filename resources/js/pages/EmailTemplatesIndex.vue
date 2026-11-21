<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api, ApiError, firstError } from '../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../lib/feedback';
import { t } from '../lib/i18n';

const LOCALES = [
    { id: 'en', text: 'English' },
    { id: 'ms', text: 'Bahasa Melayu' },
];

const GROUP_ORDER = ['accounts', 'membership', 'security', 'appointments', 'votes', 'digest'];

const types = ref([]);
const selected = ref('');
const locale = ref('en');
const loading = ref(false);
const saving = ref(false);
const loadError = ref('');
const errors = ref({});
const previewSubject = ref('');
const previewHtml = ref('');

const editor = reactive({
    label: '',
    subject: '',
    body: '',
    button: '',
    has_button: false,
    placeholders: [],
    customized: false,
});

const groups = computed(() => GROUP_ORDER.map((group) => ({
    key: group,
    label: t(`app.email_templates.group.${group}`),
    items: types.value.filter((type) => type.group === group),
})));

const fieldError = (name) => firstError(errors.value, name);

function fill(data) {
    Object.assign(editor, {
        label: data.label,
        subject: data.subject,
        body: data.body,
        button: data.button ?? '',
        has_button: data.has_button,
        placeholders: data.placeholders,
        customized: data.customized,
    });
}

function payload() {
    return {
        subject: editor.subject,
        body: editor.body,
        button: editor.has_button ? editor.button : null,
    };
}

async function loadTypes() {
    try {
        const response = await api('/admin/api/email-templates');
        types.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    }
}

async function loadTemplate() {
    if (!selected.value) {
        return;
    }

    loading.value = true;
    loadError.value = '';
    errors.value = {};
    previewHtml.value = '';

    try {
        const response = await api(`/admin/api/email-templates/${selected.value}/${locale.value}`);
        fill(response.data);
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

function handleError(error) {
    if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
        errors.value = error.errors;
    } else {
        notifyError(error.message);
    }
}

async function save() {
    const confirmed = await confirmAction({
        title: t('app.email_templates.confirm_save_title'),
        text: t('app.email_templates.confirm_save_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const response = await api(`/admin/api/email-templates/${selected.value}/${locale.value}`, { method: 'PUT', body: payload() });
        fill(response.data);
        notifySuccess(t('app.email_templates.saved'));
        await loadTypes();
    } catch (error) {
        handleError(error);
    } finally {
        saving.value = false;
    }
}

async function resetToDefault() {
    const confirmed = await confirmAction({
        title: t('app.email_templates.confirm_reset_title'),
        text: t('app.email_templates.confirm_reset_text'),
    });

    if (!confirmed) {
        return;
    }

    saving.value = true;

    try {
        const response = await api(`/admin/api/email-templates/${selected.value}/${locale.value}`, { method: 'DELETE' });
        fill(response.data);
        previewHtml.value = '';
        notifySuccess(t('app.email_templates.reset_done'));
        await loadTypes();
    } catch (error) {
        notifyError(error.message);
    } finally {
        saving.value = false;
    }
}

async function preview() {
    errors.value = {};

    try {
        const response = await api(`/admin/api/email-templates/${selected.value}/${locale.value}/preview`, { method: 'POST', body: payload() });
        previewSubject.value = response.data.subject;
        previewHtml.value = response.data.html;
    } catch (error) {
        handleError(error);
    }
}

function select(key) {
    selected.value = key;
}

watch([selected, locale], loadTemplate);

onMounted(async () => {
    await loadTypes();
    selected.value = types.value[0]?.key ?? '';
});
</script>

<template>
    <div>
        <p class="text-body-secondary mb-3">{{ t('app.email_templates.intro') }}</p>

        <div v-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>

        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body p-0">
                        <template v-for="group in groups" :key="group.key">
                            <div class="px-3 pt-3 pb-1 small fw-semibold text-uppercase text-body-secondary">{{ group.label }}</div>
                            <div class="list-group list-group-flush">
                                <button v-for="type in group.items" :key="type.key" type="button"
                                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                        :class="{ active: selected === type.key }"
                                        :aria-current="selected === type.key ? 'true' : undefined"
                                        @click="select(type.key)">
                                    <span>{{ type.label }}</span>
                                    <span v-if="type.customized[locale]" class="badge text-bg-warning">{{ t('app.email_templates.changed') }}</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <div v-if="!selected" class="card">
                    <div class="card-body text-body-secondary">{{ t('app.email_templates.select') }}</div>
                </div>

                <div v-else class="card">
                    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <span class="fw-semibold">{{ editor.label }}</span>
                        <div class="btn-group btn-group-sm" role="group" :aria-label="t('app.email_templates.language')">
                            <button v-for="item in LOCALES" :key="item.id" type="button"
                                    class="btn" :class="locale === item.id ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="locale = item.id">{{ item.text }}</button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div v-if="loading" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </div>

                        <form v-else novalidate @submit.prevent="save">
                            <div class="mb-3">
                                <label class="form-label" for="email-subject">{{ t('app.email_templates.subject') }}</label>
                                <input id="email-subject" v-model="editor.subject" type="text" maxlength="150"
                                       class="form-control" :class="{ 'is-invalid': fieldError('subject') }">
                                <div class="invalid-feedback">{{ fieldError('subject') }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="email-body">{{ t('app.email_templates.body') }}</label>
                                <textarea id="email-body" v-model="editor.body" rows="10" maxlength="5000"
                                          class="form-control" :class="{ 'is-invalid': fieldError('body') }"></textarea>
                                <div class="invalid-feedback">{{ fieldError('body') }}</div>
                            </div>

                            <div v-if="editor.has_button" class="mb-3">
                                <label class="form-label" for="email-button">{{ t('app.email_templates.button') }}</label>
                                <input id="email-button" v-model="editor.button" type="text" maxlength="80"
                                       class="form-control" :class="{ 'is-invalid': fieldError('button') }">
                                <div class="invalid-feedback">{{ fieldError('button') }}</div>
                            </div>

                            <div class="mb-3 small">
                                <span class="fw-semibold me-1">{{ t('app.email_templates.placeholders') }}:</span>
                                <code v-for="placeholder in editor.placeholders" :key="placeholder" class="me-1">{{ placeholder }}</code>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary" :disabled="saving">{{ t('app.email_templates.save') }}</button>
                                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="preview">{{ t('app.email_templates.show_preview') }}</button>
                                <button v-if="editor.customized" type="button" class="btn btn-outline-danger" :disabled="saving" @click="resetToDefault">{{ t('app.email_templates.reset') }}</button>
                            </div>
                        </form>

                        <div v-if="previewHtml" class="mt-4">
                            <div class="small fw-semibold mb-2">{{ t('app.email_templates.preview') }}: {{ previewSubject }}</div>
                            <iframe :srcdoc="previewHtml" sandbox="" title="Preview" class="w-100 border rounded" style="height: 520px"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
