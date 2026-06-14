<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, Search, X, FolderKanban, Wallet, CalendarDays, Users,
} from 'lucide-vue-next';

interface ProjectCard {
    id: string;
    name: string;
    status: string | { value: string };
    progress_percent: number;
    budget: number | null;
    budget_spent: number;
    budget_utilization: number;
    planned_end_date?: string | null;
    milestones_count: number;
    risks_count: number;
    tasks_count: number;
    meetings_count: number;
    updated_at?: string;
    owner?: { id: string; name: string } | null;
}

interface Props {
    projects: ProjectCard[];
    filters: { status?: string; search?: string };
    statuses: string[];
    teams: Array<{ id: string; name: string }>;
    users: Array<{ id: string; name: string; nip?: string }>;
    can: { create: boolean };
}

const props = defineProps<Props>();

const showCreateSlide = ref(false);
const filterStatus = ref(props.filters.status ?? '');
const searchQuery  = ref(props.filters.search ?? '');

const applyFilters = () => {
    router.get('/projects', {
        status: filterStatus.value || undefined,
        search: searchQuery.value || undefined,
    }, { preserveState: true, replace: true });
};

// Status badge config (DESIGN.md §6.13 dual-mode tokens)
const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:      { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    planning:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Perencanaan' },
    active:     { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Aktif' },
    on_hold:    { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Ditangguhkan' },
    monitoring: { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Monitoring' },
    closing:    { bg: 'rgba(245,158,11,0.2)',  text: '#FBBF24', label: 'Penutupan' },
    completed:  { bg: 'rgba(16,185,129,0.3)',  text: '#10B981', label: 'Selesai' },
    cancelled:  { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Dibatalkan' },
    archived:   { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan' },
};

const statusBadge = (s: string | { value: string }) => {
    const key = typeof s === 'string' ? s : s.value;
    return statusConfig[key] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: key };
};

const formatDate = (iso?: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatRupiah = (n: number | null) => {
    if (n === null || n === undefined) return '—';
    if (n >= 1_000_000_000) return `Rp ${(n / 1_000_000_000).toFixed(1)} M`;
    if (n >= 1_000_000)     return `Rp ${(n / 1_000_000).toFixed(0)} jt`;
    return `Rp ${n.toLocaleString('id-ID')}`;
};

const utilizationColor = (pct: number) => {
    if (pct >= 90) return '#EF4444';
    if (pct >= 70) return '#F59E0B';
    return '#10B981';
};

const createForm = useForm({
    name:                '',
    description:         '',
    objectives:          '',
    planned_start_date:  '',
    planned_end_date:    '',
    budget:              null as number | null,
    team_id:             '',
    manager_id:          '',
    data_classification: 2,
});

const submitCreate = () => {
    createForm
        .transform((d) => ({
            ...d,
            team_id: d.team_id || null,
            manager_id: d.manager_id || null,
        }))
        .post('/projects', {
            onSuccess: () => {
                showCreateSlide.value = false;
                createForm.reset();
            },
        });
};

const classificationOptions = [
    { value: 1, label: 'Publik' },
    { value: 2, label: 'Internal' },
    { value: 3, label: 'Rahasia' },
    { value: 4, label: 'Sangat Rahasia' },
];

const activeCount = computed(() => props.projects.filter(p => statusBadge(p.status).label === 'Aktif').length);
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Proyek</span>
        </template>

        <div class="space-y-4 max-w-[1200px]">

            <!-- Page header -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Proyek</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">
                        {{ projects.length }} proyek · {{ activeCount }} aktif
                    </p>
                </div>
                <button
                    v-if="can.create"
                    @click="showCreateSlide = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Buat Proyek
                </button>
            </div>

            <!-- Filter bar -->
            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari proyek..."
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <select
                    v-model="filterStatus"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg text-sm border outline-none"
                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Status</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusBadge(s).label }}</option>
                </select>
            </div>

            <!-- Cards grid -->
            <div v-if="projects.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="p in projects"
                    :key="p.id"
                    :href="`/projects/${p.id}`"
                    class="block rounded-xl p-4 hover:opacity-90 transition-opacity"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1);">
                            <FolderKanban :size="20" style="color: #3B82F6;" />
                        </div>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :style="`background: ${statusBadge(p.status).bg}; color: ${statusBadge(p.status).text};`"
                        >{{ statusBadge(p.status).label }}</span>
                    </div>

                    <p class="text-sm font-semibold leading-snug mb-2 line-clamp-2" style="color: var(--text-primary);">
                        {{ p.name }}
                    </p>

                    <!-- Progress bar -->
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-[11px] mb-1" style="color: var(--text-muted);">
                            <span>Progress</span>
                            <span style="color: var(--text-secondary);">{{ p.progress_percent }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--bg-tertiary);">
                            <div class="h-full rounded-full" :style="`width: ${p.progress_percent}%; background: #3B82F6;`" />
                        </div>
                    </div>

                    <!-- Budget utilization -->
                    <div v-if="p.budget" class="flex items-center gap-1.5 text-[11px] mb-2" style="color: var(--text-muted);">
                        <Wallet :size="13" />
                        <span>{{ formatRupiah(p.budget) }}</span>
                        <span class="ml-auto font-medium" :style="`color: ${utilizationColor(p.budget_utilization)};`">
                            {{ p.budget_utilization }}% terpakai
                        </span>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between text-[11px] pt-2 border-t" style="color: var(--text-muted); border-color: var(--border-color);">
                        <span class="flex items-center gap-1">
                            <CalendarDays :size="12" /> {{ formatDate(p.planned_end_date) }}
                        </span>
                        <span v-if="p.owner" class="flex items-center gap-1 truncate">
                            <Users :size="12" /> {{ p.owner.name }}
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Empty state (DESIGN.md §5.12) -->
            <div
                v-else
                class="rounded-xl py-16 text-center"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <FolderKanban :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Belum ada proyek</p>
                <p class="text-sm mb-4" style="color: var(--text-muted);">Mulai kelola proyek tim Anda</p>
                <button
                    v-if="can.create"
                    @click="showCreateSlide = true"
                    class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >Buat Proyek</button>
            </div>
        </div>

        <!-- Create Slide-over -->
        <Teleport to="body">
            <div v-if="showCreateSlide" class="fixed inset-0 z-50 flex justify-end">
                <div class="absolute inset-0" style="background: rgba(0,0,0,0.4);" @click="showCreateSlide = false" />

                <div
                    class="relative z-10 w-[480px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Buat Proyek</h2>
                        <button @click="showCreateSlide = false" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Nama Proyek <span style="color: #EF4444;">*</span>
                            </label>
                            <input
                                v-model="createForm.name"
                                type="text"
                                placeholder="Contoh: Digitalisasi Layanan Perizinan"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                            <p v-if="createForm.errors.name" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Deskripsi</label>
                            <textarea
                                v-model="createForm.description"
                                rows="2"
                                placeholder="Ringkasan singkat proyek..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Sasaran (Objectives)</label>
                            <textarea
                                v-model="createForm.objectives"
                                rows="2"
                                placeholder="Apa yang ingin dicapai..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Mulai</label>
                                <input
                                    v-model="createForm.planned_start_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Selesai</label>
                                <input
                                    v-model="createForm.planned_end_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <p v-if="createForm.errors.planned_end_date" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.planned_end_date }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Anggaran (Rp)</label>
                            <input
                                v-model.number="createForm.budget"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Tim</label>
                                <select
                                    v-model="createForm.team_id"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option value="">—</option>
                                    <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Manajer</label>
                                <select
                                    v-model="createForm.manager_id"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option value="">—</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Klasifikasi Data</label>
                            <select
                                v-model.number="createForm.data_classification"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option v-for="c in classificationOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="createForm.processing ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : 'Buat Proyek' }}
                            </button>
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
