<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { createEditor } from '../lib/editor';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const container = ref(null);
let editor = null;

onMounted(() => {
    editor = createEditor(
        container.value,
        props.modelValue,
        (html) => emit('update:modelValue', html),
        props.placeholder,
    );
});

onBeforeUnmount(() => {
    editor?.destroy();
    editor = null;
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor && value !== editor.currentHtml()) {
            editor.setHtml(value);
        }
    },
);
</script>

<template>
    <div :id="id" class="app-rich-text" :class="{ 'is-invalid': invalid }">
        <div ref="container"></div>
    </div>
</template>
