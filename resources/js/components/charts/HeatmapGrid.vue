<script setup lang="ts">
import { computed } from 'vue';

interface Column {
    key: string;
    label: string;
    /** higher value = better (green). Set invert for "lower is better" metrics. */
    invert?: boolean;
    suffix?: string;
}

interface Row {
    label: string;
    [key: string]: string | number;
}

const props = withDefaults(defineProps<{
    rows: Row[];
    columns: Column[];
    ariaLabel?: string;
}>(), {
    ariaLabel: 'Heatmap perbandingan OPD',
});

// Per-column min/max for normalization → red→green color scale.
const ranges = computed(() => {
    const r: Record<string, { min: number; max: number }> = {};
    for (const col of props.columns) {
        const vals = props.rows.map((row) => Number(row[col.key] ?? 0));
        r[col.key] = { min: Math.min(...vals, 0), max: Math.max(...vals, 1) };
    }
    return r;
});

// Map a normalized 0..1 (1 = good) to a red→amber→green rgba background.
const cellColor = (col: Column, raw: number): string => {
    const { min, max } = ranges.value[col.key];
    const range = Math.max(1e-6, max - min);
    let t = (raw - min) / range; // 0..1
    if (col.invert) t = 1 - t;
    t = Math.max(0, Math.min(1, t));
    // red (239,68,68) → amber (245,158,11) → green (16,185,129)
    let rC: number, gC: number, bC: number;
    if (t < 0.5) {
        const k = t / 0.5;
        rC = 239 + (245 - 239) * k;
        gC = 68 + (158 - 68) * k;
        bC = 68 + (11 - 68) * k;
    } else {
        const k = (t - 0.5) / 0.5;
        rC = 245 + (16 - 245) * k;
        gC = 158 + (185 - 158) * k;
        bC = 11 + (129 - 11) * k;
    }
    return `rgba(${Math.round(rC)},${Math.round(gC)},${Math.round(bC)},0.22)`;
};

const cellTextColor = (col: Column, raw: number): string => {
    const { min, max } = ranges.value[col.key];
    const range = Math.max(1e-6, max - min);
    let t = (raw - min) / range;
    if (col.invert) t = 1 - t;
    return t < 0.5 ? '#F87171' : '#34D399';
};
</script>

<template>
    <div class="overflow-x-auto" role="table" :aria-label="ariaLabel">
        <table class="w-full border-collapse text-xs">
            <thead>
                <tr>
                    <th
                        class="text-left font-semibold px-3 py-2 sticky left-0"
                        style="color: var(--text-muted); background: var(--card-bg);"
                    >OPD</th>
                    <th
                        v-for="col in columns" :key="col.key"
                        class="text-center font-semibold px-3 py-2 whitespace-nowrap"
                        style="color: var(--text-muted);"
                    >{{ col.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.label">
                    <td
                        class="px-3 py-2 font-medium truncate max-w-[160px] sticky left-0"
                        style="color: var(--text-primary); background: var(--card-bg);"
                    >{{ row.label }}</td>
                    <td
                        v-for="col in columns" :key="col.key"
                        class="text-center px-3 py-2 font-semibold"
                        :style="`background:${cellColor(col, Number(row[col.key] ?? 0))}; color:${cellTextColor(col, Number(row[col.key] ?? 0))};`"
                    >
                        {{ Number(row[col.key] ?? 0) }}{{ col.suffix ?? '' }}
                    </td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length + 1" class="text-center px-3 py-6" style="color: var(--text-muted);">
                        Belum ada data perbandingan OPD.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
