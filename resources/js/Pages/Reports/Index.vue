<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, Search, X, BarChart2, FolderOpen, ChevronRight,
} from 'lucide-vue-next';

interface ReportCard {
    id: string;
    title: string;
    report_type: string | { value: string };
    period_type: string | { value: string };
    status: string | { value: string };
    period_start_date?: string;
    period_end_date?: string;
    data_classification: number;
    classification_label: string;
    updated_at?: string;
    author?: { id: string; name: string };
}

interface Props {
    reports: ReportCard[];
    filters: { type?: string; status?: string; search?: string };
    types: string[];
    statuses: string[];
}

const props = defineProps<Props>();

const showCreateSlide = ref(false);

const filterType   = ref(props.filters.type ?? '');
const filterStatus = ref(props.filters.status ?? '');
const searchQuery  = ref(props.filters.search ?? '');

const applyFilters = () => {
    router.get('/reports', {
        type:   filterType.value || undefined,
        status: filterStatus.value || undefined,
        search: searchQuery.value || undefined,
    }, { preserveState: true, replace: true });
};

// Status badge config (DESIGN.md §6.13)
const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:      { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    submitted:  { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Diajukan' },
    in_review:  { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Direview' },
    revision:   { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Perlu Revisi' },
    approved:   { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Disetujui' },
    published:  { bg: 'rgba(16,185,129,0.3)',  text: '#10B981', label: 'Dipublikasikan' },
    archived:   { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan' },
    rejected:   { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Ditolak' },
};

const statusBadge = (s: string | { value: string }) => {
    const key = typeof s === 'string' ? s : s.value;
    return statusConfig[key] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: key };
};

const typeLabels: Record<string, string> = {
    daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan',
    quarterly: 'Triwulan', annual: 'Tahunan', activity: 'Kegiatan',
    project: 'Proyek', performance: 'Kinerja', financial: 'Keuangan', special: 'Khusus',
};

const typeLabel = (t: string | { value: string }) => {
    const key = typeof t === 'string' ? t : t.value;
    return typeLabels[key] ?? key;
};

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatPeriod = (start?: string, end?: string) => {
    if (!start) return '—';
    const s = formatDate(start);
    if (!end || end === start) return s;
    return `${s} – ${formatDate(end)}`;
};

// Create form
const createForm = useForm({
    title:               '',
    report_type:         'activity',
    period_type:         'monthly',
    period_start_date:   '',
    period_end_date:     '',
    data_classification: 2,
    content:             '',
    data_sources:        [] as string[],
});

const submitCreate = () => {
    createForm.post('/reports', {
        onSuccess: () => {
            showCreateSlide.value = false;
            createForm.reset();
        },
    });
};

const reportTypes = [
    { value: 'activity',    label: 'Kegiatan' },
    { value: 'daily',       label: 'Harian' },
    { value: 'weekly',      label: 'Mingguan' },
    { value: 'monthly',     label: 'Bulanan' },
    { value: 'quarterly',   label: 'Triwulan' },
    { value: 'annual',      label: 'Tahunan' },
    { value: 'project',     label: 'Proyek' },
    { value: 'performance', label: 'Kinerja' },
    { value: 'financial',   label: 'Keuangan' },
    { value: 'special',     label: 'Khusus' },
];

const periodTypes = [
    { value: 'monthly',  label: 'Bulanan' },
    { value: 'daily',    label: 'Harian' },
    { value: 'weekly',   label: 'Mingguan' },
    { value: 'semester', label: 'Semester' },
    { value: 'annual',   label: 'Tahunan' },
    { value: 'custom',   label: 'Kustom' },
];

const classificationOptions = [
    { value: 1, label: 'Publik' },
    { value: 2, label: 'Internal' },
    { value: 3, label: 'Rahasia' },
    { value: 4, label: 'Sangat Rahasia' },
];
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Laporan</span>
        </template>

        <div class="space-y-4 max-w-[1200px]">

            <!-- Page header -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Laporan</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ reports.length }} laporan</p>
                </div>
                <button
                    @click="showCreateSlide = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Buat Laporan
                </button>
            </div>

            <!-- Filter bar -->
            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari laporan..."
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <select
                    v-model="filterType"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg text-sm border outline-none"
                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Tipe</option>
                    <option v-for="t in reportTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
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
            <div v-if="reports.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="r in reports"
                    :key="r.id"
                    :href="`/reports/${r.id}`"
                    class="block rounded-xl p-4 hover:opacity-90 transition-opacity"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <!-- Icon + status badge -->
                    <div class="flex items-start justify-between mb-3">
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center"
                            style="background: rgba(59,130,246,0.1);"
                        >
                            <BarChart2 :size="20" style="color: #3B82F6;" />
                        </div>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :style="`background: ${statusBadge(r.status).bg}; color: ${statusBadge(r.status).text};`"
                        >{{ statusBadge(r.status).label }}</span>
                    </div>

                    <!-- Title -->
                    <p class="text-sm font-semibold leading-snug mb-1 line-clamp-2" style="color: var(--text-primary);">
                        {{ r.title }}
                    </p>

                    <!-- Period -->
                    <p class="text-xs mb-2" style="color: var(--text-muted);">
                        {{ formatPeriod(r.period_start_date, r.period_end_date) }}
                    </p>

                    <!-- Type badge -->
                    <div class="flex items-center gap-1.5 flex-wrap mb-3">
                        <span
                            class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                            style="background: var(--bg-tertiary); color: var(--text-muted);"
                        >{{ typeLabel(r.report_type) }}</span>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between text-[11px]" style="color: var(--text-muted);">
                        <span>{{ formatDate(r.updated_at) }}</span>
                        <span v-if="r.author">{{ r.author.name }}</span>
                    </div>
                </Link>
            </div>

            <!-- Empty state (DESIGN.md §5.12) -->
            <div
                v-if="reports.length === 0"
                class="rounded-xl py-16 text-center"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <FolderOpen :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Belum ada laporan</p>
                <p class="text-sm mb-4" style="color: var(--text-muted);">Buat laporan pertama Anda</p>
                <button
                    @click="showCreateSlide = true"
                    class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >Buat Laporan</button>
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
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Buat Laporan</h2>
                        <button @click="showCreateSlide = false" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">

                        <!-- Title -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Judul Laporan <span style="color: #EF4444;">*</span>
                            </label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                placeholder="Contoh: Laporan Kegiatan Bulanan..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                            <p v-if="createForm.errors.title" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.title }}</p>
                        </div>

                        <!-- Type + Period Type -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Tipe Laporan <span style="color: #EF4444;">*</span>
                                </label>
                                <select
                                    v-model="createForm.report_type"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option v-for="t in reportTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Periode <span style="color: #EF4444;">*</span>
                                </label>
                                <select
                                    v-model="createForm.period_type"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option v-for="p in periodTypes" :key="p.value" :value="p.value">{{ p.label }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Period dates -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Tanggal Mulai <span style="color: #EF4444;">*</span>
                                </label>
                                <input
                                    v-model="createForm.period_start_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <p v-if="createForm.errors.period_start_date" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.period_start_date }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Tanggal Selesai <span style="color: #EF4444;">*</span>
                                </label>
                                <input
                                    v-model="createForm.period_end_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <p v-if="createForm.errors.period_end_date" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.period_end_date }}</p>
                            </div>
                        </div>

                        <!-- Classification -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Klasifikasi Data <span style="color: #EF4444;">*</span>
                            </label>
                            <select
                                v-model.number="createForm.data_classification"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option v-for="c in classificationOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>

                        <!-- Content -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Konten</label>
                            <textarea
                                v-model="createForm.content"
                                rows="4"
                                placeholder="Isi laporan..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="createForm.processing ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : 'Buat Laporan' }}
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
