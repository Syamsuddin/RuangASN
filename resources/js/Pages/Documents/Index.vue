<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, FileText, File, Search, X, Grid, List,
    FileSpreadsheet, FileImage, ChevronRight, FolderOpen,
    Upload,
} from 'lucide-vue-next';

interface DocumentCard {
    id: string;
    title: string;
    document_type: string | { value: string };
    status: string | { value: string };
    document_number?: string;
    data_classification: number;
    classification_label: string;
    version_number: number;
    is_latest: boolean;
    file_name?: string;
    mime_type?: string;
    updated_at?: string;
    owner?: { id: string; name: string };
}

interface Props {
    documents: DocumentCard[];
    filters: { type?: string; status?: string; classification?: string; search?: string };
    types: string[];
    statuses: string[];
}

const props = defineProps<Props>();

const showCreateSlide = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');

const filterType           = ref(props.filters.type ?? '');
const filterStatus         = ref(props.filters.status ?? '');
const filterClassification = ref(props.filters.classification ?? '');
const searchQuery          = ref(props.filters.search ?? '');

const applyFilters = () => {
    router.get('/documents', {
        type:           filterType.value || undefined,
        status:         filterStatus.value || undefined,
        classification: filterClassification.value || undefined,
        search:         searchQuery.value || undefined,
    }, { preserveState: true, replace: true });
};

// Classification badge per DESIGN.md §5.4
const classificationBadge = (level: number) => {
    const map: Record<number, { bg: string; text: string; border?: string }> = {
        1: { bg: 'rgba(16,185,129,0.15)',  text: '#34D399' },
        2: { bg: 'rgba(59,130,246,0.15)',  text: '#60A5FA' },
        3: { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D' },
        4: { bg: 'rgba(239,68,68,0.15)',   text: '#F87171', border: 'rgba(239,68,68,0.3)' },
    };
    return map[level] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
};

const classificationStyle = (level: number) => {
    const cfg = classificationBadge(level);
    const border = cfg.border ? `border: 1px solid ${cfg.border};` : '';
    return `background: ${cfg.bg}; color: ${cfg.text}; ${border}`;
};

// Status badge
const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:      { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    in_review:  { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Review' },
    approved:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Disetujui' },
    published:  { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Published' },
    rejected:   { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Ditolak' },
    archived:   { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan' },
    expired:    { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Kedaluwarsa' },
    superseded: { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Superseded' },
};

const statusBadge = (s: string | { value: string }) => {
    const key = typeof s === 'string' ? s : s.value;
    return statusConfig[key] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: key };
};

const typeLabels: Record<string, string> = {
    letter: 'Surat', regulation: 'Peraturan', sop: 'SOP', report: 'Laporan',
    minutes: 'Notulensi', decision: 'Keputusan', memo: 'Memo', template: 'Template',
    reference: 'Referensi', contract: 'Kontrak', project_doc: 'Dok. Proyek',
    performance_doc: 'Dok. Kinerja',
};

const typeLabel = (t: string | { value: string }) => {
    const key = typeof t === 'string' ? t : t.value;
    return typeLabels[key] ?? key;
};

const fileIcon = (mime?: string) => {
    if (!mime) return FileText;
    if (mime.includes('pdf')) return File;
    if (mime.includes('spreadsheet') || mime.includes('excel')) return FileSpreadsheet;
    if (mime.includes('image')) return FileImage;
    return FileText;
};

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

// Create form
const createForm = useForm({
    title:               '',
    description:         '',
    document_type:       'letter',
    data_classification: 2,
    document_number:     '',
    document_date:       '',
    effective_date:      '',
    expiry_date:         '',
    file:                null as File | null,
    tags:                [] as string[],
});

const fileInput = ref<HTMLInputElement>();
const dragOver  = ref(false);

const handleFileDrop = (e: DragEvent) => {
    dragOver.value = false;
    const file = e.dataTransfer?.files[0];
    if (file) createForm.file = file;
};

const handleFileSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) createForm.file = file;
};

const submitCreate = () => {
    createForm.post('/documents', {
        forceFormData: true,
        onSuccess: () => {
            showCreateSlide.value = false;
            createForm.reset();
        },
    });
};

const documentTypes = [
    { value: 'letter',          label: 'Surat' },
    { value: 'regulation',      label: 'Peraturan' },
    { value: 'sop',             label: 'SOP' },
    { value: 'report',          label: 'Laporan' },
    { value: 'minutes',         label: 'Notulensi' },
    { value: 'decision',        label: 'Keputusan' },
    { value: 'memo',            label: 'Memo' },
    { value: 'template',        label: 'Template' },
    { value: 'reference',       label: 'Referensi' },
    { value: 'contract',        label: 'Kontrak' },
    { value: 'project_doc',     label: 'Dok. Proyek' },
    { value: 'performance_doc', label: 'Dok. Kinerja' },
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
            <span style="color: var(--text-muted);">Dokumen</span>
        </template>

        <div class="space-y-4 max-w-[1200px]">

            <!-- Page header -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Dokumen</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ documents.length }} dokumen</p>
                </div>
                <button
                    @click="showCreateSlide = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Upload Dokumen
                </button>
            </div>

            <!-- Filter bar -->
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Search -->
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari dokumen..."
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <!-- Type filter -->
                <select
                    v-model="filterType"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg text-sm border outline-none"
                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Tipe</option>
                    <option v-for="t in documentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <!-- Status filter -->
                <select
                    v-model="filterStatus"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg text-sm border outline-none"
                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Status</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusBadge(s).label }}</option>
                </select>
                <!-- Classification filter -->
                <select
                    v-model="filterClassification"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg text-sm border outline-none"
                    style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Klasifikasi</option>
                    <option v-for="c in classificationOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <!-- View toggle -->
                <div class="flex gap-1 p-1 rounded-lg ml-auto" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                    <button
                        @click="viewMode = 'grid'"
                        class="p-1.5 rounded-md transition-colors"
                        :style="viewMode === 'grid' ? 'background: #3B82F6; color: white;' : 'color: var(--text-muted);'"
                    ><Grid :size="14" /></button>
                    <button
                        @click="viewMode = 'list'"
                        class="p-1.5 rounded-md transition-colors"
                        :style="viewMode === 'list' ? 'background: #3B82F6; color: white;' : 'color: var(--text-muted);'"
                    ><List :size="14" /></button>
                </div>
            </div>

            <!-- GRID VIEW -->
            <div v-if="documents.length > 0 && viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="d in documents"
                    :key="d.id"
                    :href="`/documents/${d.id}`"
                    class="block rounded-xl p-4 hover:opacity-90 transition-opacity"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <!-- File icon + classification badge -->
                    <div class="flex items-start justify-between mb-3">
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center"
                            style="background: rgba(59,130,246,0.1);"
                        >
                            <component :is="fileIcon(d.mime_type)" :size="20" style="color: #3B82F6;" />
                        </div>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :style="classificationStyle(d.data_classification)"
                        >{{ d.classification_label }}</span>
                    </div>

                    <!-- Title -->
                    <p class="text-sm font-semibold leading-snug mb-1 line-clamp-2" style="color: var(--text-primary);">
                        {{ d.title }}
                    </p>

                    <!-- Document number -->
                    <p v-if="d.document_number" class="text-xs mb-2" style="color: var(--text-muted);">
                        {{ d.document_number }}
                    </p>

                    <!-- Type + status badges -->
                    <div class="flex items-center gap-1.5 flex-wrap mb-3">
                        <span
                            class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                            style="background: var(--bg-tertiary); color: var(--text-muted);"
                        >{{ typeLabel(d.document_type) }}</span>
                        <span
                            class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                            :style="`background: ${statusBadge(d.status).bg}; color: ${statusBadge(d.status).text};`"
                        >{{ statusBadge(d.status).label }}</span>
                    </div>

                    <!-- Footer: updated date + owner -->
                    <div class="flex items-center justify-between text-[11px]" style="color: var(--text-muted);">
                        <span>{{ formatDate(d.updated_at) }}</span>
                        <span v-if="d.owner">{{ d.owner.name }}</span>
                    </div>
                </Link>
            </div>

            <!-- LIST VIEW -->
            <div
                v-else-if="documents.length > 0 && viewMode === 'list'"
                class="rounded-xl overflow-hidden"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Nama Dokumen</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Tipe</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Klasifikasi</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Status</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Diperbarui</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in documents"
                            :key="d.id"
                            class="border-b hover:opacity-80 transition-opacity"
                            style="border-color: var(--border-color);"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <component :is="fileIcon(d.mime_type)" :size="15" style="color: #3B82F6; shrink-0" />
                                    <div>
                                        <p class="font-medium text-sm" style="color: var(--text-primary);">{{ d.title }}</p>
                                        <p v-if="d.document_number" class="text-xs" style="color: var(--text-muted);">{{ d.document_number }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">{{ typeLabel(d.document_type) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                    :style="classificationStyle(d.data_classification)"
                                >{{ d.classification_label }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                    :style="`background: ${statusBadge(d.status).bg}; color: ${statusBadge(d.status).text};`"
                                >{{ statusBadge(d.status).label }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">{{ formatDate(d.updated_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/documents/${d.id}`"
                                    class="flex items-center gap-1 text-xs font-medium hover:opacity-80"
                                    style="color: #3B82F6;"
                                >Detail <ChevronRight :size="12" /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state (DESIGN.md §5.12) -->
            <div
                v-if="documents.length === 0"
                class="rounded-xl py-16 text-center"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <FolderOpen :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Belum ada dokumen</p>
                <p class="text-sm mb-4" style="color: var(--text-muted);">Upload dokumen pertama Anda</p>
                <button
                    @click="showCreateSlide = true"
                    class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >Upload Dokumen</button>
            </div>

        </div>

        <!-- Create Document Slide-over -->
        <Teleport to="body">
            <div v-if="showCreateSlide" class="fixed inset-0 z-50 flex justify-end">
                <div class="absolute inset-0" style="background: rgba(0,0,0,0.4);" @click="showCreateSlide = false" />

                <div
                    class="relative z-10 w-[480px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Upload Dokumen</h2>
                        <button @click="showCreateSlide = false" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">

                        <!-- Title -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Judul Dokumen <span style="color: #EF4444;">*</span>
                            </label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                placeholder="Contoh: Surat Perintah Tugas..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                            <p v-if="createForm.errors.title" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.title }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Deskripsi</label>
                            <textarea
                                v-model="createForm.description"
                                rows="2"
                                placeholder="Ringkasan singkat dokumen..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Type + Classification (2 columns) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Tipe <span style="color: #EF4444;">*</span>
                                </label>
                                <select
                                    v-model="createForm.document_type"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option v-for="t in documentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                                <p v-if="createForm.errors.document_type" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.document_type }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Klasifikasi <span style="color: #EF4444;">*</span>
                                </label>
                                <select
                                    v-model.number="createForm.data_classification"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option v-for="c in classificationOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                                </select>
                                <p v-if="createForm.errors.data_classification" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.data_classification }}</p>
                            </div>
                        </div>

                        <!-- Document number + Date -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Nomor Dokumen</label>
                                <input
                                    v-model="createForm.document_number"
                                    type="text"
                                    placeholder="e.g. 001/OPD/2026"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Tanggal Dokumen</label>
                                <input
                                    v-model="createForm.document_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                        </div>

                        <!-- Effective + Expiry dates -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Berlaku Mulai</label>
                                <input
                                    v-model="createForm.effective_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Berlaku Hingga</label>
                                <input
                                    v-model="createForm.expiry_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                        </div>

                        <!-- File drag-drop zone -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">File Dokumen</label>
                            <div
                                class="relative rounded-lg border-2 border-dashed p-6 text-center transition-colors cursor-pointer"
                                :style="dragOver
                                    ? 'border-color: #3B82F6; background: rgba(59,130,246,0.05);'
                                    : 'border-color: var(--border-color); background: var(--bg-tertiary);'"
                                @dragover.prevent="dragOver = true"
                                @dragleave="dragOver = false"
                                @drop.prevent="handleFileDrop"
                                @click="fileInput?.click()"
                            >
                                <input
                                    ref="fileInput"
                                    type="file"
                                    class="hidden"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                                    @change="handleFileSelect"
                                />
                                <Upload :size="24" class="mx-auto mb-2" style="color: var(--text-muted);" />
                                <p v-if="createForm.file" class="text-sm font-medium" style="color: #3B82F6;">
                                    {{ (createForm.file as File).name }}
                                </p>
                                <p v-else class="text-sm" style="color: var(--text-muted);">
                                    Drag & drop atau klik untuk pilih file<br>
                                    <span class="text-xs">PDF, Word, Excel, PPT, Gambar — max 20MB</span>
                                </p>
                            </div>
                            <p v-if="createForm.errors.file" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.file }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="createForm.processing ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >
                                {{ createForm.processing ? 'Mengupload...' : 'Simpan Dokumen' }}
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
