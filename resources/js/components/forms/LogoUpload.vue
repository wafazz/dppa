<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { csrfToken } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

/**
 * Superadmin-only logo upload. The image is redrawn on a canvas (at most 512 × 512 pixels) in the
 * browser before it is sent, so large photos are reduced and the upload is always a PNG.
 */
const props = defineProps({
    logoUrl: { type: String, default: null },
});

const emit = defineEmits(['changed']);

const MAX_SIDE = 512;
const MIN_SIDE = 32;
const MAX_INPUT_BYTES = 10 * 1024 * 1024;

const dragging = ref(false);
const busy = ref(false);
const errorMessage = ref('');
const previewUrl = ref('');
const preparedBlob = ref(null);
const fileInput = ref(null);

let objectUrl = null;

function setError(message) {
    errorMessage.value = message;
}

function clearPending() {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    previewUrl.value = '';
    preparedBlob.value = null;
}

/**
 * Redraw the chosen image on a canvas, scaled down to fit within MAX_SIDE.
 */
function prepare(file) {
    errorMessage.value = '';

    if (!file.type.startsWith('image/')) {
        setError(t('app.settings.logo_not_image'));
        return;
    }

    if (file.size > MAX_INPUT_BYTES) {
        setError(t('app.settings.logo_hint'));
        return;
    }

    clearPending();

    const image = new Image();
    objectUrl = URL.createObjectURL(file);

    image.onload = () => {
        const width = image.naturalWidth;
        const height = image.naturalHeight;

        if (Math.min(width, height) < MIN_SIDE) {
            setError(t('app.settings.logo_too_small'));
            clearPending();
            return;
        }

        const scale = Math.min(1, MAX_SIDE / Math.max(width, height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);

        const context = canvas.getContext('2d');
        context.imageSmoothingQuality = 'high';
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        canvas.toBlob((blob) => {
            if (blob === null) {
                setError(t('app.settings.logo_not_image'));
                clearPending();
                return;
            }

            preparedBlob.value = blob;
            previewUrl.value = canvas.toDataURL('image/png');
        }, 'image/png');
    };

    image.onerror = () => {
        setError(t('app.settings.logo_not_image'));
        clearPending();
    };

    image.src = objectUrl;
}

function onDrop(event) {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file) {
        prepare(file);
    }
}

function onPick(event) {
    const file = event.target.files?.[0];

    if (file) {
        prepare(file);
    }

    event.target.value = '';
}

async function upload() {
    if (preparedBlob.value === null) {
        return;
    }

    const confirmed = await confirmAction({
        title: t('app.settings.logo_save'),
        text: t('app.settings.logo_title'),
    });

    if (!confirmed) {
        return;
    }

    busy.value = true;

    try {
        const body = new FormData();
        body.append('logo', preparedBlob.value, 'logo.png');

        const response = await fetch('/admin/api/branding/logo', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body,
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const first = Object.values(data.errors ?? {})[0]?.[0];
            setError(first ?? data.message ?? t('app.feedback.server_error'));
            return;
        }

        clearPending();
        notifySuccess(t('app.settings.logo_saved'));
        emit('changed', data.data);
    } catch {
        notifyError(t('app.feedback.server_error'));
    } finally {
        busy.value = false;
    }
}

async function remove() {
    const confirmed = await confirmAction({
        title: t('app.settings.logo_remove'),
        text: t('app.settings.logo_title'),
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    busy.value = true;

    try {
        const response = await fetch('/admin/api/branding/logo', {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        const data = await response.json();
        notifySuccess(t('app.settings.logo_removed'));
        emit('changed', data.data);
    } catch {
        notifyError(t('app.feedback.server_error'));
    } finally {
        busy.value = false;
    }
}

onBeforeUnmount(clearPending);
</script>

<template>
    <div class="card h-100">
        <div class="card-header fw-semibold">{{ t('app.settings.logo_title') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-5">
                    <div class="small text-body-secondary mb-2">{{ t('app.settings.logo_current') }}</div>
                    <div class="logo-box d-flex align-items-center justify-content-center">
                        <img v-if="logoUrl" :src="logoUrl" alt="" class="img-fluid">
                        <span v-else class="small text-body-secondary text-center px-2">{{ t('app.settings.logo_none') }}</span>
                    </div>
                    <button v-if="logoUrl" type="button" class="btn btn-sm btn-outline-danger mt-2 w-100" :disabled="busy" @click="remove">
                        {{ t('app.settings.logo_remove') }}
                    </button>
                </div>

                <div class="col-12 col-md-7">
                    <div
                        class="drop-zone d-flex flex-column align-items-center justify-content-center text-center p-3"
                        :class="{ 'drop-zone-active': dragging }"
                        role="button"
                        tabindex="0"
                        @click="fileInput?.click()"
                        @keydown.enter.prevent="fileInput?.click()"
                        @dragenter.prevent="dragging = true"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <div v-if="previewUrl" class="mb-2">
                            <div class="small text-body-secondary mb-1">{{ t('app.settings.logo_preview') }}</div>
                            <img :src="previewUrl" alt="" class="img-fluid" style="max-height: 120px">
                        </div>
                        <div v-else class="small">{{ t('app.settings.logo_drop') }}</div>
                        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="d-none" @change="onPick">
                    </div>
                    <div class="form-text">{{ t('app.settings.logo_hint') }}</div>
                    <div v-if="errorMessage" class="invalid-feedback d-block">{{ errorMessage }}</div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-primary" :disabled="busy || preparedBlob === null" @click="upload">
                            <span v-if="busy" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                            {{ t('app.settings.logo_save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.logo-box {
    min-height: 140px;
    border: 1px dashed var(--bs-border-color);
    border-radius: 0.5rem;
    padding: 0.5rem;
}
.drop-zone {
    min-height: 180px;
    border: 2px dashed var(--bs-border-color);
    border-radius: 0.75rem;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.drop-zone:hover,
.drop-zone:focus-visible,
.drop-zone-active {
    border-color: var(--bs-primary);
    background: var(--bs-primary-bg-subtle);
    outline: none;
}
</style>
