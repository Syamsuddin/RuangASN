<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    data: number[];
    width?: number;
    height?: number;
    color?: string;
    ariaLabel?: string;
}>(), {
    width: 120,
    height: 32,
    color: '#3B82F6',
    ariaLabel: 'Tren ringkas',
});

const max = computed(() => Math.max(1, ...props.data));
const min = computed(() => Math.min(0, ...props.data));

const points = computed(() => {
    const n = Math.max(1, props.data.length - 1);
    const range = Math.max(1, max.value - min.value);
    const pad = 2;
    return props.data.map((v, i) => {
        const x = props.data.length === 1
            ? props.width / 2
            : (i / n) * (props.width - pad * 2) + pad;
        const y = props.height - pad - ((v - min.value) / range) * (props.height - pad * 2);
        return { x, y };
    });
});

const path = computed(() =>
    points.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ')
);
const last = computed(() => points.value[points.value.length - 1]);
</script>

<template>
    <svg
        :viewBox="`0 0 ${width} ${height}`"
        :width="width" :height="height"
        preserveAspectRatio="none"
        role="img"
        :aria-label="ariaLabel"
    >
        <title>{{ ariaLabel }}</title>
        <path :d="path" fill="none" :stroke="color" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" />
        <circle v-if="last" :cx="last.x" :cy="last.y" r="2" :fill="color" />
    </svg>
</template>
