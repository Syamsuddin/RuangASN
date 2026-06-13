<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, LayoutGrid, List, Search, X, Calendar, User,
    ChevronRight, AlertCircle,
} from 'lucide-vue-next';

interface Task {
    id: string;
    title: string;
    status: string;
    priority: string;
    due_date?: string;
    assignee?: { id: string; name: string };
    creator?: { id: string; name: string };
    evidence_count?: number;
    created_at: string;
}

interface Props {
    tasks_by_status: Record<string, Task[]>;
    all_tasks: { data: Task[]; meta: { total: number } };
    filters: { status?: string; priority?: string; search?: string };
    users: Array<{ id: string; name: string }>;
}

const props = defineProps<Props>();

// ── View & Filter state ──
const viewMode = ref<'kanban' | 'list'>('kanban');
const searchQuery = ref(props.filters.search ?? '');
const filterStatus = ref(props.filters.status ?? '');
const filterPriority = ref(props.filters.priority ?? '');
const showCreateModal = ref(false);

// ── Create Task Form ──
const createForm = useForm({
    title: '',
    description: '',
    priority: 'medium',
    assignee_id: '',
    due_date: '',
});

const submitCreate = () => {
    createForm.post('/tasks', {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// ── Filter apply ──
const applyFilters = () => {
    router.get('/tasks', {
        status: filterStatus.value || undefined,
        priority: filterPriority.value || undefined,
        search: searchQuery.value || undefined,
    }, { preserveState: true, replace: true });
};

// ── Status config ──
const statusConfig: Record<string, { accent: string; label: string; bg: string }> = {
    draft:           { accent: '#64748B', label: 'Draft',        bg: 'rgba(100,116,139,0.1)' },
    open:            { accent: '#3B82F6', label: 'Open',         bg: 'rgba(59,130,246,0.1)' },
    assigned:        { accent: '#3B82F6', label: 'Ditugaskan',   bg: 'rgba(59,130,246,0.1)' },
    in_progress:     { accent: '#F59E0B', label: 'In Progress',  bg: 'rgba(245,158,11,0.1)' },
    waiting_review:  { accent: '#8B5CF6', label: 'Review',       bg: 'rgba(139,92,246,0.1)' },
    revision_needed: { accent: '#F59E0B', label: 'Revisi',       bg: 'rgba(245,158,11,0.1)' },
    completed:       { accent: '#10B981', label: 'Selesai',      bg: 'rgba(16,185,129,0.1)' },
    closed:          { accent: '#64748B', label: 'Closed',       bg: 'rgba(100,116,139,0.1)' },
    cancelled:       { accent: '#EF4444', label: 'Dibatalkan',   bg: 'rgba(239,68,68,0.1)' },
};

const statusBadge = (status: string) => ({
    draft:           { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    open:            { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Open' },
    assigned:        { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Ditugaskan' },
    in_progress:     { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'In Progress' },
    waiting_review:  { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Review' },
    revision_needed: { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Revisi' },
    completed:       { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Selesai' },
    closed:          { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Closed' },
    cancelled:       { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Dibatalkan' },
}[status] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: status });

const priorityBadge = (priority: string) => ({
    critical: { bg: 'rgba(239,68,68,0.2)',    text: '#F87171', label: 'Kritis' },
    high:     { bg: 'rgba(239,68,68,0.15)',   text: '#F87171', label: 'Tinggi' },
    medium:   { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D', label: 'Sedang' },
    low:      { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: 'Rendah' },
    routine:  { bg: 'rgba(100,116,139,0.1)',  text: '#94A3B8', label: 'Rutin' },
}[priority] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: priority });

// ── Kanban columns ──
const kanbanColumns = computed(() => [
    { key: 'open',           label: 'Open',        accent: '#3B82F6', statuses: ['open', 'assigned'] },
    { key: 'in_progress',    label: 'In Progress', accent: '#F59E0B', statuses: ['in_progress'] },
    { key: 'waiting_review', label: 'Review',      accent: '#8B5CF6', statuses: ['waiting_review', 'revision_needed'] },
    { key: 'completed',      label: 'Selesai',     accent: '#10B981', statuses: ['completed', 'closed'] },
    { key: 'draft',          label: 'Draft',       accent: '#64748B', statuses: ['draft'] },
]);

const tasksForColumn = (statuses: string[]) => {
    return statuses.flatMap(s => props.tasks_by_status[s] ?? []);
};

// ── List view helpers ──
const isOverdue = (task: Task) => {
    if (!task.due_date) return false;
    return new Date(task.due_date) < new Date() && !['completed', 'cancelled', 'closed'].includes(task.status);
};

const formatDue = (dateStr?: string) => {
    if (!dateStr) return null;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const due = new Date(dateStr); due.setHours(0, 0, 0, 0);
    if (due < today) return { label: 'Terlambat', urgent: true };
    if (due.getTime() === today.getTime()) return { label: 'Hari ini', urgent: true };
    const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
    if (due.getTime() === tomorrow.getTime()) return { label: 'Besok', urgent: false };
    return {
        label: new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }),
        urgent: false,
    };
};

const initials = (name: string) =>
    name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Tasks</span>
        </template>

        <div class="space-y-4 max-w-[1400px]">

            <!-- ── Page header ── -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Manajemen Task</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">
                        {{ all_tasks.meta.total }} task total
                    </p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Buat Task
                </button>
            </div>

            <!-- ── Toolbar: View toggle + Filters ── -->
            <div
                class="flex items-center gap-3 flex-wrap p-3 rounded-xl"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <!-- View toggle -->
                <div class="flex rounded-lg overflow-hidden border" style="border-color: var(--border-color);">
                    <button
                        @click="viewMode = 'kanban'"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors"
                        :style="viewMode === 'kanban'
                            ? 'background: #3B82F6; color: white;'
                            : 'color: var(--text-secondary); background: var(--bg-tertiary);'"
                    >
                        <LayoutGrid :size="14" /> Kanban
                    </button>
                    <button
                        @click="viewMode = 'list'"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors"
                        :style="viewMode === 'list'
                            ? 'background: #3B82F6; color: white;'
                            : 'color: var(--text-secondary); background: var(--bg-tertiary);'"
                    >
                        <List :size="14" /> List
                    </button>
                </div>

                <!-- Search -->
                <div class="relative">
                    <Search :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Cari task..."
                        class="pl-8 pr-3 py-1.5 rounded-lg text-xs border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary); width: 180px;"
                    />
                </div>

                <!-- Status filter -->
                <select
                    v-model="filterStatus"
                    @change="applyFilters"
                    class="px-3 py-1.5 rounded-lg text-xs border outline-none"
                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="open">Open</option>
                    <option value="assigned">Ditugaskan</option>
                    <option value="in_progress">In Progress</option>
                    <option value="waiting_review">Review</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>

                <!-- Priority filter -->
                <select
                    v-model="filterPriority"
                    @change="applyFilters"
                    class="px-3 py-1.5 rounded-lg text-xs border outline-none"
                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-secondary);"
                >
                    <option value="">Semua Prioritas</option>
                    <option value="critical">Kritis</option>
                    <option value="high">Tinggi</option>
                    <option value="medium">Sedang</option>
                    <option value="low">Rendah</option>
                    <option value="routine">Rutin</option>
                </select>

                <!-- Clear filters -->
                <button
                    v-if="filterStatus || filterPriority || searchQuery"
                    @click="filterStatus = ''; filterPriority = ''; searchQuery = ''; applyFilters()"
                    class="flex items-center gap-1 px-2 py-1.5 text-xs rounded-lg transition-colors"
                    style="color: var(--text-muted);"
                >
                    <X :size="12" /> Reset
                </button>
            </div>

            <!-- ── KANBAN VIEW ── -->
            <div v-if="viewMode === 'kanban'" class="flex gap-3 overflow-x-auto pb-4">
                <div
                    v-for="col in kanbanColumns"
                    :key="col.key"
                    class="flex-shrink-0 w-[260px] rounded-xl flex flex-col"
                    style="background: var(--bg-tertiary); border: 1px solid var(--border-color);"
                >
                    <!-- Column header -->
                    <div
                        class="px-3 py-2.5 flex items-center gap-2 border-b rounded-t-xl"
                        :style="`border-top: 2px solid ${col.accent}; border-bottom-color: var(--border-color);`"
                    >
                        <span class="text-xs font-semibold" style="color: var(--text-primary);">{{ col.label }}</span>
                        <span
                            class="ml-auto text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                            :style="`background: ${col.accent}22; color: ${col.accent};`"
                        >{{ tasksForColumn(col.statuses).length }}</span>
                    </div>

                    <!-- Cards -->
                    <div class="p-2 space-y-2 flex-1 overflow-y-auto max-h-[calc(100vh-280px)]">
                        <Link
                            v-for="task in tasksForColumn(col.statuses)"
                            :key="task.id"
                            :href="`/tasks/${task.id}`"
                            class="block rounded-md p-3 transition-shadow hover:shadow-md cursor-pointer"
                            :style="`background: var(--card-bg); border-left: 2px solid ${col.accent};`"
                        >
                            <!-- Title -->
                            <p
                                class="text-xs font-medium leading-snug mb-2"
                                style="color: var(--text-primary); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
                            >{{ task.title }}</p>

                            <!-- Badges -->
                            <div class="flex items-center gap-1.5 flex-wrap mb-2">
                                <span
                                    class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                    :style="`background: ${priorityBadge(task.priority).bg}; color: ${priorityBadge(task.priority).text};`"
                                >{{ priorityBadge(task.priority).label }}</span>
                                <span
                                    class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                    :style="`background: ${statusBadge(task.status).bg}; color: ${statusBadge(task.status).text};`"
                                >{{ statusBadge(task.status).label }}</span>
                            </div>

                            <!-- Footer: assignee + due date -->
                            <div class="flex items-center justify-between gap-2 mt-2">
                                <div v-if="task.assignee" class="flex items-center gap-1">
                                    <div
                                        class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                                        style="background: #3B82F6;"
                                    >{{ initials(task.assignee.name) }}</div>
                                    <span class="text-[10px] truncate max-w-[80px]" style="color: var(--text-muted);">
                                        {{ task.assignee.name.split(' ')[0] }}
                                    </span>
                                </div>
                                <div v-else class="flex items-center gap-1">
                                    <User :size="12" style="color: var(--text-muted);" />
                                    <span class="text-[10px]" style="color: var(--text-muted);">Belum ditugaskan</span>
                                </div>
                                <div v-if="task.due_date" class="flex items-center gap-1 shrink-0">
                                    <AlertCircle
                                        v-if="isOverdue(task)"
                                        :size="10"
                                        style="color: #EF4444;"
                                    />
                                    <Calendar v-else :size="10" style="color: var(--text-muted);" />
                                    <span
                                        class="text-[10px]"
                                        :style="isOverdue(task) ? 'color: #EF4444;' : 'color: var(--text-muted);'"
                                    >{{ formatDue(task.due_date)?.label }}</span>
                                </div>
                            </div>
                        </Link>

                        <!-- Empty column -->
                        <div
                            v-if="tasksForColumn(col.statuses).length === 0"
                            class="text-center py-6 text-xs"
                            style="color: var(--text-muted);"
                        >Tidak ada task</div>
                    </div>
                </div>
            </div>

            <!-- ── LIST VIEW ── -->
            <div
                v-else
                class="rounded-xl overflow-hidden"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <!-- Table header -->
                <div
                    class="grid grid-cols-[1fr_160px_100px_120px_110px_80px] px-4 py-2.5 border-b text-xs font-semibold"
                    style="color: var(--text-muted); border-color: var(--border-color); background: var(--bg-tertiary);"
                >
                    <span>Judul</span>
                    <span>Assignee</span>
                    <span>Prioritas</span>
                    <span>Status</span>
                    <span>Jatuh Tempo</span>
                    <span class="text-right">Aksi</span>
                </div>

                <!-- Rows -->
                <template v-if="all_tasks.data.length">
                    <Link
                        v-for="task in all_tasks.data"
                        :key="task.id"
                        :href="`/tasks/${task.id}`"
                        class="grid grid-cols-[1fr_160px_100px_120px_110px_80px] px-4 py-3 border-b items-center transition-colors hover:opacity-90"
                        :style="`border-color: var(--border-color); ${isOverdue(task) ? 'background: rgba(239,68,68,0.04);' : ''}`"
                    >
                        <!-- Title -->
                        <span class="text-sm font-medium truncate pr-4" style="color: var(--text-primary);">
                            {{ task.title }}
                        </span>

                        <!-- Assignee -->
                        <div class="flex items-center gap-1.5">
                            <div
                                v-if="task.assignee"
                                class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                style="background: #3B82F6;"
                            >{{ initials(task.assignee.name) }}</div>
                            <span class="text-xs truncate" style="color: var(--text-secondary);">
                                {{ task.assignee?.name ?? '—' }}
                            </span>
                        </div>

                        <!-- Priority -->
                        <span
                            class="text-[11px] font-medium px-2 py-0.5 rounded-full inline-block"
                            :style="`background: ${priorityBadge(task.priority).bg}; color: ${priorityBadge(task.priority).text};`"
                        >{{ priorityBadge(task.priority).label }}</span>

                        <!-- Status -->
                        <span
                            class="text-[11px] font-medium px-2 py-0.5 rounded-full inline-block"
                            :style="`background: ${statusBadge(task.status).bg}; color: ${statusBadge(task.status).text};`"
                        >{{ statusBadge(task.status).label }}</span>

                        <!-- Due date -->
                        <span
                            class="text-xs"
                            :style="isOverdue(task) ? 'color: #EF4444;' : 'color: var(--text-muted);'"
                        >
                            {{ task.due_date ? formatDue(task.due_date)?.label : '—' }}
                        </span>

                        <!-- Action -->
                        <div class="flex justify-end">
                            <ChevronRight :size="16" style="color: var(--text-muted);" />
                        </div>
                    </Link>
                </template>
                <div v-else class="px-4 py-12 text-center text-sm" style="color: var(--text-muted);">
                    Belum ada task.
                    <button @click="showCreateModal = true" style="color: #3B82F6;" class="font-medium">Buat sekarang!</button>
                </div>
            </div>

        </div>

        <!-- ── Create Task Slide-over ── -->
        <Teleport to="body">
            <div
                v-if="showCreateModal"
                class="fixed inset-0 z-50 flex justify-end"
            >
                <!-- Overlay -->
                <div
                    class="absolute inset-0"
                    style="background: rgba(0,0,0,0.4);"
                    @click="showCreateModal = false"
                />

                <!-- Panel -->
                <div
                    class="relative z-10 w-[480px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Buat Task Baru</h2>
                        <button
                            @click="showCreateModal = false"
                            class="rounded-md p-1 transition-colors hover:opacity-70"
                            style="color: var(--text-muted);"
                        >
                            <X :size="18" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">
                        <!-- Title -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Judul Task <span style="color: #EF4444;">*</span>
                            </label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                placeholder="Masukkan judul task..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                :class="{ 'border-red-500': createForm.errors.title }"
                            />
                            <p v-if="createForm.errors.title" class="mt-1 text-xs" style="color: #EF4444;">
                                {{ createForm.errors.title }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Deskripsi</label>
                            <textarea
                                v-model="createForm.description"
                                rows="3"
                                placeholder="Deskripsi task (opsional)..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Priority -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Prioritas <span style="color: #EF4444;">*</span>
                            </label>
                            <select
                                v-model="createForm.priority"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option value="critical">Kritis</option>
                                <option value="high">Tinggi</option>
                                <option value="medium">Sedang</option>
                                <option value="low">Rendah</option>
                                <option value="routine">Rutin</option>
                            </select>
                        </div>

                        <!-- Assignee -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Tugaskan ke</label>
                            <select
                                v-model="createForm.assignee_id"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option value="">— Pilih pengguna —</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>

                        <!-- Due date -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Jatuh Tempo</label>
                            <input
                                v-model="createForm.due_date"
                                type="date"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                :min="new Date().toISOString().split('T')[0]"
                            />
                            <p v-if="createForm.errors.due_date" class="mt-1 text-xs" style="color: #EF4444;">
                                {{ createForm.errors.due_date }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="createForm.processing ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : 'Buat Task' }}
                            </button>
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                class="px-4 py-2 rounded-lg text-sm font-medium border transition-colors"
                                style="border-color: var(--border-color); color: var(--text-secondary); background: var(--bg-tertiary);"
                            >
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
