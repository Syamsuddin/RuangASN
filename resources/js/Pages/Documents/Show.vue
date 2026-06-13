<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, FileText, Download, Plus, CheckCircle2,
    XCircle, Clock, User, Building2, File, AlertCircle,
} from 'lucide-vue-next';

interface Approver {
    id: string;
    name: string;
}

interface Approval {
    id: string;
    step_number: number;
    status: string;
    notes?: string;
    decided_at?: string;
    approver?: Approver;
}

interface DocumentVersion {
    id: string;
    version_number: number;
    status: string | { value: string };
    is_latest: boolean;
    created_at?: string;
}

interface Document {
    id: string;
    title: string;
    description?: string;
    document_type: string | { value: string };
    status: string | { value: string };
    document_number?: string;
    document_date?: string;
    effective_date?: string;
    expiry_date?: string;
    data_classification: number;
    classification_label: string;
    version_number: number;
    is_latest: boolean;
    file_name?: string;
    file_size?: number;
    mime_type?: string;
    ai_summary?: string;
    ai_tags?: string[];
    tags?: string[];
    owner?: { id: string; name: string };
    creator?: { id: string; name: string };
    approvals?: Approval[];
    versions?: DocumentVersion[];
    versions_count?: number;
    meeting?: { id: string; title: string };
    task?: { id: string; title: string };
    updated_at?: string;
    created_at?: string;
}

interface Props {
    document: Document;
    stream_url?: string;
    download_url?: string;
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        publish: boolean;
        createVersion: boolean;
        download: boolean;
    };
}

const props = defineProps<Props>();

const activeTab = ref<'preview' | 'versions' | 'approvals' | 'terkait'>('preview');

const tabs = [
    { key: 'preview' as const,   label: 'Preview' },
    { key: 'versions' as const,  label: 'Versi' },
    { key: 'approvals' as const, label: 'Persetujuan' },
    { key: 'terkait' as const,   label: 'Terkait' },
];

// Helpers
const statusVal = (s: string | { value: string }) => typeof s === 'string' ? s : s.value;
const typeVal   = (t: string | { value: string }) => typeof t === 'string' ? t : t.value;

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

const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:      { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    in_review:  { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Dalam Review' },
    approved:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Disetujui' },
    published:  { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Published' },
    rejected:   { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Ditolak' },
    archived:   { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan' },
    expired:    { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Kedaluwarsa' },
    superseded: { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Digantikan' },
};

const statusBadge = (s: string | { value: string }) => {
    const key = statusVal(s);
    return statusConfig[key] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: key };
};

const typeLabels: Record<string, string> = {
    letter: 'Surat', regulation: 'Peraturan', sop: 'SOP', report: 'Laporan',
    minutes: 'Notulensi', decision: 'Keputusan', memo: 'Memo', template: 'Template',
    reference: 'Referensi', contract: 'Kontrak', project_doc: 'Dok. Proyek',
    performance_doc: 'Dok. Kinerja',
};

const typeLabel = (t: string | { value: string }) => typeLabels[typeVal(t)] ?? typeVal(t);

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const formatFileSize = (bytes?: number) => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const approvalStatusConfig: Record<string, { icon: typeof CheckCircle2; color: string; label: string }> = {
    pending:  { icon: Clock,          color: '#F59E0B', label: 'Menunggu' },
    approved: { icon: CheckCircle2,   color: '#10B981', label: 'Disetujui' },
    rejected: { icon: XCircle,        color: '#EF4444', label: 'Ditolak' },
};

// Submit form
const submitForm = useForm({ approver_ids: [] as string[] });
const showSubmitPanel = ref(false);
const approverInput = ref('');

const addApprover = () => {
    if (approverInput.value && !submitForm.approver_ids.includes(approverInput.value)) {
        submitForm.approver_ids.push(approverInput.value);
        approverInput.value = '';
    }
};

const removeApprover = (id: string) => {
    submitForm.approver_ids = submitForm.approver_ids.filter(a => a !== id);
};

const doSubmit = () => {
    submitForm.post(`/documents/${props.document.id}/submit`, { preserveScroll: true });
};

// Approve / reject forms
const approveForm  = useForm({ notes: '' });
const rejectForm   = useForm({ reason: '' });
const activeReject = ref<string | null>(null);

const doApprove = (approvalId: string) => {
    approveForm.post(`/documents/approvals/${approvalId}/approve`, { preserveScroll: true });
};

const doReject = (approvalId: string) => {
    rejectForm.post(`/documents/approvals/${approvalId}/reject`, { preserveScroll: true });
};

// Publish / archive
const doPublish = () => {
    router.post(`/documents/${props.document.id}/publish`, {}, { preserveScroll: true });
};

const doArchive = () => {
    if (confirm('Arsipkan dokumen ini?')) {
        router.post(`/documents/${props.document.id}/archive`, {}, { preserveScroll: true });
    }
};

// New version form
const newVersionForm = useForm({ title: props.document.title, description: '', file: null as File | null });
const showVersionPanel = ref(false);
const newVersionFileInput = ref<HTMLInputElement>();

const handleVersionFile = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) newVersionForm.file = file;
};

const doCreateVersion = () => {
    newVersionForm.post(`/documents/${props.document.id}/versions`, {
        forceFormData: true,
        onSuccess: () => { showVersionPanel.value = false; },
    });
};

const currentUserId = () => {
    // We rely on server-side can.approve; checking approvals for pending step
    return props.document.approvals?.find(a => a.status === 'pending');
};

// Viewer helpers
const showWatermarkOverlay = computed(() =>
    props.document.data_classification >= 3
);

const fileViewerType = computed(() => {
    const mime = props.document.mime_type ?? '';
    if (!props.document.file_name) return 'none';
    if (mime.includes('pdf')) return 'pdf';
    if (mime.startsWith('image/')) return 'image';
    return 'other';
});
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/documents" style="color: var(--text-muted);" class="hover:opacity-80">Dokumen</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium truncate max-w-[200px]">{{ document.title }}</span>
        </template>

        <div class="max-w-[1200px] space-y-4">

            <!-- Header card -->
            <div class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                <!-- Back + actions -->
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <Link href="/documents" class="flex items-center gap-1.5 text-sm font-medium hover:opacity-80" style="color: #3B82F6;">
                        <ArrowLeft :size="16" /> Kembali ke Dokumen
                    </Link>
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Download (signed URL generated server-side) -->
                        <a
                            v-if="can.download && document.file_name && download_url"
                            :href="download_url"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                            style="background: #8B5CF6;"
                        >
                            <Download :size="14" /> Unduh
                        </a>
                        <!-- Submit for review -->
                        <button
                            v-if="can.submit && statusVal(document.status) === 'draft'"
                            @click="showSubmitPanel = !showSubmitPanel"
                            class="px-3 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                            style="background: #F59E0B;"
                        >Submit Review</button>
                        <!-- Publish -->
                        <button
                            v-if="can.publish"
                            @click="doPublish"
                            class="px-3 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                            style="background: #10B981;"
                        >Publish</button>
                        <!-- New Version -->
                        <button
                            v-if="can.createVersion"
                            @click="showVersionPanel = !showVersionPanel"
                            class="px-3 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                            style="border-color: var(--border-color); color: var(--text-secondary);"
                        >Buat Versi Baru</button>
                        <!-- Archive -->
                        <button
                            v-if="can.update && !['archived','draft'].includes(statusVal(document.status))"
                            @click="doArchive"
                            class="px-3 py-2 rounded-lg text-sm border hover:opacity-80"
                            style="border-color: var(--border-color); color: #94A3B8;"
                        >Arsipkan</button>
                    </div>
                </div>

                <!-- Classification + status badges -->
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <span
                        class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        :style="classificationStyle(document.data_classification)"
                    >{{ document.classification_label }}</span>
                    <span
                        class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        :style="`background: ${statusBadge(document.status).bg}; color: ${statusBadge(document.status).text};`"
                    >{{ statusBadge(document.status).label }}</span>
                    <span v-if="document.is_latest" class="text-[10px] font-bold px-2 py-1 rounded-full" style="background: rgba(16,185,129,0.2); color: #10B981;">
                        v{{ document.version_number }} (Terbaru)
                    </span>
                </div>

                <h1 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">{{ document.title }}</h1>

                <!-- Meta row -->
                <div class="flex items-center gap-5 flex-wrap text-sm" style="color: var(--text-secondary);">
                    <div class="flex items-center gap-1.5">
                        <File :size="14" style="color: var(--text-muted);" />
                        <span>{{ typeLabel(document.document_type) }}</span>
                    </div>
                    <div v-if="document.document_number" class="flex items-center gap-1.5">
                        <FileText :size="14" style="color: var(--text-muted);" />
                        <span>{{ document.document_number }}</span>
                    </div>
                    <div v-if="document.owner" class="flex items-center gap-1.5">
                        <User :size="14" style="color: var(--text-muted);" />
                        <span>{{ document.owner.name }}</span>
                    </div>
                    <div v-if="document.document_date" class="flex items-center gap-1.5">
                        <Clock :size="14" style="color: var(--text-muted);" />
                        <span>{{ formatDate(document.document_date) }}</span>
                    </div>
                    <div v-if="document.expiry_date" class="flex items-center gap-1.5">
                        <AlertCircle :size="14" style="color: var(--text-muted);" />
                        <span>Berlaku s.d. {{ formatDate(document.expiry_date) }}</span>
                    </div>
                </div>

                <!-- Submit panel -->
                <div v-if="showSubmitPanel" class="mt-4 p-4 rounded-lg space-y-3" style="background: var(--bg-tertiary);">
                    <p class="text-xs font-semibold" style="color: var(--text-muted);">Tambah Approver (masukkan User ID)</p>
                    <div class="flex gap-2">
                        <input
                            v-model="approverInput"
                            type="text"
                            placeholder="User ID approver..."
                            class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                            @keyup.enter="addApprover"
                        />
                        <button @click="addApprover" class="px-3 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                            <Plus :size="14" />
                        </button>
                    </div>
                    <div v-if="submitForm.approver_ids.length" class="space-y-1">
                        <div
                            v-for="aid in submitForm.approver_ids"
                            :key="aid"
                            class="flex items-center justify-between px-3 py-1.5 rounded-lg"
                            style="background: var(--card-bg);"
                        >
                            <span class="text-xs" style="color: var(--text-secondary);">{{ aid }}</span>
                            <button @click="removeApprover(aid)" style="color: #EF4444;"><XCircle :size="14" /></button>
                        </div>
                    </div>
                    <button
                        @click="doSubmit"
                        :disabled="submitForm.processing || submitForm.approver_ids.length === 0"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        :style="submitForm.approver_ids.length === 0 ? 'background: #F59E0B; opacity:0.5;' : 'background: #F59E0B;'"
                    >{{ submitForm.processing ? 'Submitting...' : 'Submit untuk Review' }}</button>
                </div>

                <!-- New version panel -->
                <div v-if="showVersionPanel" class="mt-4 p-4 rounded-lg space-y-3" style="background: var(--bg-tertiary);">
                    <p class="text-xs font-semibold" style="color: var(--text-muted);">Buat Versi Baru (v{{ document.version_number + 1 }})</p>
                    <input
                        v-model="newVersionForm.title"
                        type="text"
                        class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                        placeholder="Judul versi baru..."
                    />
                    <input
                        ref="newVersionFileInput"
                        type="file"
                        class="w-full text-sm"
                        style="color: var(--text-secondary);"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        @change="handleVersionFile"
                    />
                    <button
                        @click="doCreateVersion"
                        :disabled="newVersionForm.processing"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background: #3B82F6;"
                    >{{ newVersionForm.processing ? 'Membuat...' : 'Buat Versi Baru' }}</button>
                </div>
            </div>

            <!-- Main grid: tabs + sidebar -->
            <div class="grid lg:grid-cols-[1fr_280px] gap-4 items-start">

                <!-- Tabs -->
                <div class="rounded-xl overflow-hidden" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                    <!-- Tab bar -->
                    <div class="flex border-b overflow-x-auto" style="border-color: var(--border-color);">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap shrink-0"
                            :style="activeTab === tab.key
                                ? 'border-color: #3B82F6; color: #3B82F6;'
                                : 'border-color: transparent; color: var(--text-muted);'"
                        >{{ tab.label }}</button>
                    </div>

                    <div class="p-5">

                        <!-- Preview tab -->
                        <div v-if="activeTab === 'preview'">
                            <!-- Description -->
                            <div
                                v-if="document.description"
                                class="rounded-lg p-4 text-sm leading-relaxed whitespace-pre-wrap mb-4"
                                style="background: var(--bg-tertiary); color: var(--text-secondary);"
                            >{{ document.description }}</div>

                            <!-- File metadata row -->
                            <div v-if="document.file_name" class="rounded-lg p-4 mb-4" style="background: var(--bg-tertiary);">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1);">
                                            <FileText :size="18" style="color: #3B82F6;" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium" style="color: var(--text-primary);">{{ document.file_name }}</p>
                                            <p class="text-xs" style="color: var(--text-muted);">
                                                {{ formatFileSize(document.file_size) }}
                                                <span v-if="document.mime_type"> • {{ document.mime_type }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <a
                                        v-if="can.download && download_url"
                                        :href="download_url"
                                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-white"
                                        style="background: #8B5CF6;"
                                    ><Download :size="13" /> Unduh</a>
                                </div>
                            </div>

                            <!-- In-app viewer -->
                            <div v-if="can.download && stream_url && fileViewerType !== 'none'" class="mb-4">
                                <!-- Watermark notice for CONFIDENTIAL / RESTRICTED -->
                                <div
                                    v-if="showWatermarkOverlay"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg mb-2 text-xs font-medium"
                                    style="background: rgba(245,158,11,0.12); color: #F59E0B; border: 1px solid rgba(245,158,11,0.25);"
                                >
                                    <AlertCircle :size="13" />
                                    Dokumen ini bersifat rahasia. Watermark identitas Anda ditampilkan dalam pratinjau.
                                </div>

                                <!-- PDF viewer -->
                                <div v-if="fileViewerType === 'pdf'" class="relative rounded-lg overflow-hidden" style="height: 600px;">
                                    <iframe
                                        :src="stream_url"
                                        class="w-full h-full border-0"
                                        title="Pratinjau Dokumen"
                                    />
                                    <!-- Watermark overlay for CONFIDENTIAL/RESTRICTED -->
                                    <div
                                        v-if="showWatermarkOverlay"
                                        class="absolute inset-0 pointer-events-none overflow-hidden select-none"
                                        aria-hidden="true"
                                    >
                                        <!-- Diagonal watermark pattern using CSS -->
                                        <div
                                            class="absolute inset-0 flex items-center justify-center"
                                            style="transform: rotate(-35deg); opacity: 0.12;"
                                        >
                                            <div class="grid gap-16 text-center" style="grid-template-rows: repeat(8, 1fr);">
                                                <div
                                                    v-for="n in 8"
                                                    :key="n"
                                                    class="text-xs font-bold tracking-widest uppercase whitespace-nowrap"
                                                    style="color: #EF4444; font-size: 13px; letter-spacing: 0.2em;"
                                                >RAHASIA &bull; {{ document.owner?.name ?? '' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Image viewer -->
                                <div v-else-if="fileViewerType === 'image'" class="relative rounded-lg overflow-hidden">
                                    <img
                                        :src="stream_url"
                                        :alt="document.file_name"
                                        class="w-full object-contain rounded-lg"
                                        style="max-height: 600px; background: var(--bg-tertiary);"
                                    />
                                    <!-- Watermark overlay -->
                                    <div
                                        v-if="showWatermarkOverlay"
                                        class="absolute inset-0 pointer-events-none overflow-hidden select-none"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="absolute inset-0 flex items-center justify-center"
                                            style="transform: rotate(-35deg); opacity: 0.15;"
                                        >
                                            <div class="grid gap-12 text-center" style="grid-template-rows: repeat(6, 1fr);">
                                                <div
                                                    v-for="n in 6"
                                                    :key="n"
                                                    class="text-xs font-bold tracking-widest uppercase whitespace-nowrap"
                                                    style="color: #EF4444; font-size: 14px; letter-spacing: 0.2em;"
                                                >RAHASIA &bull; {{ document.owner?.name ?? '' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Unsupported type -->
                                <div v-else class="rounded-lg p-6 text-center" style="background: var(--bg-tertiary);">
                                    <FileText :size="32" class="mx-auto mb-2" style="color: var(--text-muted);" />
                                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                                        Pratinjau tidak tersedia untuk tipe berkas ini.
                                    </p>
                                    <a
                                        v-if="download_url"
                                        :href="download_url"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white"
                                        style="background: #8B5CF6;"
                                    ><Download :size="14" /> Unduh Berkas</a>
                                </div>
                            </div>

                            <!-- AI Summary -->
                            <div
                                v-if="document.ai_summary"
                                class="rounded-lg p-4"
                                style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.2);"
                            >
                                <p class="text-xs font-semibold mb-2" style="color: #8B5CF6;">Ringkasan AI</p>
                                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ document.ai_summary }}</p>
                                <div v-if="document.ai_tags?.length" class="flex gap-1.5 flex-wrap mt-2">
                                    <span
                                        v-for="tag in document.ai_tags"
                                        :key="tag"
                                        class="text-[11px] px-2 py-0.5 rounded-full"
                                        style="background: rgba(139,92,246,0.15); color: #A78BFA;"
                                    >{{ tag }}</span>
                                </div>
                            </div>

                            <p v-if="!document.description && !document.file_name && !document.ai_summary" class="text-sm" style="color: var(--text-muted);">
                                Tidak ada konten untuk ditampilkan.
                            </p>
                        </div>

                        <!-- Versi tab -->
                        <div v-else-if="activeTab === 'versions'">
                            <div v-if="document.versions?.length" class="space-y-2">
                                <div
                                    v-for="v in document.versions"
                                    :key="v.id"
                                    class="flex items-center justify-between rounded-lg px-4 py-3"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                            style="background: #3B82F6;"
                                        >v{{ v.version_number }}</span>
                                        <div>
                                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                                Versi {{ v.version_number }}
                                                <span v-if="v.is_latest" class="ml-2 text-[10px] font-bold px-1.5 py-0.5 rounded-full" style="background: rgba(16,185,129,0.2); color: #10B981;">Terbaru</span>
                                            </p>
                                            <p class="text-xs" style="color: var(--text-muted);">{{ formatDate(v.created_at) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                            :style="`background: ${statusBadge(v.status).bg}; color: ${statusBadge(v.status).text};`"
                                        >{{ statusBadge(v.status).label }}</span>
                                        <Link
                                            :href="`/documents/${v.id}`"
                                            class="text-xs hover:opacity-80"
                                            style="color: #3B82F6;"
                                        >Lihat</Link>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm" style="color: var(--text-muted);">Belum ada versi sebelumnya.</p>
                        </div>

                        <!-- Persetujuan tab -->
                        <div v-else-if="activeTab === 'approvals'">
                            <div v-if="document.approvals?.length" class="space-y-3">
                                <div
                                    v-for="a in document.approvals"
                                    :key="a.id"
                                    class="rounded-lg p-4"
                                    :style="`background: var(--bg-tertiary); border-left: 3px solid ${approvalStatusConfig[a.status]?.color ?? '#94A3B8'};`"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                                style="background: #8B5CF6;"
                                            >{{ a.step_number }}</div>
                                            <div>
                                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                                    {{ a.approver?.name ?? 'Approver' }}
                                                </p>
                                                <p class="text-xs" style="color: var(--text-muted);">
                                                    Langkah {{ a.step_number }}
                                                    <span v-if="a.decided_at"> • {{ formatDate(a.decided_at) }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                            :style="`background: rgba(0,0,0,0.1); color: ${approvalStatusConfig[a.status]?.color ?? '#94A3B8'};`"
                                        >{{ approvalStatusConfig[a.status]?.label ?? a.status }}</span>
                                    </div>

                                    <p v-if="a.notes" class="text-xs mt-2 italic" style="color: var(--text-muted);">{{ a.notes }}</p>

                                    <!-- Approve/reject controls -->
                                    <div v-if="can.approve && a.status === 'pending'" class="mt-3 space-y-2">
                                        <div class="flex gap-2">
                                            <button
                                                @click="doApprove(a.id)"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                                                style="background: #10B981;"
                                            ><CheckCircle2 :size="12" /> Setujui</button>
                                            <button
                                                @click="activeReject = activeReject === a.id ? null : a.id"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                                style="background: rgba(239,68,68,0.15); color: #F87171;"
                                            ><XCircle :size="12" /> Tolak</button>
                                        </div>
                                        <!-- Reject form -->
                                        <div v-if="activeReject === a.id" class="space-y-2">
                                            <textarea
                                                v-model="rejectForm.reason"
                                                rows="2"
                                                placeholder="Alasan penolakan (wajib)..."
                                                class="w-full px-3 py-2 rounded-lg text-xs border outline-none resize-none"
                                                style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                            />
                                            <button
                                                @click="doReject(a.id)"
                                                :disabled="!rejectForm.reason || rejectForm.processing"
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                                                style="background: #EF4444;"
                                            >Konfirmasi Tolak</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm" style="color: var(--text-muted);">Belum ada proses persetujuan.</p>
                        </div>

                        <!-- Terkait tab -->
                        <div v-else-if="activeTab === 'terkait'">
                            <div class="space-y-3">
                                <div v-if="document.meeting" class="rounded-lg p-4 flex items-center justify-between" style="background: var(--bg-tertiary);">
                                    <div>
                                        <p class="text-xs font-semibold mb-1" style="color: var(--text-muted);">Meeting</p>
                                        <p class="text-sm" style="color: var(--text-primary);">{{ document.meeting.title }}</p>
                                    </div>
                                    <Link
                                        :href="`/meetings/${document.meeting.id}`"
                                        class="text-xs hover:opacity-80"
                                        style="color: #3B82F6;"
                                    >Lihat</Link>
                                </div>
                                <div v-if="document.task" class="rounded-lg p-4 flex items-center justify-between" style="background: var(--bg-tertiary);">
                                    <div>
                                        <p class="text-xs font-semibold mb-1" style="color: var(--text-muted);">Task</p>
                                        <p class="text-sm" style="color: var(--text-primary);">{{ document.task.title }}</p>
                                    </div>
                                    <Link
                                        :href="`/tasks/${document.task.id}`"
                                        class="text-xs hover:opacity-80"
                                        style="color: #3B82F6;"
                                    >Lihat</Link>
                                </div>
                                <p v-if="!document.meeting && !document.task" class="text-sm" style="color: var(--text-muted);">
                                    Tidak ada entitas terkait.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right sidebar -->
                <div class="space-y-4">
                    <!-- Document info card -->
                    <div class="rounded-xl p-4 space-y-3" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                        <h3 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Info Dokumen</h3>

                        <div v-if="document.owner">
                            <p class="text-xs" style="color: var(--text-muted);">Pemilik</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background: #3B82F6;">
                                    {{ document.owner.name.charAt(0).toUpperCase() }}
                                </div>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ document.owner.name }}</span>
                            </div>
                        </div>

                        <div class="border-t pt-3 space-y-2" style="border-color: var(--border-color);">
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Tipe</span>
                                <span style="color: var(--text-secondary);">{{ typeLabel(document.document_type) }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Versi</span>
                                <span style="color: var(--text-secondary);">v{{ document.version_number }}</span>
                            </div>
                            <div v-if="document.document_date" class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Tgl Dokumen</span>
                                <span style="color: var(--text-secondary);">{{ formatDate(document.document_date) }}</span>
                            </div>
                            <div v-if="document.effective_date" class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Berlaku Mulai</span>
                                <span style="color: var(--text-secondary);">{{ formatDate(document.effective_date) }}</span>
                            </div>
                            <div v-if="document.expiry_date" class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Berlaku Hingga</span>
                                <span style="color: var(--text-secondary);">{{ formatDate(document.expiry_date) }}</span>
                            </div>
                            <div v-if="document.file_size" class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Ukuran File</span>
                                <span style="color: var(--text-secondary);">{{ formatFileSize(document.file_size) }}</span>
                            </div>
                            <div v-if="document.versions_count !== undefined" class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Total Versi</span>
                                <span style="color: var(--text-secondary);">{{ (document.versions_count ?? 0) + 1 }}</span>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div v-if="document.tags?.length" class="border-t pt-3" style="border-color: var(--border-color);">
                            <p class="text-xs mb-2" style="color: var(--text-muted);">Tags</p>
                            <div class="flex gap-1.5 flex-wrap">
                                <span
                                    v-for="tag in document.tags"
                                    :key="tag"
                                    class="text-[11px] px-2 py-0.5 rounded-full"
                                    style="background: var(--bg-tertiary); color: var(--text-muted);"
                                >{{ tag }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Approvals summary -->
                    <div v-if="document.approvals?.length" class="rounded-xl p-4" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                        <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-muted);">
                            Persetujuan ({{ document.approvals.length }} langkah)
                        </h3>
                        <div class="space-y-2">
                            <div
                                v-for="a in document.approvals"
                                :key="a.id"
                                class="flex items-center gap-2"
                            >
                                <component
                                    :is="approvalStatusConfig[a.status]?.icon ?? Clock"
                                    :size="14"
                                    :style="`color: ${approvalStatusConfig[a.status]?.color ?? '#94A3B8'};`"
                                />
                                <span class="text-xs flex-1 truncate" style="color: var(--text-secondary);">{{ a.approver?.name ?? '—' }}</span>
                                <span class="text-[11px]" :style="`color: ${approvalStatusConfig[a.status]?.color ?? '#94A3B8'};`">
                                    {{ approvalStatusConfig[a.status]?.label ?? a.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
