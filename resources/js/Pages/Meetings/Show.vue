<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import {
    ArrowLeft, Clock, Video, MapPin, Users, Building2,
    Monitor, Plus, CheckCircle2, Circle, ChevronRight,
    FileText, ListChecks, UserCheck, Gavel, Zap, ScrollText, QrCode,
} from 'lucide-vue-next';

interface Participant {
    id: string;
    user_id: string;
    name?: string;
    role: string;
    attendance_status: string;
    check_in_at?: string;
}

interface AgendaItem {
    id: string;
    title: string;
    description?: string;
    duration_minutes?: number;
    sort_order: number;
    is_completed: boolean;
    presenter_id?: string;
}

interface Decision {
    id: string;
    content: string;
    agenda_item_id?: string;
    recorded_by: string;
    recorded_at: string;
}

interface ActionItem {
    id: string;
    title: string;
    description?: string;
    assignee_id?: string;
    assignee_name?: string;
    due_date?: string;
    is_task_created: boolean;
    task_id?: string;
}

interface MeetingMinute {
    id: string;
    content?: string;
    ai_draft?: string;
    status: string;
    approved_by?: string;
    approved_at?: string;
}

interface Meeting {
    id: string;
    title: string;
    description?: string;
    status: string;
    meeting_mode: string;
    meeting_type: string;
    scheduled_at: string;
    duration_minutes: number;
    estimated_end_at?: string;
    actual_start_at?: string;
    actual_end_at?: string;
    location?: string;
    online_url?: string;
    agenda_notes?: string;
    organization_id: string;
    host?: { id: string; name: string };
    secretary?: { id: string; name: string };
    participant_count: number;
    participants: Participant[];
    agenda_items: AgendaItem[];
    decisions: Decision[];
    action_items: ActionItem[];
    minutes?: MeetingMinute;
    created_at: string;
    updated_at: string;
}

interface Props {
    meeting: Meeting;
    can: {
        update: boolean;
        recordMinutes: boolean;
        approveMinutes: boolean;
        createActionItem: boolean;
        transition: boolean;
    };
    users: Array<{ id: string; name: string }>;
}

const props = defineProps<Props>();

const activeTab = ref('overview');

const tabs = [
    { key: 'overview',     label: 'Overview',      icon: FileText },
    { key: 'agenda',       label: 'Agenda',        icon: ListChecks },
    { key: 'participants', label: 'Peserta',        icon: UserCheck },
    { key: 'decisions',    label: 'Keputusan',     icon: Gavel },
    { key: 'action_items', label: 'Action Items',  icon: Zap },
    { key: 'minutes',      label: 'Notulensi',     icon: ScrollText },
];

// Status config
const statusConfig: Record<string, { bg: string; text: string; label: string; accent: string }> = {
    draft:       { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft',        accent: '#64748B' },
    scheduled:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Dijadwalkan',  accent: '#3B82F6' },
    confirmed:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Dikonfirmasi', accent: '#3B82F6' },
    in_progress: { bg: 'rgba(16,185,129,0.2)', text: '#34D399', label: 'Berlangsung',   accent: '#10B981' },
    completed:   { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Selesai',      accent: '#64748B' },
    postponed:   { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Ditunda',      accent: '#F59E0B' },
    cancelled:   { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Dibatalkan',   accent: '#EF4444' },
    archived:    { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan',   accent: '#64748B' },
};

const statusBadge = (s: string) =>
    statusConfig[s] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: s, accent: '#64748B' };

const attendanceConfig: Record<string, { dot: string; label: string }> = {
    invited:    { dot: '#94A3B8', label: 'Diundang' },
    accepted:   { dot: '#3B82F6', label: 'Diterima' },
    declined:   { dot: '#EF4444', label: 'Ditolak' },
    present:    { dot: '#10B981', label: 'Hadir' },
    absent:     { dot: '#EF4444', label: 'Tidak Hadir' },
    late:       { dot: '#F59E0B', label: 'Terlambat' },
    left_early: { dot: '#F59E0B', label: 'Pulang Awal' },
    delegated:  { dot: '#8B5CF6', label: 'Delegasi' },
};

const attendanceDot = (s: string) =>
    attendanceConfig[s] ?? { dot: '#94A3B8', label: s };

// Next status transitions for the button
const nextStatusMap: Record<string, { status: string; label: string }> = {
    draft:     { status: 'scheduled',   label: 'Jadwalkan' },
    scheduled: { status: 'in_progress', label: 'Mulai Meeting' },
    confirmed: { status: 'in_progress', label: 'Mulai Meeting' },
    in_progress: { status: 'completed', label: 'Selesaikan' },
};
const nextStatus = computed(() => nextStatusMap[props.meeting.status] ?? null);

// Helpers
const initials = (name: string) =>
    name.split(' ').slice(0, 2).map((w: string) => w[0]).join('').toUpperCase();

const formatDate = (isoStr: string) =>
    new Date(isoStr).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const formatTime = (isoStr: string) =>
    new Date(isoStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

const formatDateTime = (isoStr: string) =>
    new Date(isoStr).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const isOnline = computed(() => ['online', 'hybrid'].includes(props.meeting.meeting_mode));
const isUpcoming = computed(() => ['scheduled', 'confirmed', 'in_progress'].includes(props.meeting.status));

// Transition form
const transitionForm = useForm({ status: '', reason: '' });
const doTransition = (statusValue: string) => {
    transitionForm.status = statusValue;
    transitionForm.post(`/meetings/${props.meeting.id}/transition`, { preserveScroll: true });
};

// Agenda form
const agendaForm = useForm({ title: '', description: '', duration_minutes: null as number | null, presenter_id: '' });
const showAgendaForm = ref(false);
const submitAgenda = () => {
    agendaForm.post(`/meetings/${props.meeting.id}/agenda`, {
        preserveScroll: true,
        onSuccess: () => { agendaForm.reset(); showAgendaForm.value = false; },
    });
};

// Decision form
const decisionForm = useForm({ content: '', agenda_item_id: '' });
const showDecisionForm = ref(false);
const submitDecision = () => {
    decisionForm.post(`/meetings/${props.meeting.id}/decisions`, {
        preserveScroll: true,
        onSuccess: () => { decisionForm.reset(); showDecisionForm.value = false; },
    });
};

// Action Item form
const actionForm = useForm({
    title: '',
    description: '',
    assignee_id: '',
    due_date: '',
    decision_id: '',
    create_task: false,
});
const showActionForm = ref(false);
const submitActionItem = () => {
    actionForm.post(`/meetings/${props.meeting.id}/action-items`, {
        preserveScroll: true,
        onSuccess: () => { actionForm.reset(); showActionForm.value = false; },
    });
};

// Minutes form
const minutesForm = useForm({ content: props.meeting.minutes?.content ?? '', status: 'draft' });
const submitMinutes = () => {
    minutesForm.post(`/meetings/${props.meeting.id}/minutes`, { preserveScroll: true });
};

const approveMinutes = () => {
    if (!props.meeting.minutes) return;
    router.post(`/meetings/minutes/${props.meeting.minutes.id}/approve`, {}, { preserveScroll: true });
};

// Attendance
const attendanceForm = useForm({ attendance_status: '' });
const recordAttendance = (participantId: string, status: string) => {
    router.patch(`/meetings/participants/${participantId}/attendance`, { attendance_status: status }, { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/meetings" style="color: var(--text-muted);" class="hover:opacity-80">Meeting</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium truncate max-w-[200px]">{{ meeting.title }}</span>
        </template>

        <div class="max-w-[1200px] space-y-4">

            <!-- Header card -->
            <div
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <!-- Back + actions -->
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <Link href="/meetings" class="flex items-center gap-1.5 text-sm font-medium hover:opacity-80" style="color: #3B82F6;">
                        <ArrowLeft :size="16" /> Kembali ke Meeting
                    </Link>
                    <div class="flex items-center gap-2">
                        <!-- Join button (if online & upcoming/live) -->
                        <a
                            v-if="isOnline && isUpcoming && meeting.online_url"
                            :href="meeting.online_url"
                            target="_blank"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                            style="background: #10B981;"
                        >
                            <Monitor :size="14" /> Bergabung ke Meeting
                        </a>
                        <!-- Transition button (host only) -->
                        <button
                            v-if="can.transition && nextStatus"
                            @click="doTransition(nextStatus.status)"
                            :disabled="transitionForm.processing"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90 transition-opacity"
                            style="background: #3B82F6;"
                        >{{ nextStatus.label }}</button>
                        <!-- Cancel button -->
                        <button
                            v-if="can.transition && !['completed','archived','cancelled'].includes(meeting.status)"
                            @click="doTransition('cancelled')"
                            class="px-3 py-2 rounded-lg text-sm border hover:opacity-80"
                            style="border-color: var(--border-color); color: #F87171; background: var(--bg-tertiary);"
                        >Batalkan</button>
                    </div>
                </div>

                <!-- Status badge + title -->
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <span
                        class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        :style="`background: ${statusBadge(meeting.status).bg}; color: ${statusBadge(meeting.status).text};`"
                    >{{ statusBadge(meeting.status).label }}</span>
                    <span
                        v-if="meeting.status === 'in_progress'"
                        class="text-[10px] font-bold px-2 py-1 rounded-full animate-pulse"
                        style="background: rgba(16,185,129,0.3); color: #10B981;"
                    >BERLANGSUNG</span>
                </div>

                <h1 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">{{ meeting.title }}</h1>

                <!-- Meta row -->
                <div class="flex items-center gap-5 flex-wrap text-sm" style="color: var(--text-secondary);">
                    <div class="flex items-center gap-1.5">
                        <Clock :size="15" style="color: var(--text-muted);" />
                        <span>{{ formatDate(meeting.scheduled_at) }} • {{ formatTime(meeting.scheduled_at) }} ({{ meeting.duration_minutes }} mnt)</span>
                    </div>
                    <div v-if="isOnline" class="flex items-center gap-1.5">
                        <Video :size="15" style="color: var(--text-muted);" />
                        <a v-if="meeting.online_url" :href="meeting.online_url" target="_blank" style="color: #3B82F6;" class="hover:underline">Link Meeting</a>
                        <span v-else>Online</span>
                    </div>
                    <div v-if="meeting.location" class="flex items-center gap-1.5">
                        <MapPin :size="15" style="color: var(--text-muted);" />
                        <span>{{ meeting.location }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Users :size="15" style="color: var(--text-muted);" />
                        <span>{{ meeting.participant_count }} peserta</span>
                    </div>
                    <div v-if="meeting.host" class="flex items-center gap-1.5">
                        <Building2 :size="15" style="color: var(--text-muted);" />
                        <span>Host: {{ meeting.host.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Main grid: tabs + sidebar -->
            <div class="grid lg:grid-cols-[1fr_280px] gap-4 items-start">

                <!-- Left: tabs -->
                <div class="rounded-xl overflow-hidden" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">

                    <!-- Tab bar (scrollable) -->
                    <div class="flex border-b overflow-x-auto" style="border-color: var(--border-color);">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap shrink-0"
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
                                v-if="meeting.description"
                                class="rounded-lg p-4 text-sm leading-relaxed whitespace-pre-wrap mb-4"
                                style="background: var(--bg-tertiary); color: var(--text-secondary);"
                            >{{ meeting.description }}</div>
                            <p v-else class="text-sm mb-4" style="color: var(--text-muted);">Tidak ada deskripsi.</p>

                            <!-- Agenda notes -->
                            <div v-if="meeting.agenda_notes" class="mt-3">
                                <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);">Catatan Agenda</p>
                                <div
                                    class="rounded-lg p-3 text-sm whitespace-pre-wrap"
                                    style="background: var(--bg-tertiary); color: var(--text-secondary);"
                                >{{ meeting.agenda_notes }}</div>
                            </div>
                        </div>

                        <!-- Agenda -->
                        <div v-else-if="activeTab === 'agenda'">
                            <div class="space-y-2 mb-4">
                                <div
                                    v-for="(item, idx) in meeting.agenda_items"
                                    :key="item.id"
                                    class="flex gap-3 rounded-lg p-3"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <div
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                        :style="item.is_completed
                                            ? 'background: rgba(16,185,129,0.2); color: #10B981;'
                                            : 'background: rgba(59,130,246,0.2); color: #60A5FA;'"
                                    >{{ idx + 1 }}</div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle2 v-if="item.is_completed" :size="14" style="color: #10B981;" />
                                            <Circle v-else :size="14" style="color: var(--text-muted);" />
                                            <span class="text-sm font-medium" :style="item.is_completed ? 'color: var(--text-muted); text-decoration: line-through;' : 'color: var(--text-primary);'">
                                                {{ item.title }}
                                            </span>
                                            <span v-if="item.duration_minutes" class="text-xs ml-auto shrink-0" style="color: var(--text-muted);">{{ item.duration_minutes }} mnt</span>
                                        </div>
                                        <p v-if="item.description" class="text-xs mt-1" style="color: var(--text-muted);">{{ item.description }}</p>
                                    </div>
                                </div>
                                <p v-if="!meeting.agenda_items.length" class="text-sm" style="color: var(--text-muted);">Belum ada agenda.</p>
                            </div>

                            <!-- Add agenda form -->
                            <div v-if="can.update">
                                <div v-if="!showAgendaForm">
                                    <button @click="showAgendaForm = true" class="flex items-center gap-2 text-sm font-medium hover:opacity-80" style="color: #3B82F6;">
                                        <Plus :size="15" /> Tambah Agenda
                                    </button>
                                </div>
                                <form v-else @submit.prevent="submitAgenda" class="space-y-3 p-4 rounded-lg" style="background: var(--bg-tertiary);">
                                    <input v-model="agendaForm.title" type="text" placeholder="Judul agenda..." class="w-full px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" />
                                    <div class="grid grid-cols-2 gap-3">
                                        <input v-model="agendaForm.description" type="text" placeholder="Deskripsi (opsional)" class="px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" />
                                        <input v-model.number="agendaForm.duration_minutes" type="number" min="1" placeholder="Durasi (mnt)" class="px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" :disabled="agendaForm.processing" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: #3B82F6;">Simpan</button>
                                        <button type="button" @click="showAgendaForm = false; agendaForm.reset()" class="px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Participants -->
                        <div v-else-if="activeTab === 'participants'">
                            <!-- QR button: host only, meeting scheduled/confirmed/in_progress -->
                            <div
                                v-if="can.update && ['scheduled','confirmed','in_progress'].includes(meeting.status)"
                                class="flex justify-end mb-3"
                            >
                                <Link
                                    :href="`/meetings/${meeting.id}/checkin-qr`"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white hover:opacity-90"
                                    style="background: #3B82F6;"
                                >
                                    <QrCode :size="13" /> Tampilkan QR Absensi
                                </Link>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="p in meeting.participants"
                                    :key="p.id"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                        style="background: #3B82F6;"
                                    >{{ initials(p.name ?? '?') }}</div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium" style="color: var(--text-primary);">{{ p.name ?? 'Unknown' }}</p>
                                        <p class="text-xs capitalize" style="color: var(--text-muted);">{{ p.role }}</p>
                                    </div>
                                    <!-- Attendance status dot + label + check_in_at -->
                                    <div class="flex flex-col items-end gap-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <div
                                                class="w-2.5 h-2.5 rounded-full"
                                                :style="`background: ${attendanceDot(p.attendance_status).dot};`"
                                            ></div>
                                            <span class="text-xs" style="color: var(--text-muted);">{{ attendanceDot(p.attendance_status).label }}</span>
                                        </div>
                                        <span v-if="p.check_in_at" class="text-[10px]" style="color: var(--text-muted);">
                                            {{ formatTime(p.check_in_at) }}
                                        </span>
                                    </div>
                                    <!-- Record attendance (if can) -->
                                    <div v-if="can.update && meeting.status === 'in_progress'" class="shrink-0">
                                        <select
                                            @change="(e) => recordAttendance(p.id, (e.target as HTMLSelectElement).value)"
                                            class="text-xs px-2 py-1 rounded border outline-none"
                                            style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                                        >
                                            <option value="">Ubah</option>
                                            <option v-for="(cfg, key) in attendanceConfig" :key="key" :value="key">{{ cfg.label }}</option>
                                        </select>
                                    </div>
                                </div>
                                <p v-if="!meeting.participants.length" class="text-sm" style="color: var(--text-muted);">Belum ada peserta.</p>
                            </div>
                        </div>

                        <!-- Decisions -->
                        <div v-else-if="activeTab === 'decisions'">
                            <div class="space-y-3 mb-4">
                                <div
                                    v-for="(d, idx) in meeting.decisions"
                                    :key="d.id"
                                    class="rounded-lg p-3"
                                    style="background: var(--bg-tertiary); border-left: 3px solid #8B5CF6;"
                                >
                                    <div class="flex items-start gap-2">
                                        <span class="text-xs font-bold shrink-0 mt-0.5" style="color: #8B5CF6;">{{ idx + 1 }}.</span>
                                        <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ d.content }}</p>
                                    </div>
                                    <p class="text-xs mt-2" style="color: var(--text-muted);">{{ formatDateTime(d.recorded_at) }}</p>
                                </div>
                                <p v-if="!meeting.decisions.length" class="text-sm" style="color: var(--text-muted);">Belum ada keputusan dicatat.</p>
                            </div>

                            <!-- Add decision form -->
                            <div v-if="can.recordMinutes">
                                <div v-if="!showDecisionForm">
                                    <button @click="showDecisionForm = true" class="flex items-center gap-2 text-sm font-medium hover:opacity-80" style="color: #8B5CF6;">
                                        <Plus :size="15" /> Catat Keputusan
                                    </button>
                                </div>
                                <form v-else @submit.prevent="submitDecision" class="space-y-3 p-4 rounded-lg" style="background: var(--bg-tertiary);">
                                    <textarea v-model="decisionForm.content" rows="3" placeholder="Isi keputusan rapat..." class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" />
                                    <select v-model="decisionForm.agenda_item_id" class="w-full px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                        <option value="">— Terkait agenda (opsional) —</option>
                                        <option v-for="a in meeting.agenda_items" :key="a.id" :value="a.id">{{ a.title }}</option>
                                    </select>
                                    <div class="flex gap-2">
                                        <button type="submit" :disabled="decisionForm.processing" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: #8B5CF6;">Simpan</button>
                                        <button type="button" @click="showDecisionForm = false; decisionForm.reset()" class="px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Action Items -->
                        <div v-else-if="activeTab === 'action_items'">
                            <div class="space-y-2 mb-4">
                                <div
                                    v-for="ai in meeting.action_items"
                                    :key="ai.id"
                                    class="flex gap-3 rounded-lg p-3"
                                    :style="`background: var(--bg-tertiary); border-left: 3px solid ${ai.is_task_created ? '#10B981' : '#F59E0B'};`"
                                >
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <p class="text-sm font-medium" style="color: var(--text-primary);">{{ ai.title }}</p>
                                            <span
                                                v-if="ai.is_task_created"
                                                class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                                style="background: rgba(16,185,129,0.2); color: #10B981;"
                                            >Task Dibuat</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs" style="color: var(--text-muted);">
                                            <span v-if="ai.assignee_name">{{ ai.assignee_name }}</span>
                                            <span v-if="ai.due_date">Jatuh tempo: {{ ai.due_date }}</span>
                                        </div>
                                    </div>
                                    <Link
                                        v-if="ai.task_id"
                                        :href="`/tasks/${ai.task_id}`"
                                        class="flex items-center gap-1 text-xs hover:opacity-80 shrink-0"
                                        style="color: #3B82F6;"
                                    >Lihat Task <ChevronRight :size="12" /></Link>
                                </div>
                                <p v-if="!meeting.action_items.length" class="text-sm" style="color: var(--text-muted);">Belum ada action item.</p>
                            </div>

                            <!-- Add action item form -->
                            <div v-if="can.createActionItem">
                                <div v-if="!showActionForm">
                                    <button @click="showActionForm = true" class="flex items-center gap-2 text-sm font-medium hover:opacity-80" style="color: #F59E0B;">
                                        <Plus :size="15" /> Tambah Action Item
                                    </button>
                                </div>
                                <form v-else @submit.prevent="submitActionItem" class="space-y-3 p-4 rounded-lg" style="background: var(--bg-tertiary);">
                                    <input v-model="actionForm.title" type="text" placeholder="Judul action item..." class="w-full px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" />
                                    <div class="grid grid-cols-2 gap-3">
                                        <select v-model="actionForm.assignee_id" class="px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                            <option value="">— Assignee —</option>
                                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                        </select>
                                        <input v-model="actionForm.due_date" type="date" class="px-3 py-2 rounded-lg text-sm border outline-none" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);" />
                                    </div>
                                    <!-- Buat task otomatis checkbox -->
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="actionForm.create_task" class="accent-blue-500 w-4 h-4" />
                                        <span class="text-sm" style="color: var(--text-secondary);">Buat task otomatis dari action item ini</span>
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="submit" :disabled="actionForm.processing" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: #F59E0B;">Simpan</button>
                                        <button type="button" @click="showActionForm = false; actionForm.reset()" class="px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Notulensi -->
                        <div v-else-if="activeTab === 'minutes'">
                            <!-- Status -->
                            <div v-if="meeting.minutes" class="flex items-center gap-2 mb-4">
                                <span
                                    class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                    :style="meeting.minutes.status === 'approved'
                                        ? 'background: rgba(16,185,129,0.2); color: #10B981;'
                                        : 'background: rgba(245,158,11,0.2); color: #FCD34D;'"
                                >{{ meeting.minutes.status === 'approved' ? 'Disetujui' : 'Draft' }}</span>
                                <span v-if="meeting.minutes.approved_at" class="text-xs" style="color: var(--text-muted);">
                                    {{ formatDateTime(meeting.minutes.approved_at) }}
                                </span>
                            </div>

                            <!-- Content editor (if can record) -->
                            <div v-if="can.recordMinutes" class="space-y-3 mb-4">
                                <RichTextEditor
                                    v-model="minutesForm.content"
                                    placeholder="Tulis notulensi rapat di sini..."
                                />
                                <div class="flex gap-2">
                                    <button @click="submitMinutes" :disabled="minutesForm.processing" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: #3B82F6;">
                                        {{ minutesForm.processing ? 'Menyimpan...' : 'Simpan Notulensi' }}
                                    </button>
                                    <button
                                        v-if="can.approveMinutes && meeting.minutes && meeting.minutes.status !== 'approved'"
                                        @click="approveMinutes"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                                        style="background: #10B981;"
                                    >Setujui Notulensi</button>
                                </div>
                            </div>

                            <!-- Read-only content (HTML from rich text) -->
                            <div
                                v-else-if="meeting.minutes?.content"
                                class="prose-rte rounded-lg p-4 text-sm leading-relaxed"
                                style="background: var(--bg-tertiary); color: var(--text-secondary);"
                                v-html="meeting.minutes.content"
                            />

                            <!-- Approve only -->
                            <div v-if="!can.recordMinutes && can.approveMinutes && meeting.minutes && meeting.minutes.status !== 'approved'" class="mt-3">
                                <button @click="approveMinutes" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: #10B981;">
                                    Setujui Notulensi
                                </button>
                            </div>

                            <p v-if="!meeting.minutes && !can.recordMinutes" class="text-sm" style="color: var(--text-muted);">Notulensi belum dibuat.</p>
                        </div>

                    </div>
                </div>

                <!-- Right: sidebar -->
                <div class="space-y-4">
                    <!-- Meeting info card -->
                    <div class="rounded-xl p-4 space-y-3" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                        <h3 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Info Meeting</h3>

                        <div>
                            <p class="text-xs" style="color: var(--text-muted);">Host</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0" style="background: #3B82F6;">
                                    {{ initials(meeting.host?.name ?? '?') }}
                                </div>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ meeting.host?.name ?? '—' }}</span>
                            </div>
                        </div>

                        <div v-if="meeting.secretary">
                            <p class="text-xs" style="color: var(--text-muted);">Sekretaris</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0" style="background: #8B5CF6;">
                                    {{ initials(meeting.secretary.name) }}
                                </div>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ meeting.secretary.name }}</span>
                            </div>
                        </div>

                        <div class="border-t pt-3 space-y-2" style="border-color: var(--border-color);">
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Tipe</span>
                                <span class="capitalize" style="color: var(--text-secondary);">{{ meeting.meeting_type }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Mode</span>
                                <span class="capitalize" style="color: var(--text-secondary);">{{ meeting.meeting_mode }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Agenda</span>
                                <span style="color: var(--text-secondary);">{{ meeting.agenda_items.length }} item</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Keputusan</span>
                                <span style="color: var(--text-secondary);">{{ meeting.decisions.length }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Action Items</span>
                                <span style="color: var(--text-secondary);">{{ meeting.action_items.length }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Participants summary -->
                    <div class="rounded-xl p-4" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                        <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-muted);">
                            Peserta ({{ meeting.participant_count }})
                        </h3>
                        <div class="space-y-2">
                            <div
                                v-for="p in meeting.participants.slice(0, 6)"
                                :key="p.id"
                                class="flex items-center gap-2"
                            >
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0" style="background: #3B82F6;">
                                    {{ initials(p.name ?? '?') }}
                                </div>
                                <span class="text-xs flex-1 truncate" style="color: var(--text-secondary);">{{ p.name }}</span>
                                <div class="w-2 h-2 rounded-full shrink-0" :style="`background: ${attendanceDot(p.attendance_status).dot};`"></div>
                            </div>
                            <p v-if="meeting.participant_count > 6" class="text-xs" style="color: var(--text-muted);">
                                +{{ meeting.participant_count - 6 }} lainnya
                            </p>
                        </div>
                    </div>

                    <!-- Status transitions sidebar -->
                    <div
                        v-if="can.transition && !['completed','archived','cancelled'].includes(meeting.status)"
                        class="rounded-xl p-4"
                        style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                    >
                        <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-muted);">Transisi Status</h3>
                        <div class="space-y-1.5">
                            <button
                                v-if="meeting.status === 'draft'"
                                @click="doTransition('scheduled')"
                                class="w-full py-2 px-3 rounded-lg text-sm font-medium text-left text-white hover:opacity-90"
                                style="background: #3B82F6;"
                            >Jadwalkan</button>
                            <button
                                v-if="['scheduled','confirmed'].includes(meeting.status)"
                                @click="doTransition('in_progress')"
                                class="w-full py-2 px-3 rounded-lg text-sm font-medium text-left text-white hover:opacity-90"
                                style="background: #10B981;"
                            >Mulai Meeting</button>
                            <button
                                v-if="meeting.status === 'in_progress'"
                                @click="doTransition('completed')"
                                class="w-full py-2 px-3 rounded-lg text-sm font-medium text-left text-white hover:opacity-90"
                                style="background: #64748B;"
                            >Selesaikan Meeting</button>
                            <button
                                v-if="['scheduled','confirmed'].includes(meeting.status)"
                                @click="doTransition('postponed')"
                                class="w-full py-2 px-3 rounded-lg text-sm font-medium text-left hover:opacity-90"
                                style="background: rgba(245,158,11,0.2); color: #FCD34D;"
                            >Tunda Meeting</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
