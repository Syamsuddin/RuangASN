<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BarChart from '@/components/charts/BarChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import HeatmapGrid from '@/components/charts/HeatmapGrid.vue';
import {
    ClipboardList, FolderKanban, Award, CalendarCheck, FileText,
    Sparkles, AlertTriangle, TrendingUp, Loader2,
} from 'lucide-vue-next';

interface TaskMetrics { total: number; completed: number; overdue: number; in_progress: number; completion_rate: number }
interface SkpMetrics { plans: number; evaluated: number; avg_final_score: number; predicate_distribution: Record<string, number> }
interface Metrics {
    tasks: TaskMetrics;
    meetings: { total: number; completed: number };
    documents: { total: number; published: number; pending_approval: number };
    reports: { total: number; published: number };
    projects: { total: number; active: number; avg_progress: number };
    skp: SkpMetrics;
    users: { active: number };
    notifications: { sent: number };
}
interface TrendPoint { date: string; completion_rate: number; tasks_completed: number; tasks_overdue: number; avg_skp: number; active_projects: number }
interface OpdRow { organization_id: string; name: string; code: string; completion_rate: number; overdue: number; avg_skp: number; active_projects: number; avg_progress: number }

const props = defineProps<{
    current: Metrics;
    trend: TrendPoint[];
    opdComparison: OpdRow[] | null;
    aiBriefAvailable: boolean;
    organization: { id: string; name: string };
}>();

const period = ref<'30' | '14' | '7'>('30');
const trimmedTrend = computed(() => {
    const n = Number(period.value);
    return props.trend.slice(-n);
});

const kpis = computed(() => [
    {
        label: 'Penyelesaian Tugas',
        value: `${props.current.tasks.completion_rate}%`,
        sub: `${props.current.tasks.completed} dari ${props.current.tasks.total} selesai`,
        subDanger: false,
        icon: ClipboardList, accent: '#3B82F6', accentBg: 'rgba(59,130,246,0.15)',
    },
    {
        label: 'Proyek Aktif',
        value: props.current.projects.active,
        sub: `${props.current.projects.avg_progress}% rata-rata progres`,
        subDanger: false,
        icon: FolderKanban, accent: '#F59E0B', accentBg: 'rgba(245,158,11,0.15)',
    },
    {
        label: 'Rata-rata SKP',
        value: props.current.skp.avg_final_score.toFixed(2),
        sub: `${props.current.skp.evaluated} dievaluasi`,
        subDanger: false,
        icon: Award, accent: '#8B5CF6', accentBg: 'rgba(139,92,246,0.15)',
    },
    {
        label: 'Rapat Selesai',
        value: props.current.meetings.completed,
        sub: `dari ${props.current.meetings.total} rapat`,
        subDanger: false,
        icon: CalendarCheck, accent: '#10B981', accentBg: 'rgba(16,185,129,0.15)',
    },
    {
        label: 'Dokumen Terbit',
        value: props.current.documents.published,
        sub: `${props.current.documents.pending_approval} menunggu persetujuan`,
        subDanger: props.current.documents.pending_approval > 0,
        icon: FileText, accent: '#06B6D4', accentBg: 'rgba(6,182,212,0.15)',
    },
]);

// LineChart: task completion rate over the period.
const completionSeries = computed(() =>
    trimmedTrend.value.map((t) => ({
        label: t.date.slice(5),
        value: t.completion_rate,
    }))
);

// DonutChart: current task status breakdown.
const statusBreakdown = computed(() => {
    const t = props.current.tasks;
    const other = Math.max(0, t.total - t.completed - t.in_progress - t.overdue);
    return [
        { label: 'Selesai', value: t.completed, color: '#10B981' },
        { label: 'Dikerjakan', value: t.in_progress, color: '#F59E0B' },
        { label: 'Terlambat', value: t.overdue, color: '#EF4444' },
        { label: 'Lainnya', value: other, color: '#64748B' },
    ];
});

const predicateLabels: Record<string, string> = {
    sangat_baik: 'Sangat Baik',
    baik: 'Baik',
    cukup: 'Cukup',
    kurang: 'Kurang',
    sangat_kurang: 'Sangat Kurang',
};
const predicateColors: Record<string, string> = {
    sangat_baik: '#10B981',
    baik: '#3B82F6',
    cukup: '#F59E0B',
    kurang: '#F97316',
    sangat_kurang: '#EF4444',
};

// BarChart: SKP predicate distribution.
const predicateBars = computed(() =>
    Object.entries(props.current.skp.predicate_distribution).map(([key, value]) => ({
        label: predicateLabels[key]?.split(' ')[0] ?? key,
        value: value as number,
        color: predicateColors[key] ?? '#64748B',
    }))
);

// Heatmap columns for OPD comparison (pemda only).
const heatColumns = [
    { key: 'completion_rate', label: 'Penyelesaian', suffix: '%' },
    { key: 'avg_skp', label: 'Rata SKP' },
    { key: 'active_projects', label: 'Proyek Aktif' },
    { key: 'avg_progress', label: 'Progres', suffix: '%' },
    { key: 'overdue', label: 'Overdue', invert: true },
];

// ── AI Executive Brief (on-demand JSON call) ──
const brief = ref<string | null>(null);
const briefLoading = ref(false);
const briefError = ref<string | null>(null);

const fetchBrief = async () => {
    briefLoading.value = true;
    briefError.value = null;
    try {
        const { data } = await (window as any).axios.post('/executive/ai-brief');
        brief.value = data.brief;
    } catch (e) {
        briefError.value = 'Gagal memuat ringkasan eksekutif.';
    } finally {
        briefLoading.value = false;
    }
};

const todayDate = computed(() =>
    new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Eksekutif</span>
        </template>

        <div class="space-y-6 max-w-[1400px]">

            <!-- ── Header ── -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold flex items-center gap-2" style="color: var(--text-primary);">
                        <TrendingUp :size="22" style="color: #3B82F6;" /> Dashboard Eksekutif
                    </h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                        {{ todayDate }} · {{ organization.name }}
                    </p>
                </div>
                <div class="flex items-center gap-1 rounded-lg p-1" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                    <button
                        v-for="opt in (['7','14','30'] as const)" :key="opt"
                        @click="period = opt"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
                        :style="period === opt ? 'background:#3B82F6; color:white;' : 'color: var(--text-secondary);'"
                    >{{ opt }} hari</button>
                </div>
            </div>

            <!-- ── KPI cards ── -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div
                    v-for="kpi in kpis" :key="kpi.label"
                    class="rounded-xl p-5"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-3" :style="`background: ${kpi.accentBg};`">
                        <component :is="kpi.icon" :size="20" :style="`color: ${kpi.accent};`" />
                    </div>
                    <p class="text-3xl font-bold" style="color: var(--text-primary);">{{ kpi.value }}</p>
                    <p class="text-sm mt-0.5" style="color: var(--text-secondary);">{{ kpi.label }}</p>
                    <p class="text-xs mt-1" :style="kpi.subDanger ? 'color: #F87171;' : 'color: var(--text-muted);'">{{ kpi.sub }}</p>
                </div>
            </div>

            <!-- ── AI Executive Brief ── -->
            <div
                class="rounded-xl p-5"
                style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.25);"
            >
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-semibold text-sm flex items-center gap-2" style="color: var(--text-primary);">
                        <Sparkles :size="18" style="color: #8B5CF6;" /> AI Executive Brief
                    </h2>
                    <button
                        @click="fetchBrief"
                        :disabled="briefLoading"
                        class="text-xs px-3 py-1.5 rounded-md font-semibold text-white flex items-center gap-1.5"
                        style="background: #8B5CF6;"
                    >
                        <Loader2 v-if="briefLoading" :size="14" class="animate-spin" />
                        <Sparkles v-else :size="14" />
                        {{ brief ? 'Perbarui' : 'Buat Ringkasan' }}
                    </button>
                </div>
                <p v-if="briefError" class="text-sm" style="color: #F87171;">{{ briefError }}</p>
                <pre
                    v-else-if="brief"
                    class="text-sm whitespace-pre-wrap font-sans leading-relaxed"
                    style="color: var(--text-secondary);"
                >{{ brief }}</pre>
                <p v-else class="text-sm" style="color: var(--text-muted);">
                    Klik "Buat Ringkasan" untuk menghasilkan ringkasan eksekutif berbasis AI dari KPI organisasi.
                </p>
            </div>

            <!-- ── Trend + breakdown charts ── -->
            <div class="grid lg:grid-cols-3 gap-4">
                <!-- completion trend -->
                <div
                    class="lg:col-span-2 rounded-xl p-5"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <h2 class="font-semibold text-sm mb-4" style="color: var(--text-primary);">
                        Tingkat Penyelesaian Tugas ({{ period }} hari)
                    </h2>
                    <LineChart
                        v-if="completionSeries.length"
                        :data="completionSeries" :max="100" suffix="%" color="#3B82F6"
                        aria-label="Tren tingkat penyelesaian tugas"
                    />
                    <p v-else class="text-sm text-center py-8" style="color: var(--text-muted);">
                        Belum ada data tren. Snapshot harian terisi setelah job berjalan.
                    </p>
                </div>

                <!-- status donut -->
                <div
                    class="rounded-xl p-5"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <h2 class="font-semibold text-sm mb-4" style="color: var(--text-primary);">Status Tugas</h2>
                    <DonutChart
                        :data="statusBreakdown"
                        :center-value="current.tasks.total"
                        center-label="Total"
                        aria-label="Distribusi status tugas"
                    />
                </div>
            </div>

            <!-- ── SKP predicate distribution ── -->
            <div
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <h2 class="font-semibold text-sm mb-4" style="color: var(--text-primary);">Distribusi Predikat SKP</h2>
                <BarChart :data="predicateBars" :height="180" aria-label="Distribusi predikat SKP" />
            </div>

            <!-- ── OPD heatmap (pemda only) ── -->
            <div
                v-if="opdComparison"
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <h2 class="font-semibold text-sm mb-4" style="color: var(--text-primary);">Perbandingan Kinerja OPD</h2>
                <HeatmapGrid :rows="opdComparison" :columns="heatColumns" aria-label="Heatmap perbandingan kinerja OPD" />
            </div>

            <!-- ── Overdue / alert section ── -->
            <div
                v-if="current.tasks.overdue > 0"
                class="rounded-xl p-5 flex items-start gap-3"
                style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3);"
            >
                <AlertTriangle :size="20" class="shrink-0 mt-0.5" style="color: #EF4444;" />
                <div>
                    <h2 class="font-semibold text-sm" style="color: var(--text-primary);">Perhatian: Tugas Terlambat</h2>
                    <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                        Terdapat <b style="color: #F87171;">{{ current.tasks.overdue }} tugas terlambat</b> di organisasi yang memerlukan tindak lanjut.
                    </p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
