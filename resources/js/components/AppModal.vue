<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as bootstrap from 'bootstrap';
import { t } from '../lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    size: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const closeLabel = t('app.common.close');

const element = ref(null);
const titleId = computed(() => `modal-title-${Math.random().toString(36).slice(2, 9)}`);
let instance = null;

function handleHidden() {
    if (props.open) {
        emit('close');
    }
}

function focusFirstField() {
    element.value?.querySelector('[data-autofocus]')?.focus();
}

function handleShown() {
    // Defer so the focus lands after Bootstrap has finished moving focus into the dialog.
    setTimeout(focusFirstField, 0);
}

onMounted(() => {
    instance = new bootstrap.Modal(element.value, {
        backdrop: 'static',
        keyboard: true,
        // Focus is managed manually so SweetAlert2 confirmations can take focus while the modal is open.
        focus: false,
    });

    element.value.addEventListener('hidden.bs.modal', handleHidden);
    element.value.addEventListener('shown.bs.modal', handleShown);

    if (props.open) {
        instance.show();
    }
});

watch(
    () => props.open,
    (isOpen) => {
        if (!instance) {
            return;
        }

        if (isOpen) {
            instance.show();
        } else {
            instance.hide();
        }
    },
);

onBeforeUnmount(() => {
    element.value?.removeEventListener('hidden.bs.modal', handleHidden);
    element.value?.removeEventListener('shown.bs.modal', handleShown);
    instance?.dispose();
    instance = null;
});

function close() {
    instance?.hide();
}
</script>

<template>
    <Teleport to="body">
        <div ref="element" class="modal fade" tabindex="-1" :aria-labelledby="titleId" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down" :class="size">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" :id="titleId">{{ title }}</h2>
                        <button type="button" class="btn-close" :aria-label="closeLabel" @click="close"></button>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
