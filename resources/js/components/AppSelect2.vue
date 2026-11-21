<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { locale } from '../lib/i18n';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    ajaxUrl: { type: String, default: null },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const select = ref(null);

function $select() {
    return window.$(select.value);
}

function initialise() {
    const $element = $select();
    const modal = $element.closest('.modal');

    const config = {
        width: '100%',
        placeholder: props.placeholder,
        language: locale === 'ms' ? 'ms' : 'en',
        theme: 'bootstrap-5',
        dropdownParent: modal.length ? modal : window.$(document.body),
    };

    if (props.ajaxUrl) {
        config.ajax = {
            url: props.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: (params) => ({ q: params.term ?? '' }),
            processResults: (response) => ({ results: response.data ?? [] }),
        };
    }

    $element.select2(config);
    $element.val(props.modelValue ?? '').trigger('change.select2');
    $element.on('change.appSelect2', () => {
        emit('update:modelValue', $element.val() ?? '');
    });
}

function destroy() {
    const $element = $select();
    $element.off('change.appSelect2');

    if ($element.hasClass('select2-hidden-accessible')) {
        $element.select2('destroy');
    }
}

onMounted(initialise);

onBeforeUnmount(destroy);

watch(
    () => props.modelValue,
    (value) => {
        const $element = $select();
        const current = $element.val() ?? '';

        if (String(value ?? '') !== String(current)) {
            $element.val(value ?? '').trigger('change.select2');
        }
    },
);

// Rebuild Select2 when the option list changes so the dropdown shows the new items.
watch(
    () => props.options,
    () => {
        nextTick(() => {
            destroy();
            initialise();
        });
    },
);
</script>

<template>
    <select
        :id="id"
        ref="select"
        class="form-select"
        :class="{ 'is-invalid': invalid }"
        :disabled="disabled"
    >
        <option value="">{{ placeholder }}</option>
        <option v-for="option in options" :key="option.id" :value="option.id">
            {{ option.text }}
        </option>
    </select>
</template>
