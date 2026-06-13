<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BarChart3, TrendingUp, Users } from 'lucide-vue-next';

interface Stats {
    total_plans: number;
    avg_final_score: number | null;
    predicate_distribution: Record<string, number>;
}

interface Member {
    user?: { id: string; name: string } | null;
    status?: string | null;
    final_score?: number | null;
    predicate?: string | null;
    indicators_count: number;
}

interface Props {
    stats: Stats;
    members: Member[];
}

const props = defineProps<Props>();

const predicateConfig: Record<string, { bg: string; text: string; label: string }> = {
    sangat_baik:   { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Sangat Baik' },
    baik:          { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Baik' },
    cukup:         { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Cukup' },
    kurang:        { bg: 'rgba(249,115,22,0.2)',  text: '#FB923C', label: 'Kurang' },
    sangat_kurang: { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Sangat Kurang' },
    belum_dievaluasi: { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Belum Dievaluasi' },
};

const predicateBadge = (p?: string | null) => predicateConfig[p ?? 'belum_dievaluasi'] ?? predicateConfig['belum_dievaluasi'];

const predicateOrder = ['sangat_baik', 'baik', 'cukup', 'kurang', 'sangat_kurang', 'belum_dievaluasi'];

const maxCount = computed(() =>
    Math.max(1, ...predicateOrder.map(k => props.stats.predicate_distribution[k] ?? 0))
);
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">
                <a href="/performance" style="color: var(--text-muted);">Kinerja / SKP</a>
            </span>
            <span class="mx-1" style="color: var(--text-muted);">/</span>
            <span style="color: var(--text-primary);">Analytics</span>
        </template>

        <div class="space-y-6 max-w-[1200px]">

            <!-- Summary cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div
                    class="rounded-xl p-4"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <div class="flex items-center gap-3 mb-1">
                        <Users :size="18" style="color: #3B82F6;" />
                        <span class="text-sm font-semibold" style="color: var(--text-secondary);">Total SKP</span>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ stats.total_plans }}</p>
                </div>

                <div
                    class="rounded-xl p-4"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <div class="flex items-center gap-3 mb-1">
                        <TrendingUp :size="18" style="color: #10B981;" />
                        <span class="text-sm font-semibold" style="color: var(--text-secondary);">Rata-rata Nilai Akhir</span>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ stats.avg_final_score != null ? stats.avg_final_score : '—' }}
                    </p>
                </div>

                <div
                    class="rounded-xl p-4"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <div class="flex items-center gap-3 mb-1">
                        <BarChart3 :size="18" style="color: #8B5CF6;" />
                        <span class="text-sm font-semibold" style="color: var(--text-secondary);">Sudah Dievaluasi</span>
                    </div>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ stats.total_plans - (stats.predicate_distribution['belum_dievaluasi'] ?? 0) }}
                    </p>
                </div>
            </div>

            <!-- Predicate distribution bar chart -->
            <div
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">Distribusi Predikat</h2>
                <div class="space-y-3">
                    <div v-for="key in predicateOrder" :key="key" class="flex items-center gap-4">
                        <span class="text-xs w-36 shrink-0" style="color: var(--text-secondary);">
                            {{ predicateBadge(key).label }}
                        </span>
                        <div class="flex-1 h-6 rounded-full overflow-hidden" style="background: var(--bg-tertiary);">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="`width: ${((stats.predicate_distribution[key] ?? 0) / maxCount) * 100}%; background: ${predicateBadge(key).text};`"
                            />
                        </div>
                        <span class="text-sm font-semibold w-8 text-right" style="color: var(--text-primary);">
                            {{ stats.predicate_distribution[key] ?? 0 }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Member table -->
            <div
                class="rounded-xl overflow-hidden"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <div class="px-5 py-4 border-b" style="border-color: var(--border-color);">
                    <h2 class="text-base font-semibold" style="color: var(--text-primary);">Progress per Pegawai</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background: var(--bg-tertiary);">
                                <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Pegawai</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Status</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Indikator</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Nilai Akhir</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Predikat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--border-color);">
                            <tr
                                v-for="(m, i) in members"
                                :key="i"
                                class="hover:opacity-80 transition-opacity"
                            >
                                <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">
                                    {{ m.user?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3" style="color: var(--text-muted);">
                                    {{ m.status ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right" style="color: var(--text-secondary);">
                                    {{ m.indicators_count }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold" style="color: var(--text-primary);">
                                    {{ m.final_score != null ? m.final_score : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="m.predicate"
                                        class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                        :style="`background: ${predicateBadge(m.predicate).bg}; color: ${predicateBadge(m.predicate).text};`"
                                    >{{ predicateBadge(m.predicate).label }}</span>
                                    <span v-else class="text-xs" style="color: var(--text-muted);">—</span>
                                </td>
                            </tr>
                            <tr v-if="!members.length">
                                <td colspan="5" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">
                                    Tidak ada data
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
