<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, Calendar, User, Building2, CheckSquare, Square,
    Paperclip, MessageSquare, History, Upload, Trash2, Send,
    Plus, Edit3,
} from 'lucide-vue-next';

interface Checklist {
    id: string;
    title: string;
    is_done: boolean;
    sort_order: number;
}

interface Evidence {
    id: string;
    title: string;
    evidence_type: string;
    created_at: string;
}

interface Comment {
    id: string;
    content: string;
    user: { id: string; name: string };
    created_at: string;
}

interface StatusHistory {
    id: string;
    from_status: string;
    to_status: string;
    reason?: string;
    changed_at: string;
}

interface Props {
    task: {
        id: string;
        title: string;
        description?: string;
        status: string;
        priority: string;
        due_date?: string;
        created_at: string;
        updated_at: string;
        creator?: { id: string; name: string };
        assignee?: { id: string; name: string };
        reviewer?: { id: string; name: string };
        organization_id: string;
        checklists: Checklist[];
        evidences: Evidence[];
        comments?: Comment[];
        status_histories?: StatusHistory[];
    };
    can: { update: boolean; delete: boolean; transition: boolean };
}

const props = defineProps<Props>();

// ── Tabs ──
const tabs = [
    { key: 'overview',   label: 'Overview',  icon: Edit3 },
    { key: 'checklist',  label: 'Checklist', icon: CheckSquare },
    { key: 'evidence',   label: 'Bukti',     icon: Paperclip },
    { key: 'comments',   label: 'Komentar',  icon: MessageSquare },
    { key: 'history',    label: 'Riwayat',   icon: History },
];
const activeTab = ref<string>('overview');

// ── Status / Priority config ──
const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:           { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    open:            { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Open' },
    assigned:        { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Ditugaskan' },
    in_progress:     { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'In Progress' },
    waiting_review:  { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Review' },
    revision_needed: { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Revisi' },
    completed:       { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Selesai' },
    closed:          { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Closed' },
    cancelled:       { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Dibatalkan' },
};

const priorityConfig: Record<string, { bg: string; text: string; label: string }> = {
    critical: { bg: 'rgba(239,68,68,0.2)',    text: '#F87171', label: 'Kritis' },
    high:     { bg: 'rgba(239,68,68,0.15)',   text: '#F87171', label: 'Tinggi' },
    medium:   { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D', label: 'Sedang' },
    low:      { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: 'Rendah' },
    routine:  { bg: 'rgba(100,116,139,0.1)',  text: '#94A3B8', label: 'Rutin' },
};

const statusBadge = (s: string) =>
    statusConfig[s] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: s };
const priorityBadge = (p: string) =>
    priorityConfig[p] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: p };

// ── Next status logic ──
const nextStatusMap: Record<string, { status: string; label: string }> = {
    draft:           { status: 'open',           label: 'Buka Task' },
    open:            { status: 'in_progress',    label: 'Mulai Kerjakan' },
    assigned:        { status: 'in_progress',    label: 'Mulai Kerjakan' },
    in_progress:     { status: 'waiting_review', label: 'Submit Review' },
    waiting_review:  { status: 'completed',      label: 'Selesaikan' },
    revision_needed: { status: 'in_progress',    label: 'Kerjakan Ulang' },
};

const nextStatus = computed(() => nextStatusMap[props.task.status] ?? null);

// ── Transition form ──
const transitionForm = useForm({ status: '', reason: '' });

const doTransition = (statusValue: string) => {
    transitionForm.status = statusValue;
    transitionForm.post(`/tasks/${props.task.id}/transition`, { preserveScroll: true });
};

// ── Checklist ──
const checklistForm = useForm({ title: '' });
const addingChecklist = ref(false);

const addChecklist = () => {
    if (!checklistForm.title.trim()) return;
    checklistForm.post(`/tasks/${props.task.id}/checklists`, {
        preserveScroll: true,
        onSuccess: () => { checklistForm.reset(); addingChecklist.value = false; },
    });
};

const toggleChecklist = (checklist: Checklist) => {
    router.patch(`/tasks/checklists/${checklist.id}/toggle`, {}, { preserveScroll: true });
};

const deleteChecklist = (checklist: Checklist) => {
    router.delete(`/tasks/checklists/${checklist.id}`, { preserveScroll: true });
};

const checklistProgress = computed(() => {
    const total = props.task.checklists.length;
    const done  = props.task.checklists.filter(c => c.is_done).length;
    return { done, total, pct: total > 0 ? Math.round((done / total) * 100) : 0 };
});

// ── Evidence ──
const evidenceForm = useForm({ title: '', file: null as File | null });
const evidenceFileInput = ref<HTMLInputElement | null>(null);

const handleEvidenceFile = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) evidenceForm.file = file;
};

const submitEvidence = () => {
    evidenceForm.post(`/tasks/${props.task.id}/evidences`, {
        preserveScroll: true,
        onSuccess: () => {
            evidenceForm.reset();
            if (evidenceFileInput.value) evidenceFileInput.value.value = '';
        },
        forceFormData: true,
    });
};

const deleteEvidence = (evidenceId: string) => {
    router.delete(`/tasks/evidences/${evidenceId}`, { preserveScroll: true });
};

// ── Comment ──
const commentForm = useForm({ content: '' });

const submitComment = () => {
    commentForm.post(`/tasks/${props.task.id}/comments`, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
};

// ── Helpers ──
const initials = (name: string) =>
    name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();

const formatDate = (dateStr: string) =>
    new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

const formatDateTime = (dateStr: string) =>
    new Date(dateStr).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const isOverdue = computed(() => {
    if (!props.task.due_date) return false;
    return new Date(props.task.due_date) < new Date() &&
        !['completed', 'cancelled', 'closed'].includes(props.task.status);
});
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/tasks" style="color: var(--text-muted);" class="hover:opacity-80">Tasks</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium truncate max-w-[200px]">
                {{ task.title }}
            </span>
        </template>

        <div class="max-w-[1200px] space-y-4">

            <!-- ── Header card ── -->
            <div
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <!-- Back + actions row -->
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <Link
                        href="/tasks"
                        class="flex items-center gap-1.5 text-sm font-medium transition-colors hover:opacity-80"
                        style="color: #3B82F6;"
                    >
                        <ArrowLeft :size="16" /> Kembali ke Tasks
                    </Link>

                    <!-- Action buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            v-if="can.transition && nextStatus"
                            @click="doTransition(nextStatus.status)"
                            :disabled="transitionForm.processing"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                            style="background: #3B82F6;"
                        >
                            {{ nextStatus.label }}
                        </button>
                        <Link
                            v-if="can.update"
                            :href="`/tasks/${task.id}/edit`"
                            class="px-3 py-2 rounded-lg text-sm font-medium border transition-colors"
                            style="border-color: var(--border-color); color: var(--text-secondary); background: var(--bg-tertiary);"
                        >
                            Edit
                        </Link>
                    </div>
                </div>

                <!-- Title -->
                <h1 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">{{ task.title }}</h1>

                <!-- Badges row -->
                <div class="flex items-center gap-2 flex-wrap mb-4">
                    <span
                        class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        :style="`background: ${statusBadge(task.status).bg}; color: ${statusBadge(task.status).text};`"
                    >{{ statusBadge(task.status).label }}</span>
                    <span
                        class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        :style="`background: ${priorityBadge(task.priority).bg}; color: ${priorityBadge(task.priority).text};`"
                    >{{ priorityBadge(task.priority).label }}</span>
                    <span
                        v-if="isOverdue"
                        class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background: rgba(239,68,68,0.2); color: #F87171;"
                    >Overdue</span>
                </div>

                <!-- Meta row -->
                <div class="flex items-center gap-5 flex-wrap text-sm" style="color: var(--text-secondary);">
                    <div v-if="task.due_date" class="flex items-center gap-1.5">
                        <Calendar :size="16" :style="isOverdue ? 'color: #EF4444;' : 'color: var(--text-muted);'" />
                        <span :style="isOverdue ? 'color: #EF4444;' : ''">
                            Jatuh tempo: {{ formatDate(task.due_date) }}
                        </span>
                    </div>
                    <div v-if="task.assignee" class="flex items-center gap-1.5">
                        <User :size="16" style="color: var(--text-muted);" />
                        <span>{{ task.assignee.name }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Main grid: tabs + sidebar ── -->
            <div class="grid lg:grid-cols-[1fr_280px] gap-4 items-start">

                <!-- Left: tabs + content -->
                <div
                    class="rounded-xl overflow-hidden"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <!-- Tab bar -->
                    <div class="flex border-b" style="border-color: var(--border-color);">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium border-b-2 transition-colors"
                            :style="activeTab === tab.key
                                ? 'border-color: #3B82F6; color: #3B82F6;'
                                : 'border-color: transparent; color: var(--text-muted);'"
                        >
                            <component :is="tab.icon" :size="14" />
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Tab content -->
                    <div class="p-5">

                        <!-- Overview -->
                        <div v-if="activeTab === 'overview'">
                            <div
                                v-if="task.description"
                                class="rounded-lg p-4 text-sm leading-relaxed whitespace-pre-wrap"
                                style="background: var(--bg-tertiary); color: var(--text-secondary);"
                            >{{ task.description }}</div>
                            <p v-else class="text-sm" style="color: var(--text-muted);">Tidak ada deskripsi.</p>
                        </div>

                        <!-- Checklist -->
                        <div v-else-if="activeTab === 'checklist'">
                            <!-- Progress bar -->
                            <div v-if="task.checklists.length > 0" class="mb-4">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-semibold" style="color: var(--text-secondary);">
                                        Progress checklist
                                    </span>
                                    <span class="text-xs" style="color: var(--text-muted);">
                                        {{ checklistProgress.done }}/{{ checklistProgress.total }}
                                    </span>
                                </div>
                                <div class="h-2 rounded-full overflow-hidden" style="background: var(--bg-tertiary);">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        style="background: #10B981;"
                                        :style="`width: ${checklistProgress.pct}%;`"
                                    />
                                </div>
                            </div>

                            <!-- Items -->
                            <div class="space-y-1.5 mb-4">
                                <div
                                    v-for="item in task.checklists"
                                    :key="item.id"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 group"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <button
                                        v-if="can.update"
                                        @click="toggleChecklist(item)"
                                        class="shrink-0 transition-colors"
                                        :style="item.is_done ? 'color: #10B981;' : 'color: var(--text-muted);'"
                                    >
                                        <CheckSquare v-if="item.is_done" :size="18" />
                                        <Square v-else :size="18" />
                                    </button>
                                    <CheckSquare v-else :size="18" :style="item.is_done ? 'color: #10B981;' : 'color: var(--text-muted);'" />
                                    <span
                                        class="flex-1 text-sm"
                                        :style="item.is_done
                                            ? 'color: var(--text-muted); text-decoration: line-through;'
                                            : 'color: var(--text-secondary);'"
                                    >{{ item.title }}</span>
                                    <button
                                        v-if="can.update"
                                        @click="deleteChecklist(item)"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity rounded-md p-1"
                                        style="color: #EF4444;"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </div>
                            </div>

                            <!-- Add checklist -->
                            <div v-if="can.update">
                                <div v-if="!addingChecklist">
                                    <button
                                        @click="addingChecklist = true"
                                        class="flex items-center gap-2 text-sm font-medium transition-colors hover:opacity-80"
                                        style="color: #3B82F6;"
                                    >
                                        <Plus :size="16" /> Tambah item
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <input
                                        v-model="checklistForm.title"
                                        type="text"
                                        placeholder="Judul item checklist..."
                                        class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none"
                                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                        @keyup.enter="addChecklist"
                                        @keyup.escape="addingChecklist = false"
                                        autofocus
                                    />
                                    <button
                                        @click="addChecklist"
                                        :disabled="checklistForm.processing"
                                        class="px-3 py-2 rounded-lg text-sm font-semibold text-white"
                                        style="background: #3B82F6;"
                                    >Tambah</button>
                                    <button
                                        @click="addingChecklist = false; checklistForm.reset()"
                                        class="px-3 py-2 rounded-lg text-sm border transition-colors"
                                        style="border-color: var(--border-color); color: var(--text-secondary);"
                                    >Batal</button>
                                </div>
                            </div>

                            <p v-if="task.checklists.length === 0 && !addingChecklist" class="text-sm" style="color: var(--text-muted);">
                                Belum ada item checklist.
                            </p>
                        </div>

                        <!-- Evidence -->
                        <div v-else-if="activeTab === 'evidence'">
                            <!-- List -->
                            <div class="space-y-2 mb-5">
                                <div
                                    v-for="ev in task.evidences"
                                    :key="ev.id"
                                    class="flex items-center gap-3 rounded-lg px-3 py-3 group"
                                    style="background: var(--bg-tertiary); border: 1px solid var(--border-color);"
                                >
                                    <Paperclip :size="16" style="color: #3B82F6; shrink-0;" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ ev.title }}</p>
                                        <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ formatDateTime(ev.created_at) }}</p>
                                    </div>
                                    <button
                                        v-if="can.update"
                                        @click="deleteEvidence(ev.id)"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity rounded-md p-1"
                                        style="color: #EF4444;"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </div>
                                <p v-if="task.evidences.length === 0" class="text-sm" style="color: var(--text-muted);">
                                    Belum ada bukti/evidence yang diupload.
                                </p>
                            </div>

                            <!-- Upload form -->
                            <div
                                v-if="can.update"
                                class="rounded-lg p-4 space-y-3"
                                style="background: var(--bg-tertiary); border: 1px dashed var(--border-color);"
                            >
                                <p class="text-xs font-semibold" style="color: var(--text-secondary);">Upload Bukti Baru</p>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted);">Judul bukti</label>
                                    <input
                                        v-model="evidenceForm.title"
                                        type="text"
                                        placeholder="Nama/judul file bukti..."
                                        class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                        style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted);">File (PDF, DOC, JPG, PNG — maks. 20 MB)</label>
                                    <input
                                        ref="evidenceFileInput"
                                        type="file"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                        @change="handleEvidenceFile"
                                        class="text-xs"
                                        style="color: var(--text-secondary);"
                                    />
                                </div>
                                <p v-if="evidenceForm.errors.file" class="text-xs" style="color: #EF4444;">{{ evidenceForm.errors.file }}</p>
                                <button
                                    @click="submitEvidence"
                                    :disabled="evidenceForm.processing || !evidenceForm.title || !evidenceForm.file"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity"
                                    :style="(evidenceForm.processing || !evidenceForm.title || !evidenceForm.file)
                                        ? 'background: #3B82F6; opacity: 0.5; cursor: not-allowed;'
                                        : 'background: #3B82F6;'"
                                >
                                    <Upload :size="14" />
                                    {{ evidenceForm.processing ? 'Mengupload...' : 'Upload' }}
                                </button>
                            </div>
                        </div>

                        <!-- Comments -->
                        <div v-else-if="activeTab === 'comments'">
                            <!-- List -->
                            <div class="space-y-4 mb-5">
                                <div
                                    v-for="comment in (task.comments ?? [])"
                                    :key="comment.id"
                                    class="flex gap-3"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                        style="background: #3B82F6;"
                                    >{{ initials(comment.user.name) }}</div>
                                    <div class="flex-1">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ comment.user.name }}</span>
                                            <span class="text-xs" style="color: var(--text-muted);">{{ formatDateTime(comment.created_at) }}</span>
                                        </div>
                                        <p class="text-sm mt-1 leading-relaxed" style="color: var(--text-secondary);">{{ comment.content }}</p>
                                    </div>
                                </div>
                                <p v-if="!(task.comments ?? []).length" class="text-sm" style="color: var(--text-muted);">
                                    Belum ada komentar.
                                </p>
                            </div>

                            <!-- New comment -->
                            <div class="space-y-2">
                                <textarea
                                    v-model="commentForm.content"
                                    rows="3"
                                    placeholder="Tulis komentar..."
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <button
                                    @click="submitComment"
                                    :disabled="commentForm.processing || !commentForm.content.trim()"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity"
                                    :style="(commentForm.processing || !commentForm.content.trim())
                                        ? 'background: #3B82F6; opacity: 0.5; cursor: not-allowed;'
                                        : 'background: #3B82F6;'"
                                >
                                    <Send :size="14" /> Kirim
                                </button>
                            </div>
                        </div>

                        <!-- History -->
                        <div v-else-if="activeTab === 'history'">
                            <div
                                v-if="(task.status_histories ?? []).length"
                                class="space-y-3"
                            >
                                <div
                                    v-for="h in (task.status_histories ?? [])"
                                    :key="h.id"
                                    class="flex gap-3 items-start rounded-lg p-3"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <History :size="16" class="mt-0.5 shrink-0" style="color: var(--text-muted);" />
                                    <div class="flex-1">
                                        <p class="text-sm" style="color: var(--text-secondary);">
                                            <span
                                                class="font-semibold px-1.5 py-0.5 rounded text-xs"
                                                :style="`background: rgba(100,116,139,0.2); color: #94A3B8;`"
                                            >{{ h.from_status }}</span>
                                            &rarr;
                                            <span
                                                class="font-semibold px-1.5 py-0.5 rounded text-xs"
                                                :style="`background: ${(statusConfig[h.to_status] ?? {}).bg ?? 'rgba(59,130,246,0.2)'}; color: ${(statusConfig[h.to_status] ?? {}).text ?? '#60A5FA'};`"
                                            >{{ (statusConfig[h.to_status] ?? {}).label ?? h.to_status }}</span>
                                        </p>
                                        <p v-if="h.reason" class="text-xs mt-1" style="color: var(--text-muted);">{{ h.reason }}</p>
                                        <p class="text-xs mt-1" style="color: var(--text-muted);">{{ formatDateTime(h.changed_at) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    class="flex gap-3 items-start rounded-lg p-3"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <History :size="16" class="mt-0.5 shrink-0" style="color: var(--text-muted);" />
                                    <div>
                                        <p class="text-sm" style="color: var(--text-secondary);">Task dibuat</p>
                                        <p class="text-xs mt-1" style="color: var(--text-muted);">{{ formatDateTime(task.created_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right: info sidebar -->
                <div class="space-y-4">
                    <div
                        class="rounded-xl p-4 space-y-3"
                        style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                    >
                        <h3 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Info Task</h3>

                        <!-- Creator -->
                        <div>
                            <p class="text-xs" style="color: var(--text-muted);">Dibuat oleh</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                    style="background: #8B5CF6;"
                                >{{ task.creator ? initials(task.creator.name) : '?' }}</div>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ task.creator?.name ?? '—' }}</span>
                            </div>
                        </div>

                        <!-- Assignee -->
                        <div>
                            <p class="text-xs" style="color: var(--text-muted);">Ditugaskan ke</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                    style="background: #3B82F6;"
                                >{{ task.assignee ? initials(task.assignee.name) : '?' }}</div>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ task.assignee?.name ?? 'Belum ditugaskan' }}</span>
                            </div>
                        </div>

                        <!-- Reviewer -->
                        <div v-if="task.reviewer">
                            <p class="text-xs" style="color: var(--text-muted);">Reviewer</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                    style="background: #10B981;"
                                >{{ initials(task.reviewer.name) }}</div>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ task.reviewer.name }}</span>
                            </div>
                        </div>

                        <div class="border-t pt-3 space-y-2" style="border-color: var(--border-color);">
                            <!-- Created -->
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Dibuat</span>
                                <span style="color: var(--text-secondary);">{{ formatDate(task.created_at) }}</span>
                            </div>
                            <!-- Updated -->
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Diperbarui</span>
                                <span style="color: var(--text-secondary);">{{ formatDate(task.updated_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick status change -->
                    <div
                        v-if="can.transition"
                        class="rounded-xl p-4"
                        style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                    >
                        <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-muted);">Ubah Status</h3>
                        <div class="space-y-1.5">
                            <button
                                v-if="task.status === 'in_progress'"
                                @click="doTransition('waiting_review')"
                                class="w-full py-2 rounded-lg text-sm font-medium text-white text-left px-3 transition-opacity hover:opacity-90"
                                style="background: #8B5CF6;"
                            >Submit Review</button>
                            <button
                                v-if="task.status === 'waiting_review'"
                                @click="doTransition('revision_needed')"
                                class="w-full py-2 rounded-lg text-sm font-medium text-left px-3 transition-opacity hover:opacity-90"
                                style="background: rgba(245,158,11,0.2); color: #FCD34D;"
                            >Minta Revisi</button>
                            <button
                                v-if="['open','assigned','draft'].includes(task.status)"
                                @click="doTransition('cancelled')"
                                class="w-full py-2 rounded-lg text-sm font-medium text-left px-3 transition-opacity hover:opacity-90"
                                style="background: rgba(239,68,68,0.15); color: #F87171;"
                            >Batalkan Task</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
