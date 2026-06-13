<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, X, ChevronRight, TrendingUp, Check, AlertCircle,
    ClipboardList, BarChart3, History,
} from 'lucide-vue-next';

// ── Types ─────────────────────────────────────────────────────────────
interface Indicator {
    id: string;
    perspective: string;
    name: string;
    target_value: number;
    target_unit: string;
    weight: number;
    realization_value?: number | null;
    achievement_pct?: number | null;
    superior_expectation?: string | null;
    sort_order: number;
    parent_indicator_id?: string | null;
}

interface Realization {
    id: string;
    realization_value: number;
    realization_date: string;
    description?: string | null;
}

interface Evaluation {
    id: string;
    performance_score?: number | null;
    behavior_score?: number | null;
    final_score?: number | null;
    predicate?: string | null;
    predicate_label?: string;
    behavior_service?: number | null;
    behavior_commit?: number | null;
    behavior_initiative?: number | null;
    behavior_teamwork?: number | null;
    behavior_leadership?: number | null;
    superior_feedback?: string | null;
    evaluated_at?: string | null;
    finalized_at?: string | null;
}

interface Plan {
    id: string;
    status: string;
    status_label: string;
    period?: { id: string; name: string; year: number } | null;
    superior?: { id: string; name: string } | null;
    user?: { id: string; name: string } | null;
    submitted_at?: string | null;
    approved_at?: string | null;
    overall_achievement_pct?: number;
    indicators?: Indicator[];
    evaluation?: Evaluation | null;
}

interface Can {
    update: boolean;
    submit: boolean;
    approve: boolean;
    review: boolean;
    evaluate: boolean;
    addRealization: boolean;
}

interface Props {
    plan: Plan;
    perspectiveGroups: Record<string, Indicator[]>;
    perspectives: string[];
    can: Can;
}

// ── Setup ─────────────────────────────────────────────────────────────
const props = defineProps<Props>();

type TabKey = 'rencana' | 'realisasi' | 'evaluasi';
const activeTab = ref<TabKey>('rencana');

// ── Progress Ring ─────────────────────────────────────────────────────
const circumference = 2 * Math.PI * 45;
const achievement = computed(() => Math.min(props.plan.overall_achievement_pct ?? 0, 120));
const dashOffset = computed(() => circumference - (achievement.value / 120) * circumference);

// ── Status colors ─────────────────────────────────────────────────────
const statusConfig: Record<string, { bg: string; text: string }> = {
    planning:   { bg: 'rgba(100,116,139,0.2)',  text: '#94A3B8' },
    active:     { bg: 'rgba(16,185,129,0.2)',   text: '#34D399' },
    evaluating: { bg: 'rgba(139,92,246,0.2)',   text: '#A78BFA' },
    finalized:  { bg: 'rgba(59,130,246,0.25)',  text: '#60A5FA' },
    archived:   { bg: 'rgba(100,116,139,0.15)', text: '#64748B' },
};

const predicateConfig: Record<string, { bg: string; text: string }> = {
    sangat_baik:   { bg: 'rgba(16,185,129,0.2)',  text: '#34D399' },
    baik:          { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA' },
    cukup:         { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D' },
    kurang:        { bg: 'rgba(249,115,22,0.2)',  text: '#FB923C' },
    sangat_kurang: { bg: 'rgba(239,68,68,0.2)',   text: '#F87171' },
};

const statusBadge = (s: string) => statusConfig[s] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8' };
const predicateBadge = (p?: string | null) => predicateConfig[p ?? ''] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8' };

// ── Perspective labels ─────────────────────────────────────────────────
const perspectiveLabels: Record<string, string> = {
    penerima_layanan: 'Penerima Layanan',
    proses_bisnis:    'Proses Bisnis',
    pengembangan:     'Pengembangan',
    anggaran:         'Anggaran',
};

// ── Add Indicator form ────────────────────────────────────────────────
const showAddIndicator = ref(false);
const indicatorForm = useForm({
    perspective:  'penerima_layanan',
    name:         '',
    target_value: '',
    target_unit:  '',
    weight:       '100',
    superior_expectation: '',
});

const submitIndicator = () => {
    indicatorForm.post(`/performance/plans/${props.plan.id}/indicators`, {
        onSuccess: () => {
            showAddIndicator.value = false;
            indicatorForm.reset();
        },
    });
};

// ── Add Realization ───────────────────────────────────────────────────
const realizationTarget = ref<string | null>(null);
const realizationForm = useForm({
    realization_value: '',
    realization_date:  new Date().toISOString().split('T')[0],
    description:       '',
});

const openRealization = (indicatorId: string) => {
    realizationTarget.value = indicatorId;
    realizationForm.reset();
};

const submitRealization = () => {
    if (!realizationTarget.value) return;
    realizationForm.post(`/performance/indicators/${realizationTarget.value}/realizations`, {
        onSuccess: () => {
            realizationTarget.value = null;
            realizationForm.reset();
        },
    });
};

// ── Submit / Approve ──────────────────────────────────────────────────
const submitForm = useForm({});
const approveForm = useForm({});

const submitPlan = () => submitForm.post(`/performance/plans/${props.plan.id}/submit`);
const approvePlan = () => approveForm.post(`/performance/plans/${props.plan.id}/approve`);

// ── Evaluate form ─────────────────────────────────────────────────────
const evalForm = useForm({
    behavior_service:    props.plan.evaluation?.behavior_service?.toString() ?? '',
    behavior_commit:     props.plan.evaluation?.behavior_commit?.toString() ?? '',
    behavior_initiative: props.plan.evaluation?.behavior_initiative?.toString() ?? '',
    behavior_teamwork:   props.plan.evaluation?.behavior_teamwork?.toString() ?? '',
    behavior_leadership: props.plan.evaluation?.behavior_leadership?.toString() ?? '',
    superior_feedback:   props.plan.evaluation?.superior_feedback ?? '',
});

const submitEvaluation = () => {
    evalForm.post(`/performance/plans/${props.plan.id}/evaluate`);
};

// ── Helpers ───────────────────────────────────────────────────────────
const formatDate = (iso?: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">
                <a href="/performance" style="color: var(--text-muted);">Kinerja / SKP</a>
            </span>
            <ChevronRight :size="14" style="color: var(--text-muted);" class="mx-1" />
            <span style="color: var(--text-primary);">{{ plan.period?.name ?? 'SKP' }}</span>
        </template>

        <div class="space-y-4 max-w-[1000px]">

            <!-- Header card -->
            <div
                class="rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <!-- Progress ring -->
                <div class="shrink-0">
                    <svg width="100" height="100" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="45" fill="none" stroke="var(--border-color)" stroke-width="10" />
                        <circle
                            cx="60" cy="60" r="45" fill="none" stroke="#3B82F6" stroke-width="10"
                            stroke-linecap="round"
                            :stroke-dasharray="circumference"
                            :stroke-dashoffset="dashOffset"
                            style="transform: rotate(-90deg); transform-origin: center; transition: stroke-dashoffset 0.5s;"
                        />
                        <text x="60" y="56" text-anchor="middle" font-size="18" font-weight="700" fill="var(--text-primary)">
                            {{ achievement }}%
                        </text>
                        <text x="60" y="72" text-anchor="middle" font-size="9" fill="var(--text-muted)">Capaian</text>
                    </svg>
                </div>

                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h1 class="text-lg font-bold" style="color: var(--text-primary);">
                            {{ plan.period?.name ?? 'SKP' }}
                        </h1>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :style="`background: ${statusBadge(plan.status).bg}; color: ${statusBadge(plan.status).text};`"
                        >{{ plan.status_label }}</span>
                    </div>
                    <p class="text-sm mb-2" style="color: var(--text-muted);">
                        Pegawai: <strong>{{ plan.user?.name ?? '—' }}</strong> &bull;
                        Atasan: <strong>{{ plan.superior?.name ?? '—' }}</strong>
                    </p>
                    <!-- Evaluation result -->
                    <span
                        v-if="plan.evaluation?.predicate"
                        class="inline-block text-xs font-semibold px-3 py-0.5 rounded-full"
                        :style="`background: ${predicateBadge(plan.evaluation.predicate).bg}; color: ${predicateBadge(plan.evaluation.predicate).text};`"
                    >
                        {{ plan.evaluation.predicate_label }} &mdash; Nilai {{ plan.evaluation.final_score }}
                    </span>
                </div>

                <!-- Action buttons -->
                <div class="flex flex-col gap-2 shrink-0">
                    <button
                        v-if="can.submit && plan.status === 'planning'"
                        @click="submitPlan"
                        :disabled="submitForm.processing"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        style="background: #3B82F6;"
                    >Ajukan ke Atasan</button>
                    <button
                        v-if="can.approve && plan.status === 'planning'"
                        @click="approvePlan"
                        :disabled="approveForm.processing"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        style="background: #10B981;"
                    >Setujui SKP</button>
                    <button
                        v-if="can.evaluate && plan.status === 'active'"
                        @click="activeTab = 'evaluasi'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold border transition-opacity hover:opacity-80"
                        style="border-color: var(--border-color); color: var(--text-secondary);"
                    >Evaluasi</button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 border-b" style="border-color: var(--border-color);">
                <button
                    v-for="tab in ([{key: 'rencana', label: 'Rencana'}, {key: 'realisasi', label: 'Realisasi'}, {key: 'evaluasi', label: 'Evaluasi'}] as const)"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
                    :style="activeTab === tab.key
                        ? 'border-color: #3B82F6; color: #3B82F6;'
                        : 'border-color: transparent; color: var(--text-muted);'"
                >{{ tab.label }}</button>
            </div>

            <!-- TAB: Rencana ─────────────────────────────────────────────── -->
            <div v-if="activeTab === 'rencana'" class="space-y-4">

                <div
                    v-for="perspective in perspectives"
                    :key="perspective"
                    class="rounded-xl overflow-hidden"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <div
                        class="flex items-center justify-between px-4 py-3 border-b"
                        style="border-color: var(--border-color); background: var(--bg-tertiary);"
                    >
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">
                            {{ perspectiveLabels[perspective] }}
                        </h3>
                        <span class="text-xs" style="color: var(--text-muted);">
                            {{ (perspectiveGroups[perspective] ?? []).length }} indikator
                        </span>
                    </div>

                    <!-- Indicator rows -->
                    <div
                        v-if="(perspectiveGroups[perspective] ?? []).length > 0"
                        class="divide-y"
                        style="border-color: var(--border-color);"
                    >
                        <div
                            v-for="ind in perspectiveGroups[perspective]"
                            :key="ind.id"
                            class="px-4 py-3"
                        >
                            <div class="flex items-start justify-between gap-4 mb-2">
                                <p class="text-sm font-medium flex-1" style="color: var(--text-primary);">{{ ind.name }}</p>
                                <span class="text-xs shrink-0" style="color: var(--text-muted);">
                                    Bobot {{ ind.weight }}%
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs mb-2" style="color: var(--text-muted);">
                                <span>Target: <strong>{{ ind.target_value }} {{ ind.target_unit }}</strong></span>
                                <span>Realisasi: <strong>{{ ind.realization_value ?? '0' }} {{ ind.target_unit }}</strong></span>
                            </div>
                            <!-- Achievement bar -->
                            <div class="h-1.5 rounded-full" style="background: var(--bg-tertiary);">
                                <div
                                    class="h-1.5 rounded-full transition-all"
                                    :style="`width: ${Math.min(ind.achievement_pct ?? 0, 100)}%; background: ${(ind.achievement_pct ?? 0) >= 90 ? '#10B981' : (ind.achievement_pct ?? 0) >= 60 ? '#3B82F6' : '#F59E0B'};`"
                                />
                            </div>
                            <p class="text-[11px] mt-1" style="color: var(--text-muted);">
                                Capaian: {{ ind.achievement_pct ?? 0 }}%
                            </p>
                        </div>
                    </div>

                    <div v-else class="px-4 py-6 text-center text-sm" style="color: var(--text-muted);">
                        Belum ada indikator untuk perspektif ini
                    </div>
                </div>

                <!-- Add indicator button -->
                <button
                    v-if="can.update"
                    @click="showAddIndicator = true"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-medium border-2 border-dashed transition-opacity hover:opacity-70"
                    style="border-color: var(--border-color); color: var(--text-muted);"
                >
                    <Plus :size="16" />
                    Tambah Indikator
                </button>
            </div>

            <!-- TAB: Realisasi ───────────────────────────────────────────── -->
            <div v-if="activeTab === 'realisasi'" class="space-y-3">
                <div
                    v-for="ind in (plan.indicators ?? [])"
                    :key="ind.id"
                    class="rounded-xl p-4"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div class="flex-1">
                            <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">{{ ind.name }}</p>
                            <p class="text-xs" style="color: var(--text-muted);">
                                Target: {{ ind.target_value }} {{ ind.target_unit }} &bull;
                                Realisasi: {{ ind.realization_value ?? 0 }} {{ ind.target_unit }} &bull;
                                Capaian: {{ ind.achievement_pct ?? 0 }}%
                            </p>
                        </div>
                        <button
                            v-if="can.addRealization"
                            @click="openRealization(ind.id)"
                            class="text-xs px-3 py-1.5 rounded-lg font-medium transition-opacity hover:opacity-80"
                            style="background: rgba(59,130,246,0.15); color: #60A5FA;"
                        >
                            <Plus :size="12" class="inline mr-1" />Tambah
                        </button>
                    </div>

                    <!-- Realization input inline -->
                    <div v-if="realizationTarget === ind.id" class="mt-3 p-3 rounded-lg space-y-3" style="background: var(--bg-tertiary);">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">
                                    Nilai Realisasi
                                </label>
                                <input
                                    v-model="realizationForm.realization_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">
                                    Tanggal
                                </label>
                                <input
                                    v-model="realizationForm.realization_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Keterangan</label>
                            <input
                                v-model="realizationForm.description"
                                type="text"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="submitRealization"
                                :disabled="realizationForm.processing"
                                class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity"
                                style="background: #3B82F6;"
                            >Simpan</button>
                            <button
                                @click="realizationTarget = null"
                                class="px-3 py-2 rounded-lg text-sm border"
                                style="border-color: var(--border-color); color: var(--text-secondary);"
                            >Batal</button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!(plan.indicators ?? []).length"
                    class="rounded-xl py-12 text-center"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <p class="text-sm" style="color: var(--text-muted);">Belum ada indikator — tambahkan di tab Rencana.</p>
                </div>
            </div>

            <!-- TAB: Evaluasi ───────────────────────────────────────────── -->
            <div v-if="activeTab === 'evaluasi'" class="space-y-4">

                <!-- Existing evaluation result (read-only for owner) -->
                <div
                    v-if="plan.evaluation && !can.evaluate"
                    class="rounded-xl p-5"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Hasil Evaluasi</h3>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold" style="color: #3B82F6;">{{ plan.evaluation.performance_score ?? '—' }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-muted);">Nilai Kinerja</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold" style="color: #8B5CF6;">{{ plan.evaluation.behavior_score ?? '—' }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-muted);">Nilai Perilaku</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold" style="color: #10B981;">{{ plan.evaluation.final_score ?? '—' }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-muted);">Nilai Akhir</p>
                        </div>
                    </div>
                    <div class="text-center mb-4">
                        <span
                            class="inline-block text-sm font-bold px-4 py-1.5 rounded-full"
                            :style="`background: ${predicateBadge(plan.evaluation.predicate).bg}; color: ${predicateBadge(plan.evaluation.predicate).text};`"
                        >{{ plan.evaluation.predicate_label }}</span>
                    </div>
                    <div v-if="plan.evaluation.superior_feedback" class="p-3 rounded-lg" style="background: var(--bg-tertiary);">
                        <p class="text-xs font-semibold mb-1" style="color: var(--text-secondary);">Feedback Atasan</p>
                        <p class="text-sm" style="color: var(--text-primary);">{{ plan.evaluation.superior_feedback }}</p>
                    </div>
                    <p class="text-xs mt-3" style="color: var(--text-muted);">
                        Dievaluasi: {{ formatDate(plan.evaluation.evaluated_at) }}
                    </p>
                </div>

                <!-- Evaluation form for reviewer/evaluator -->
                <div
                    v-if="can.evaluate"
                    class="rounded-xl p-5"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Form Evaluasi (Skala 0-120)</h3>
                    <p class="text-xs mb-4" style="color: var(--text-muted);">
                        Nilai Akhir = 0.7 &times; Nilai Kinerja + 0.3 &times; Nilai Perilaku (PermenPANRB 6/2022)
                    </p>

                    <div class="space-y-3 mb-4">
                        <div v-for="dim in [
                            { key: 'behavior_service',    label: 'Orientasi Pelayanan' },
                            { key: 'behavior_commit',     label: 'Komitmen' },
                            { key: 'behavior_initiative', label: 'Inisiatif Kerja' },
                            { key: 'behavior_teamwork',   label: 'Kerjasama' },
                            { key: 'behavior_leadership', label: 'Kepemimpinan (opsional)' },
                        ]" :key="dim.key" class="flex items-center gap-4">
                            <label class="text-sm flex-1" style="color: var(--text-secondary);">{{ dim.label }}</label>
                            <input
                                v-model="(evalForm as any)[dim.key]"
                                type="number"
                                min="0"
                                max="120"
                                step="0.01"
                                class="w-24 px-3 py-2 rounded-lg text-sm border outline-none text-center"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2" style="color: var(--text-secondary);">
                            Feedback / Catatan Atasan
                        </label>
                        <textarea
                            v-model="evalForm.superior_feedback"
                            rows="3"
                            class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            placeholder="Catatan evaluasi atasan..."
                        />
                    </div>

                    <button
                        @click="submitEvaluation"
                        :disabled="evalForm.processing"
                        class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        style="background: #3B82F6;"
                    >{{ evalForm.processing ? 'Menyimpan...' : 'Simpan Evaluasi' }}</button>
                </div>

                <div
                    v-if="!plan.evaluation && !can.evaluate"
                    class="rounded-xl py-12 text-center"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <AlertCircle :size="36" class="mx-auto mb-3" style="color: var(--text-muted);" />
                    <p class="text-sm" style="color: var(--text-muted);">Belum ada evaluasi untuk SKP ini.</p>
                </div>
            </div>

        </div>

        <!-- Add Indicator Slide-over -->
        <Teleport to="body">
            <div v-if="showAddIndicator" class="fixed inset-0 z-50 flex justify-end">
                <div class="absolute inset-0" style="background: rgba(0,0,0,0.4);" @click="showAddIndicator = false" />

                <div
                    class="relative z-10 w-[440px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Tambah Indikator Kinerja</h2>
                        <button @click="showAddIndicator = false" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <form @submit.prevent="submitIndicator" class="flex-1 px-6 py-5 space-y-4">

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Perspektif *</label>
                            <select
                                v-model="indicatorForm.perspective"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option v-for="(label, key) in perspectiveLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Nama Indikator *</label>
                            <textarea
                                v-model="indicatorForm.name"
                                rows="2"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                placeholder="Deskripsi indikator kinerja..."
                            />
                            <p v-if="indicatorForm.errors.name" class="mt-1 text-xs" style="color: #EF4444;">{{ indicatorForm.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Target *</label>
                                <input
                                    v-model="indicatorForm.target_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Satuan *</label>
                                <input
                                    v-model="indicatorForm.target_unit"
                                    type="text"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                    placeholder="dokumen, kegiatan..."
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Bobot (%)</label>
                            <input
                                v-model="indicatorForm.weight"
                                type="number"
                                min="0"
                                max="100"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Ekspektasi Atasan</label>
                            <textarea
                                v-model="indicatorForm.superior_expectation"
                                rows="2"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="indicatorForm.processing"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white"
                                style="background: #3B82F6;"
                            >{{ indicatorForm.processing ? 'Menyimpan...' : 'Tambah Indikator' }}</button>
                            <button
                                type="button"
                                @click="showAddIndicator = false"
                                class="px-4 py-2.5 rounded-lg text-sm font-medium border"
                                style="border-color: var(--border-color); color: var(--text-secondary);"
                            >Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
