<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, X, BarChart3, FolderOpen, ChevronRight, TrendingUp } from 'lucide-vue-next';

interface Period {
    id: string;
    name: string;
    year: number;
}

interface Plan {
    id: string;
    status: string;
    status_label: string;
    period?: { id: string; name: string; year: number } | null;
    superior?: { id: string; name: string } | null;
    overall_achievement_pct?: number;
    evaluation?: {
        final_score?: number | null;
        predicate?: string | null;
        predicate_label?: string;
    } | null;
    submitted_at?: string | null;
    approved_at?: string | null;
    created_at?: string;
}

interface Superior {
    id: string;
    name: string;
}

interface Props {
    plans: Plan[];
    activePeriod: Period | null;
    superiors: Superior[];
    periods: Period[];
    can: { create: boolean };
}

const props = defineProps<Props>();

const showCreateSlide = ref(false);

const createForm = useForm({
    period_id:   '',
    superior_id: '',
});

const submitCreate = () => {
    createForm.post('/performance/plans', {
        onSuccess: () => {
            showCreateSlide.value = false;
            createForm.reset();
        },
    });
};

const statusConfig: Record<string, { bg: string; text: string }> = {
    planning:   { bg: 'rgba(100,116,139,0.2)',   text: '#94A3B8' },
    active:     { bg: 'rgba(16,185,129,0.2)',    text: '#34D399' },
    evaluating: { bg: 'rgba(139,92,246,0.2)',    text: '#A78BFA' },
    finalized:  { bg: 'rgba(59,130,246,0.25)',   text: '#60A5FA' },
    archived:   { bg: 'rgba(100,116,139,0.15)',  text: '#64748B' },
};

const predicateConfig: Record<string, { bg: string; text: string }> = {
    sangat_baik:   { bg: 'rgba(16,185,129,0.2)',  text: '#34D399' },
    baik:          { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA' },
    cukup:         { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D' },
    kurang:        { bg: 'rgba(249,115,22,0.2)',  text: '#FB923C' },
    sangat_kurang: { bg: 'rgba(239,68,68,0.2)',   text: '#F87171' },
};

const statusBadge = (s: string) => statusConfig[s] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8' };
const predicateBadge = (p: string) => predicateConfig[p] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8' };

// Overall achievement across all plans
const overallAchievement = computed(() => {
    if (!props.plans.length) return 0;
    const active = props.plans.filter(p => ['active', 'evaluating', 'finalized'].includes(p.status));
    if (!active.length) return 0;
    const avg = active.reduce((sum, p) => sum + (p.overall_achievement_pct ?? 0), 0) / active.length;
    return Math.min(Math.round(avg), 120);
});

const progressRingCircumference = 2 * Math.PI * 45;
const progressDashOffset = computed(() =>
    progressRingCircumference - (overallAchievement.value / 120) * progressRingCircumference
);

const predictedPredicate = computed(() => {
    const score = overallAchievement.value * 0.7; // rough preview (behavior unknown)
    if (score >= 90) return { label: 'Sangat Baik', key: 'sangat_baik' };
    if (score >= 76) return { label: 'Baik', key: 'baik' };
    if (score >= 61) return { label: 'Cukup', key: 'cukup' };
    if (score >= 51) return { label: 'Kurang', key: 'kurang' };
    return { label: 'Sangat Kurang', key: 'sangat_kurang' };
});

const formatDate = (iso?: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Kinerja / SKP</span>
        </template>

        <div class="space-y-6 max-w-[1200px]">

            <!-- Hero: Progress Ring -->
            <div
                class="rounded-2xl p-6 flex flex-col sm:flex-row items-center gap-6"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <!-- SVG Progress Ring -->
                <div class="relative shrink-0">
                    <svg width="120" height="120" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="45" fill="none" stroke="var(--border-color)" stroke-width="10" />
                        <circle
                            cx="60" cy="60" r="45" fill="none" stroke="#3B82F6" stroke-width="10"
                            stroke-linecap="round"
                            :stroke-dasharray="progressRingCircumference"
                            :stroke-dashoffset="progressDashOffset"
                            style="transform: rotate(-90deg); transform-origin: center; transition: stroke-dashoffset 0.5s ease;"
                        />
                        <text x="60" y="56" text-anchor="middle" font-size="18" font-weight="700" fill="var(--text-primary)">
                            {{ overallAchievement }}%
                        </text>
                        <text x="60" y="72" text-anchor="middle" font-size="9" fill="var(--text-muted)">Capaian</text>
                    </svg>
                </div>

                <div class="flex-1 text-center sm:text-left">
                    <h1 class="text-xl font-bold mb-1" style="color: var(--text-primary);">Kinerja / SKP Saya</h1>
                    <p class="text-sm mb-3" style="color: var(--text-muted);">
                        {{ plans.length }} SKP terdaftar
                        <span v-if="activePeriod"> &bull; Periode: {{ activePeriod.name }}</span>
                    </p>
                    <!-- Predicted predicate badge -->
                    <span
                        v-if="plans.length > 0"
                        class="inline-block text-xs font-semibold px-3 py-1 rounded-full"
                        :style="`background: ${predicateBadge(predictedPredicate.key).bg}; color: ${predicateBadge(predictedPredicate.key).text};`"
                    >Prediksi Predikat: {{ predictedPredicate.label }}</span>
                </div>

                <button
                    v-if="can.create"
                    @click="showCreateSlide = true"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90 shrink-0"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Buat SKP
                </button>
            </div>

            <!-- Plans List -->
            <div v-if="plans.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="plan in plans"
                    :key="plan.id"
                    :href="`/performance/plans/${plan.id}`"
                    class="block rounded-xl p-4 hover:opacity-90 transition-opacity"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <!-- Icon + status -->
                    <div class="flex items-start justify-between mb-3">
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center"
                            style="background: rgba(59,130,246,0.1);"
                        >
                            <BarChart3 :size="20" style="color: #3B82F6;" />
                        </div>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :style="`background: ${statusBadge(plan.status).bg}; color: ${statusBadge(plan.status).text};`"
                        >{{ plan.status_label }}</span>
                    </div>

                    <!-- Period name -->
                    <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">
                        {{ plan.period?.name ?? '—' }}
                    </p>

                    <!-- Superior -->
                    <p class="text-xs mb-3" style="color: var(--text-muted);">
                        Atasan: {{ plan.superior?.name ?? '—' }}
                    </p>

                    <!-- Progress bar -->
                    <div class="mb-2">
                        <div class="flex justify-between text-[11px] mb-1" style="color: var(--text-muted);">
                            <span>Capaian Kinerja</span>
                            <span>{{ plan.overall_achievement_pct ?? 0 }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full" style="background: var(--bg-tertiary);">
                            <div
                                class="h-1.5 rounded-full transition-all"
                                style="background: #3B82F6;"
                                :style="`width: ${Math.min(plan.overall_achievement_pct ?? 0, 100)}%`"
                            />
                        </div>
                    </div>

                    <!-- Final score if evaluated -->
                    <div v-if="plan.evaluation?.final_score" class="flex items-center justify-between text-[11px]">
                        <span style="color: var(--text-muted);">Nilai Akhir</span>
                        <span
                            class="font-semibold px-1.5 py-0.5 rounded"
                            :style="`color: ${predicateBadge(plan.evaluation.predicate ?? '').text}; background: ${predicateBadge(plan.evaluation.predicate ?? '').bg};`"
                        >{{ plan.evaluation.final_score }} — {{ plan.evaluation.predicate_label }}</span>
                    </div>

                    <div class="flex items-center justify-between text-[11px] mt-2" style="color: var(--text-muted);">
                        <span>Dibuat {{ formatDate(plan.created_at) }}</span>
                        <ChevronRight :size="14" />
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="plans.length === 0"
                class="rounded-xl py-16 text-center"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <FolderOpen :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Belum ada SKP</p>
                <p class="text-sm mb-4" style="color: var(--text-muted);">Buat Sasaran Kinerja Pegawai (SKP) untuk periode aktif</p>
                <button
                    v-if="can.create"
                    @click="showCreateSlide = true"
                    class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >Buat SKP</button>
            </div>

        </div>

        <!-- Create SKP Slide-over -->
        <Teleport to="body">
            <div v-if="showCreateSlide" class="fixed inset-0 z-50 flex justify-end">
                <div class="absolute inset-0" style="background: rgba(0,0,0,0.4);" @click="showCreateSlide = false" />

                <div
                    class="relative z-10 w-[420px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Buat SKP Baru</h2>
                        <button @click="showCreateSlide = false" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">

                        <!-- Period -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Periode SKP <span style="color: #EF4444;">*</span>
                            </label>
                            <select
                                v-model="createForm.period_id"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option value="">-- Pilih Periode --</option>
                                <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <p v-if="createForm.errors.period_id" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.period_id }}</p>
                        </div>

                        <!-- Superior -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Atasan Langsung
                            </label>
                            <select
                                v-model="createForm.superior_id"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option value="">-- Pilih Atasan --</option>
                                <option v-for="s in superiors" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="createForm.errors.superior_id" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.superior_id }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing || !createForm.period_id"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="(createForm.processing || !createForm.period_id) ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >{{ createForm.processing ? 'Menyimpan...' : 'Buat SKP' }}</button>
                            <button
                                type="button"
                                @click="showCreateSlide = false"
                                class="px-4 py-2.5 rounded-lg text-sm font-medium border"
                                style="border-color: var(--border-color); color: var(--text-secondary); background: var(--bg-tertiary);"
                            >Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
