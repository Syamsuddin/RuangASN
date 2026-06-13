<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, BarChart2, CheckCircle2, XCircle, Clock,
    User, Building2, AlertCircle, Sparkles, Send,
    RefreshCw, Archive, Eye,
} from 'lucide-vue-next';

interface StatusHistory {
    id: string;
    from_status?: string;
    to_status: string;
    notes?: string;
    changed_at?: string;
    changed_by?: { id: string; name: string };
}

interface Report {
    id: string;
    title: string;
    content?: string;
    ai_draft?: string;
    has_ai_draft: boolean;
    report_type: string | { value: string };
    period_type: string | { value: string };
    status: string | { value: string };
    status_label: string;
    period_start_date?: string;
    period_end_date?: string;
    data_sources?: string[];
    data_classification: number;
    classification_label: string;
    version: number;
    author?: { id: string; name: string };
    approver?: { id: string; name: string };
    status_histories?: StatusHistory[];
    submitted_at?: string;
    approved_at?: string;
    published_at?: string;
    updated_at?: string;
    created_at?: string;
}

interface Props {
    report: Report;
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        reject: boolean;
        publish: boolean;
        generateAiDraft: boolean;
    };
}

const props = defineProps<Props>();

type Tab = 'content' | 'sources' | 'history' | 'ai';
const activeTab = ref<Tab>('content');

const tabs: { key: Tab; label: string }[] = [
    { key: 'content', label: 'Konten' },
    { key: 'sources', label: 'Data Sources' },
    { key: 'history', label: 'Riwayat' },
    { key: 'ai',      label: 'Saran AI' },
];

// Helpers
const statusVal = (s: string | { value: string }) => typeof s === 'string' ? s : s.value;
const typeVal   = (t: string | { value: string }) => typeof t === 'string' ? t : t.value;

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

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const formatDateTime = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

// Transition modal state
const showTransitionModal = ref(false);
const transitionTarget    = ref<'in_review' | 'revision' | 'rejected' | 'archived'>('in_review');
const transitionForm      = useForm({ status: '', notes: '' });

const openTransition = (status: 'in_review' | 'revision' | 'rejected' | 'archived') => {
    transitionTarget.value  = status;
    transitionForm.status   = status;
    transitionForm.notes    = '';
    showTransitionModal.value = true;
};

const submitTransition = () => {
    transitionForm.post(`/reports/${props.report.id}/transition`, {
        onSuccess: () => { showTransitionModal.value = false; },
    });
};

const submitForm = useForm({});
const doSubmit = () => submitForm.post(`/reports/${props.report.id}/submit`);

const publishForm = useForm({});
const doPublish = () => publishForm.post(`/reports/${props.report.id}/publish`);

const aiDraftForm = useForm({});
const doGenerateAiDraft = () => aiDraftForm.post(`/reports/${props.report.id}/ai-draft`, {
    onSuccess: () => { activeTab.value = 'ai'; },
});

// Edit inline content
const editingContent = ref(false);
const editForm = useForm({ content: props.report.content ?? '' });
const saveContent = () => {
    editForm.patch(`/reports/${props.report.id}`, {
        onSuccess: () => { editingContent.value = false; },
    });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/reports" style="color: var(--text-muted);" class="hover:opacity-80">Laporan</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-secondary);" class="truncate max-w-[200px]">{{ report.title }}</span>
        </template>

        <div class="max-w-[1100px] space-y-4">

            <!-- Header card -->
            <div
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <div class="flex items-start gap-4 flex-wrap">
                    <!-- Back + icon -->
                    <Link href="/reports" class="mt-1 text-sm hover:opacity-70" style="color: var(--text-muted);">
                        <ArrowLeft :size="18" />
                    </Link>

                    <div
                        class="w-10 h-10 rounded-lg flex-shrink-0 flex items-center justify-center"
                        style="background: rgba(59,130,246,0.1);"
                    >
                        <BarChart2 :size="20" style="color: #3B82F6;" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <!-- Type badge -->
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full" style="background: var(--bg-tertiary); color: var(--text-muted);">
                                {{ typeLabels[typeVal(report.report_type)] ?? typeVal(report.report_type) }}
                            </span>
                            <!-- Status badge -->
                            <span
                                class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                :style="`background: ${statusBadge(report.status).bg}; color: ${statusBadge(report.status).text};`"
                            >{{ statusBadge(report.status).label }}</span>
                        </div>

                        <h1 class="text-lg font-bold leading-snug mb-1" style="color: var(--text-primary);">
                            {{ report.title }}
                        </h1>

                        <p class="text-xs" style="color: var(--text-muted);">
                            Periode: {{ formatDate(report.period_start_date) }} – {{ formatDate(report.period_end_date) }}
                            <span class="mx-1">·</span>
                            Penulis: {{ report.author?.name ?? '—' }}
                        </p>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Submit (draft/revision + author) -->
                        <button
                            v-if="can.submit && ['draft', 'revision'].includes(statusVal(report.status))"
                            @click="doSubmit"
                            :disabled="submitForm.processing"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                            style="background: #3B82F6;"
                        >
                            <Send :size="13" />
                            Submit
                        </button>

                        <!-- Generate AI Draft (draft/revision + author) -->
                        <button
                            v-if="can.generateAiDraft && ['draft', 'revision'].includes(statusVal(report.status))"
                            @click="doGenerateAiDraft"
                            :disabled="aiDraftForm.processing"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                            style="background: #8B5CF6;"
                        >
                            <Sparkles :size="13" />
                            Generate Draft AI
                        </button>

                        <!-- Reviewer actions (submitted/in_review + can approve) -->
                        <template v-if="can.approve && ['submitted', 'in_review'].includes(statusVal(report.status))">
                            <button
                                @click="openTransition('in_review')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                                style="background: #8B5CF6;"
                            >
                                <Eye :size="13" />
                                Mulai Review
                            </button>
                            <button
                                @click="openTransition('revision')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                style="background: rgba(245,158,11,0.15); color: #F59E0B; border: 1px solid rgba(245,158,11,0.3);"
                            >
                                <RefreshCw :size="13" />
                                Minta Revisi
                            </button>
                            <button
                                v-if="can.approve && statusVal(report.status) === 'in_review'"
                                @click="openTransition('rejected')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                style="background: rgba(239,68,68,0.15); color: #EF4444; border: 1px solid rgba(239,68,68,0.3);"
                            >
                                <XCircle :size="13" />
                                Tolak
                            </button>
                        </template>

                        <!-- Approve (in_review + can approve) -->
                        <button
                            v-if="can.approve && statusVal(report.status) === 'in_review'"
                            @click="openTransition('in_review')"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                            style="background: #10B981;"
                        >
                            <CheckCircle2 :size="13" />
                            Setujui
                        </button>

                        <!-- Publish (approved + can publish) -->
                        <button
                            v-if="can.publish && statusVal(report.status) === 'approved'"
                            @click="doPublish"
                            :disabled="publishForm.processing"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                            style="background: #10B981;"
                        >
                            <CheckCircle2 :size="13" />
                            Publikasikan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main content + sidebar -->
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4 items-start">

                <!-- Tabs panel -->
                <div
                    class="rounded-xl overflow-hidden"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <!-- Tab bar -->
                    <div class="flex border-b" style="border-color: var(--border-color);">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="px-4 py-3 text-sm font-medium transition-colors"
                            :style="activeTab === tab.key
                                ? 'color: #3B82F6; border-bottom: 2px solid #3B82F6;'
                                : 'color: var(--text-muted); border-bottom: 2px solid transparent;'"
                        >{{ tab.label }}</button>
                    </div>

                    <!-- Konten tab -->
                    <div v-if="activeTab === 'content'" class="p-5">
                        <div v-if="!editingContent">
                            <div class="flex justify-between items-center mb-3">
                                <p class="text-xs font-semibold" style="color: var(--text-muted);">Isi Laporan</p>
                                <button
                                    v-if="can.update"
                                    @click="editingContent = true"
                                    class="text-xs px-2 py-1 rounded"
                                    style="color: #3B82F6; background: rgba(59,130,246,0.1);"
                                >Edit</button>
                            </div>
                            <p
                                v-if="report.content"
                                class="text-sm leading-relaxed whitespace-pre-wrap"
                                style="color: var(--text-secondary);"
                            >{{ report.content }}</p>
                            <p v-else class="text-sm italic" style="color: var(--text-muted);">Belum ada konten.</p>
                        </div>
                        <div v-else>
                            <textarea
                                v-model="editForm.content"
                                rows="12"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                            <div class="flex gap-2 mt-3">
                                <button
                                    @click="saveContent"
                                    :disabled="editForm.processing"
                                    class="px-4 py-2 rounded-lg text-xs font-semibold text-white"
                                    style="background: #3B82F6;"
                                >Simpan</button>
                                <button
                                    @click="editingContent = false"
                                    class="px-4 py-2 rounded-lg text-xs font-medium border"
                                    style="border-color: var(--border-color); color: var(--text-secondary);"
                                >Batal</button>
                            </div>
                        </div>
                    </div>

                    <!-- Data Sources tab -->
                    <div v-if="activeTab === 'sources'" class="p-5">
                        <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Sumber Data</p>
                        <div v-if="report.data_sources && report.data_sources.length > 0" class="space-y-2">
                            <div
                                v-for="(src, idx) in report.data_sources"
                                :key="idx"
                                class="px-3 py-2 rounded-lg text-sm"
                                style="background: var(--bg-tertiary); color: var(--text-secondary);"
                            >{{ src }}</div>
                        </div>
                        <p v-else class="text-sm italic" style="color: var(--text-muted);">Belum ada sumber data.</p>
                    </div>

                    <!-- Riwayat tab -->
                    <div v-if="activeTab === 'history'" class="p-5">
                        <p class="text-xs font-semibold mb-4" style="color: var(--text-muted);">Riwayat Status</p>
                        <div v-if="report.status_histories && report.status_histories.length > 0" class="relative">
                            <div class="absolute left-3 top-0 bottom-0 w-px" style="background: var(--border-color);" />
                            <div class="space-y-4">
                                <div
                                    v-for="h in report.status_histories"
                                    :key="h.id"
                                    class="flex gap-4 pl-8 relative"
                                >
                                    <div class="absolute left-1.5 w-3 h-3 rounded-full border-2 mt-1" style="background: var(--card-bg); border-color: #3B82F6;" />
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span
                                                v-if="h.from_status"
                                                class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                                :style="`background: ${statusBadge(h.from_status).bg}; color: ${statusBadge(h.from_status).text};`"
                                            >{{ statusBadge(h.from_status).label }}</span>
                                            <span v-if="h.from_status" class="text-xs" style="color: var(--text-muted);">→</span>
                                            <span
                                                class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                                :style="`background: ${statusBadge(h.to_status).bg}; color: ${statusBadge(h.to_status).text};`"
                                            >{{ statusBadge(h.to_status).label }}</span>
                                        </div>
                                        <p class="text-xs mt-1" style="color: var(--text-muted);">
                                            {{ h.changed_by?.name ?? '—' }}
                                            <span class="mx-1">·</span>
                                            {{ formatDateTime(h.changed_at) }}
                                        </p>
                                        <p v-if="h.notes" class="text-xs mt-1 italic" style="color: var(--text-secondary);">
                                            "{{ h.notes }}"
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm italic" style="color: var(--text-muted);">Belum ada riwayat.</p>
                    </div>

                    <!-- Saran AI tab -->
                    <div v-if="activeTab === 'ai'" class="p-5">
                        <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Draft AI</p>

                        <div
                            v-if="report.has_ai_draft && report.ai_draft"
                            class="rounded-lg p-4 text-sm leading-relaxed whitespace-pre-wrap"
                            style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.25); color: var(--text-secondary);"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <Sparkles :size="14" style="color: #8B5CF6;" />
                                <span class="text-xs font-semibold" style="color: #8B5CF6;">Draft AI</span>
                            </div>
                            {{ report.ai_draft }}
                        </div>

                        <div v-else class="text-center py-8">
                            <Sparkles :size="32" class="mx-auto mb-2" style="color: rgba(139,92,246,0.4);" />
                            <p class="text-sm mb-3" style="color: var(--text-muted);">
                                Draft AI belum digenerate untuk laporan ini.
                            </p>
                            <button
                                v-if="can.generateAiDraft"
                                @click="doGenerateAiDraft"
                                :disabled="aiDraftForm.processing"
                                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white mx-auto"
                                style="background: #8B5CF6;"
                            >
                                <Sparkles :size="14" />
                                Generate Draft dengan AI
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar info card -->
                <div
                    class="rounded-xl p-5 space-y-4"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--text-muted);">Detail Laporan</p>

                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Status</p>
                            <span
                                class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                :style="`background: ${statusBadge(report.status).bg}; color: ${statusBadge(report.status).text};`"
                            >{{ statusBadge(report.status).label }}</span>
                        </div>

                        <div>
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Penulis</p>
                            <div class="flex items-center gap-1.5">
                                <User :size="13" style="color: var(--text-muted);" />
                                <span style="color: var(--text-secondary);">{{ report.author?.name ?? '—' }}</span>
                            </div>
                        </div>

                        <div v-if="report.approver">
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Disetujui oleh</p>
                            <div class="flex items-center gap-1.5">
                                <User :size="13" style="color: var(--text-muted);" />
                                <span style="color: var(--text-secondary);">{{ report.approver.name }}</span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Periode</p>
                            <span style="color: var(--text-secondary);">
                                {{ formatDate(report.period_start_date) }} – {{ formatDate(report.period_end_date) }}
                            </span>
                        </div>

                        <div>
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Klasifikasi</p>
                            <span style="color: var(--text-secondary);">{{ report.classification_label }}</span>
                        </div>

                        <div v-if="report.submitted_at">
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Diajukan</p>
                            <span style="color: var(--text-secondary);">{{ formatDateTime(report.submitted_at) }}</span>
                        </div>

                        <div v-if="report.approved_at">
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Disetujui</p>
                            <span style="color: var(--text-secondary);">{{ formatDateTime(report.approved_at) }}</span>
                        </div>

                        <div v-if="report.published_at">
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Dipublikasikan</p>
                            <span style="color: var(--text-secondary);">{{ formatDateTime(report.published_at) }}</span>
                        </div>

                        <div>
                            <p class="text-xs mb-0.5" style="color: var(--text-muted);">Diperbarui</p>
                            <span style="color: var(--text-secondary);">{{ formatDateTime(report.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transition modal -->
        <Teleport to="body">
            <div v-if="showTransitionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0" style="background: rgba(0,0,0,0.4);" @click="showTransitionModal = false" />
                <div
                    class="relative z-10 w-full max-w-md rounded-xl p-6 shadow-2xl"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <h3 class="font-semibold text-base mb-4" style="color: var(--text-primary);">
                        <template v-if="transitionTarget === 'in_review'">Mulai Review / Setujui</template>
                        <template v-else-if="transitionTarget === 'revision'">Minta Revisi</template>
                        <template v-else-if="transitionTarget === 'rejected'">Tolak Laporan</template>
                        <template v-else>Arsipkan Laporan</template>
                    </h3>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Status Baru <span style="color: #EF4444;">*</span>
                            </label>
                            <select
                                v-model="transitionForm.status"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option value="in_review">Sedang Direview</option>
                                <option value="approved">Disetujui</option>
                                <option value="revision">Perlu Revisi</option>
                                <option value="rejected">Ditolak</option>
                                <option value="archived">Diarsipkan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Catatan
                                <span v-if="['revision','rejected'].includes(transitionForm.status)" style="color: #EF4444;">*</span>
                            </label>
                            <textarea
                                v-model="transitionForm.notes"
                                rows="3"
                                placeholder="Tambahkan catatan..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                            <p v-if="transitionForm.errors.notes" class="mt-1 text-xs" style="color: #EF4444;">{{ transitionForm.errors.notes }}</p>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-5">
                        <button
                            @click="submitTransition"
                            :disabled="transitionForm.processing"
                            class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white"
                            style="background: #3B82F6;"
                        >{{ transitionForm.processing ? 'Memproses...' : 'Konfirmasi' }}</button>
                        <button
                            @click="showTransitionModal = false"
                            class="px-4 py-2.5 rounded-lg text-sm font-medium border"
                            style="border-color: var(--border-color); color: var(--text-secondary);"
                        >Batal</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
