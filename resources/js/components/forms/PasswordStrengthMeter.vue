<script setup>
import { computed } from 'vue';
import { t } from '../../lib/i18n';

/**
 * Advisory strength meter for a new password. It scores length and character variety; the server
 * still enforces the minimum length and the other password rules.
 */
const props = defineProps({
    password: { type: String, default: '' },
});

const LEVELS = [
    { key: 'weak', width: 25, class: 'bg-danger' },
    { key: 'fair', width: 50, class: 'bg-warning' },
    { key: 'good', width: 75, class: 'bg-info' },
    { key: 'strong', width: 100, class: 'bg-success' },
];

function score(value) {
    let points = 0;

    if (value.length >= 12) {
        points += 1;
    }

    if (value.length >= 16) {
        points += 1;
    }

    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
        points += 1;
    }

    if (/\d/.test(value)) {
        points += 1;
    }

    if (/[^A-Za-z0-9\s]/.test(value)) {
        points += 1;
    }

    return points;
}

const level = computed(() => {
    if (props.password === '') {
        return null;
    }

    const points = score(props.password);

    if (points <= 1) {
        return LEVELS[0];
    }

    if (points <= 2) {
        return LEVELS[1];
    }

    if (points <= 3) {
        return LEVELS[2];
    }

    return LEVELS[3];
});
</script>

<template>
    <div v-if="level" class="mt-2" aria-live="polite">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-body-secondary">{{ t('app.profile.strength_label') }}</span>
            <span class="fw-semibold">{{ t(`app.profile.strength_${level.key}`) }}</span>
        </div>
        <div class="progress" role="progressbar" :aria-valuenow="level.width" aria-valuemin="0" aria-valuemax="100"
             :aria-label="t('app.profile.strength_label')" style="height: 6px">
            <div class="progress-bar" :class="level.class" :style="{ width: `${level.width}%` }"></div>
        </div>
        <div class="form-text">{{ t('app.profile.strength_tips') }}</div>
    </div>
</template>
