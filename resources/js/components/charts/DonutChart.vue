<script setup lang="ts">
import { computed } from 'vue';

interface Segment {
    label: string;
    value: number;
    color: string;
}

const props = withDefaults(defineProps<{
    data: Segment[];
    size?: number;
    thickness?: number;
    centerLabel?: string;
    centerValue?: string | number;
    ariaLabel?: string;
}>(), {
    size: 160,
    thickness: 22,
    centerLabel: '',
    centerValue: '',
    ariaLabel: 'Diagram donat',
});

const total = computed(() => props.data.reduce((s, d) => s + Math.max(0, d.value), 0));

const radius = computed(() => (props.size - props.thickness) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const center = computed(() => props.size / 2);

// Build stroke-dasharray segments around the ring.
const segments = computed(() => {
    if (total.value <= 0) return [];
    let offset = 0;
    return props.data
        .filter((d) => d.value > 0)
        .map((d) => {
            const frac = d.value / total.value;
            const len = frac * circumference.value;
            const seg = {
                ...d,
                dasharray: `${len} ${circumference.value - len}`,
                dashoffset: -offset,
                pct: Math.round(frac * 100),
            };
            offset += len;
            return seg;
        });
});
</script>

<template>
    <div class="flex items-center gap-4">
        <svg
            :viewBox="`0 0 ${size} ${size}`"
            :width="size" :height="size"
            class="shrink-0"
            role="img"
            :aria-label="ariaLabel"
        >
            <title>{{ ariaLabel }}</title>
            <!-- track -->
            <circle
                :cx="center" :cy="center" :r="radius"
                fill="none" stroke="var(--bg-tertiary)" :stroke-width="thickness"
            />
            <!-- segments (rotate -90deg so it starts at top) -->
            <g :transform="`rotate(-90 ${center} ${center})`">
                <circle
                    v-for="seg in segments" :key="seg.label"
                    :cx="center" :cy="center" :r="radius"
                    fill="none" :stroke="seg.color" :stroke-width="thickness"
                    :stroke-dasharray="seg.dasharray" :stroke-dashoffset="seg.dashoffset"
                    stroke-linecap="butt"
                >
                    <title>{{ seg.label }}: {{ seg.value }} ({{ seg.pct }}%)</title>
                </circle>
            </g>
            <text
                v-if="centerValue !== ''"
                :x="center" :y="center - 2" text-anchor="middle" dominant-baseline="middle"
                font-size="22" font-weight="700" fill="var(--text-primary)"
            >{{ centerValue }}</text>
            <text
                v-if="centerLabel"
                :x="center" :y="center + 16" text-anchor="middle"
                font-size="9" fill="var(--text-muted)"
            >{{ centerLabel }}</text>
        </svg>

        <!-- legend -->
        <ul class="space-y-1.5 text-xs min-w-0">
            <li v-for="d in data" :key="d.label" class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="`background:${d.color};`" />
                <span class="truncate" style="color: var(--text-secondary);">{{ d.label }}</span>
                <span class="ml-auto font-semibold" style="color: var(--text-primary);">{{ d.value }}</span>
            </li>
        </ul>
    </div>
</template>
