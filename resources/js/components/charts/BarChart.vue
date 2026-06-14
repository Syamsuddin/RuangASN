<script setup lang="ts">
import { computed } from 'vue';

interface Bar {
    label: string;
    value: number;
    color?: string;
}

const props = withDefaults(defineProps<{
    data: Bar[];
    height?: number;
    color?: string;
    suffix?: string;
    ariaLabel?: string;
}>(), {
    height: 180,
    color: '#3B82F6',
    suffix: '',
    ariaLabel: 'Diagram batang',
});

// viewBox is fixed; the SVG scales responsively to its container width.
const VB_W = 320;
const gap = 12;

const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)));
const barWidth = computed(() => {
    const n = Math.max(1, props.data.length);
    return (VB_W - gap * (n + 1)) / n;
});

const bars = computed(() =>
    props.data.map((d, i) => {
        const h = (d.value / max.value) * (props.height - 28);
        const x = gap + i * (barWidth.value + gap);
        const y = props.height - 20 - h;
        return {
            ...d,
            x,
            y,
            w: barWidth.value,
            h: Math.max(0, h),
            fill: d.color ?? props.color,
        };
    })
);
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
        <!-- baseline -->
        <line :x1="0" :y1="height - 20" :x2="VB_W" :y2="height - 20" stroke="var(--border-color)" stroke-width="1" />

        <g v-for="bar in bars" :key="bar.label">
            <rect
                :x="bar.x" :y="bar.y" :width="bar.w" :height="bar.h"
                :fill="bar.fill" rx="3"
            >
                <title>{{ bar.label }}: {{ bar.value }}{{ suffix }}</title>
            </rect>
            <!-- value -->
            <text
                :x="bar.x + bar.w / 2" :y="bar.y - 4"
                text-anchor="middle" font-size="9" font-weight="600"
                fill="var(--text-secondary)"
            >{{ bar.value }}{{ suffix }}</text>
            <!-- label -->
            <text
                :x="bar.x + bar.w / 2" :y="height - 7"
                text-anchor="middle" font-size="9"
                fill="var(--text-muted)"
            >{{ bar.label }}</text>
        </g>
    </svg>
</template>
