<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { apiPath, csrfToken } from '../../lib/http';
import { confirmAction, notifyError, notifySuccess } from '../../lib/feedback';
import { t } from '../../lib/i18n';

/**
 * Display picture for the signed-in account. The chosen image is centre-cropped to a square and
 * redrawn at 256 × 256 pixels on a canvas before upload, so the stored file is always a small PNG.
 */
const props = defineProps({
    avatarUrl: { type: String, default: null },
});

const emit = defineEmits(['changed']);

const SIZE = 256;
const MIN_SIDE = 64;
const MAX_INPUT_BYTES = 10 * 1024 * 1024;

const dragging = ref(false);
const busy = ref(false);
const errorMessage = ref('');
const previewUrl = ref('');
const preparedBlob = ref(null);
const fileInput = ref(null);

let objectUrl = null;

function clearPending() {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    previewUrl.value = '';
    preparedBlob.value = null;
}

function prepare(file) {
    errorMessage.value = '';

    if (!file.type.startsWith('image/')) {
        errorMessage.value = t('app.profile.picture_not_image');
        return;
    }

    if (file.size > MAX_INPUT_BYTES) {
        errorMessage.value = t('app.profile.picture_too_large');
        return;
    }

    clearPending();

    const image = new Image();
    objectUrl = URL.createObjectURL(file);

    image.onload = () => {
        const side = Math.min(image.naturalWidth, image.naturalHeight);

        if (side < MIN_SIDE) {
            errorMessage.value = t('app.profile.picture_too_small');
            clearPending();
            return;
        }

        const offsetX = (image.naturalWidth - side) / 2;
        const offsetY = (image.naturalHeight - side) / 2;

        const canvas = document.createElement('canvas');
        canvas.width = SIZE;
        canvas.height = SIZE;

        const context = canvas.getContext('2d');
        context.imageSmoothingQuality = 'high';
        context.drawImage(image, offsetX, offsetY, side, side, 0, 0, SIZE, SIZE);

        canvas.toBlob((blob) => {
            if (blob === null) {
                errorMessage.value = t('app.profile.picture_not_image');
                clearPending();
                return;
            }

            preparedBlob.value = blob;
            previewUrl.value = canvas.toDataURL('image/png');
        }, 'image/png');
    };

    image.onerror = () => {
        errorMessage.value = t('app.profile.picture_not_image');
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

async function save() {
    if (preparedBlob.value === null) {
        return;
    }

    const confirmed = await confirmAction({ title: t('app.profile.picture_save'), text: t('app.profile.picture_title') });

    if (!confirmed) {
        return;
    }

    busy.value = true;

    try {
        const body = new FormData();
        body.append('avatar', preparedBlob.value, 'avatar.png');

        const response = await fetch(apiPath('/admin/api/profile/avatar'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body,
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const first = Object.values(data.errors ?? {})[0]?.[0];
            errorMessage.value = first ?? data.message ?? t('app.feedback.server_error');
            return;
        }

        clearPending();
        notifySuccess(t('app.profile.picture_saved'));
        emit('changed', data.data);
    } catch {
        notifyError(t('app.feedback.server_error'));
    } finally {
        busy.value = false;
    }
}

async function remove() {
    const confirmed = await confirmAction({
        title: t('app.profile.picture_remove'),
        text: t('app.profile.picture_title'),
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    busy.value = true;

    try {
        const response = await fetch(apiPath('/admin/api/profile/avatar'), {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        const data = await response.json();
        notifySuccess(t('app.profile.picture_removed'));
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
        <div class="card-header fw-semibold">{{ t('app.profile.picture_title') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-5 text-center">
                    <div class="small text-body-secondary mb-2">{{ t('app.profile.picture_current') }}</div>
                    <img v-if="avatarUrl" :src="avatarUrl" alt="" class="avatar-current rounded-circle">
                    <div v-else class="avatar-current rounded-circle d-inline-flex align-items-center justify-content-center small text-body-secondary">
                        {{ t('app.profile.picture_none') }}
                    </div>
                    <button v-if="avatarUrl" type="button" class="btn btn-sm btn-outline-danger d-block w-100 mt-3" :disabled="busy" @click="remove">
                        {{ t('app.profile.picture_remove') }}
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
                            <div class="small text-body-secondary mb-1">{{ t('app.profile.picture_preview') }}</div>
                            <img :src="previewUrl" alt="" class="avatar-current rounded-circle">
                        </div>
                        <div v-else class="small">{{ t('app.profile.picture_drop') }}</div>
                        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="d-none" @change="onPick">
                    </div>
                    <div class="form-text">{{ t('app.profile.picture_hint') }}</div>
                    <div v-if="errorMessage" class="invalid-feedback d-block">{{ errorMessage }}</div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-primary" :disabled="busy || preparedBlob === null" @click="save">
                            <span v-if="busy" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                            {{ t('app.profile.picture_save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.avatar-current {
    width: 140px;
    height: 140px;
    object-fit: cover;
    border: 1px solid var(--bs-border-color);
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
