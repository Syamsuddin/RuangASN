<script setup lang="ts">
import { computed } from 'vue';

interface Point {
    label: string;
    value: number;
}

const props = withDefaults(defineProps<{
    data: Point[];
    height?: number;
    color?: string;
    suffix?: string;
    ariaLabel?: string;
    max?: number;
}>(), {
    height: 180,
    color: '#3B82F6',
    suffix: '',
    ariaLabel: 'Diagram garis tren',
    max: undefined,
});

const VB_W = 320;
const padX = 8;
const padTop = 12;
const padBottom = 18;

const maxVal = computed(() => props.max ?? Math.max(1, ...props.data.map((d) => d.value)));

const coords = computed(() => {
    const n = Math.max(1, props.data.length - 1);
    const usableW = VB_W - padX * 2;
    const usableH = props.height - padTop - padBottom;
    return props.data.map((d, i) => {
        const x = padX + (props.data.length === 1 ? usableW / 2 : (i / n) * usableW);
        const y = padTop + usableH - (d.value / maxVal.value) * usableH;
        return { ...d, x, y };
    });
});

const linePath = computed(() =>
    coords.value.map((c, i) => `${i === 0 ? 'M' : 'L'} ${c.x.toFixed(1)} ${c.y.toFixed(1)}`).join(' ')
);

const areaPath = computed(() => {
    if (coords.value.length === 0) return '';
    const base = props.height - padBottom;
    const first = coords.value[0];
    const last = coords.value[coords.value.length - 1];
    return `M ${first.x.toFixed(1)} ${base} `
        + coords.value.map((c) => `L ${c.x.toFixed(1)} ${c.y.toFixed(1)}`).join(' ')
        + ` L ${last.x.toFixed(1)} ${base} Z`;
});

const gradId = `lc-grad-${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <svg
        :viewBox="`0 0 ${VB_W} ${height}`"
        preserveAspectRatio="xMidYMid meet"
        class="w-full h-auto"
        role="img"
        :aria-label="ariaLabel"
    >
        <title>{{ ariaLabel }}</title>
        <defs>
            <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="color" stop-opacity="0.25" />
                <stop offset="100%" :stop-color="color" stop-opacity="0" />
            </linearGradient>
        </defs>

        <line :x1="0" :y1="height - padBottom" :x2="VB_W" :y2="height - padBottom" stroke="var(--border-color)" stroke-width="1" />

        <path v-if="data.length > 1" :d="areaPath" :fill="`url(#${gradId})`" />
        <path :d="linePath" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

        <g v-for="(c, i) in coords" :key="i">
            <circle :cx="c.x" :cy="c.y" r="2.5" :fill="color">
                <title>{{ c.label }}: {{ c.value }}{{ suffix }}</title>
            </circle>
        </g>
    </svg>
</template>
