<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, Clock, Video, MapPin, Users, X, ChevronRight,
    Monitor, CalendarDays,
} from 'lucide-vue-next';

interface Participant {
    id: string;
    name: string;
}

interface Meeting {
    id: string;
    title: string;
    status: string;
    meeting_mode: string;
    meeting_type: string;
    scheduled_at: string;
    duration_minutes: number;
    location?: string;
    online_url?: string;
    host?: { id: string; name: string };
    participant_count: number;
    participants: Participant[];
}

interface Props {
    meetings: Meeting[];
    filters: { segment?: string };
    users: Array<{ id: string; name: string }>;
}

const props = defineProps<Props>();

const activeSegment = ref(props.filters.segment ?? 'all');
const showCreateSlide = ref(false);

const segments = [
    { key: 'all',      label: 'Semua' },
    { key: 'today',    label: 'Hari Ini' },
    { key: 'upcoming', label: 'Akan Datang' },
    { key: 'past',     label: 'Selesai' },
];

const applySegment = (seg: string) => {
    activeSegment.value = seg;
    router.get('/meetings', { segment: seg === 'all' ? undefined : seg }, {
        preserveState: true, replace: true,
    });
};

// Status badge config
const statusConfig: Record<string, { bg: string; text: string; label: string; accent: string }> = {
    draft:       { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft',       accent: '#64748B' },
    scheduled:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Dijadwalkan', accent: '#3B82F6' },
    confirmed:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Dikonfirmasi',accent: '#3B82F6' },
    in_progress: { bg: 'rgba(16,185,129,0.2)', text: '#34D399', label: 'Berlangsung',  accent: '#10B981' },
    completed:   { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Selesai',     accent: '#64748B' },
    postponed:   { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Ditunda',     accent: '#F59E0B' },
    cancelled:   { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Dibatalkan',  accent: '#EF4444' },
    archived:    { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan',  accent: '#64748B' },
};

const statusBadge = (s: string) =>
    statusConfig[s] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: s, accent: '#64748B' };

const isLive = (m: Meeting) => m.status === 'in_progress';
const isUpcoming = (m: Meeting) => ['scheduled', 'confirmed'].includes(m.status);
const isOnline = (m: Meeting) => ['online', 'hybrid'].includes(m.meeting_mode);

const formatTime = (isoStr: string) =>
    new Date(isoStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

const formatDate = (isoStr: string) =>
    new Date(isoStr).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

const initials = (name: string) =>
    name.split(' ').slice(0, 2).map((w: string) => w[0]).join('').toUpperCase();

// Create form
const createForm = useForm({
    title: '',
    description: '',
    meeting_type: 'internal',
    meeting_mode: 'offline',
    scheduled_at: '',
    duration_minutes: 60,
    location: '',
    online_url: '',
    secretary_id: '',
    participant_ids: [] as string[],
});

const submitCreate = () => {
    createForm.post('/meetings', {
        onSuccess: () => {
            showCreateSlide.value = false;
            createForm.reset();
        },
    });
};

const meetingTypes = [
    { value: 'internal',     label: 'Internal' },
    { value: 'cross_opd',    label: 'Lintas OPD' },
    { value: 'coordination', label: 'Koordinasi' },
    { value: 'briefing',     label: 'Briefing' },
    { value: 'review',       label: 'Review' },
    { value: 'evaluation',   label: 'Evaluasi' },
    { value: 'hearing',      label: 'Hearing' },
    { value: 'external',     label: 'Eksternal' },
    { value: 'one_on_one',   label: 'One-on-One' },
];

const toggleParticipant = (userId: string) => {
    const idx = createForm.participant_ids.indexOf(userId);
    if (idx >= 0) {
        createForm.participant_ids.splice(idx, 1);
    } else {
        createForm.participant_ids.push(userId);
    }
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Meeting</span>
        </template>

        <div class="space-y-4 max-w-[1200px]">

            <!-- Page header -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Meeting Workspace</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ meetings.length }} meeting</p>
                </div>
                <button
                    @click="showCreateSlide = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Jadwalkan Meeting
                </button>
            </div>

            <!-- Segment filter -->
            <div class="flex gap-1 p-1 rounded-xl w-fit" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                <button
                    v-for="seg in segments"
                    :key="seg.key"
                    @click="applySegment(seg.key)"
                    class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
                    :style="activeSegment === seg.key
                        ? 'background: #3B82F6; color: white;'
                        : 'color: var(--text-secondary); background: transparent;'"
                >{{ seg.label }}</button>
            </div>

            <!-- Meeting cards -->
            <div v-if="meetings.length > 0" class="space-y-3">
                <div
                    v-for="m in meetings"
                    :key="m.id"
                    class="rounded-xl overflow-hidden"
                    :style="`background: var(--card-bg); border: 1px solid var(--border-color); border-left: 4px solid ${statusBadge(m.status).accent};`"
                >
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <!-- Left: info -->
                            <div class="flex-1 min-w-0">
                                <!-- Status badge + title -->
                                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                    <span
                                        class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                        :style="`background: ${statusBadge(m.status).bg}; color: ${statusBadge(m.status).text};`"
                                    >{{ statusBadge(m.status).label }}</span>
                                    <span
                                        v-if="isLive(m)"
                                        class="text-[10px] font-bold px-2 py-0.5 rounded-full animate-pulse"
                                        style="background: rgba(16,185,129,0.3); color: #10B981;"
                                    >LIVE</span>
                                </div>

                                <Link
                                    :href="`/meetings/${m.id}`"
                                    class="block text-base font-semibold leading-snug mb-2 hover:opacity-80 transition-opacity"
                                    style="color: var(--text-primary);"
                                >{{ m.title }}</Link>

                                <!-- Meta row -->
                                <div class="flex items-center gap-4 flex-wrap text-xs" style="color: var(--text-muted);">
                                    <div class="flex items-center gap-1.5">
                                        <Clock :size="13" />
                                        <span>{{ formatDate(m.scheduled_at) }} • {{ formatTime(m.scheduled_at) }} ({{ m.duration_minutes }} mnt)</span>
                                    </div>
                                    <div v-if="m.meeting_mode === 'online' || m.meeting_mode === 'hybrid'" class="flex items-center gap-1.5">
                                        <Video :size="13" />
                                        <span>{{ m.meeting_mode === 'hybrid' ? 'Hybrid' : 'Online' }}</span>
                                    </div>
                                    <div v-else class="flex items-center gap-1.5">
                                        <MapPin :size="13" />
                                        <span>{{ m.location || 'Offline' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Users :size="13" />
                                        <span>{{ m.participant_count }} peserta</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: avatar stack + actions -->
                            <div class="flex items-center gap-3 shrink-0">
                                <!-- Avatar stack -->
                                <div class="flex -space-x-2">
                                    <div
                                        v-for="(p, idx) in m.participants.slice(0, 4)"
                                        :key="p.id"
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white border-2 shrink-0"
                                        :style="`background: ${['#3B82F6','#8B5CF6','#10B981','#F59E0B'][idx % 4]}; border-color: var(--card-bg);`"
                                        :title="p.name"
                                    >{{ initials(p.name) }}</div>
                                    <div
                                        v-if="m.participant_count > 4"
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold border-2"
                                        style="background: var(--bg-tertiary); border-color: var(--card-bg); color: var(--text-muted);"
                                    >+{{ m.participant_count - 4 }}</div>
                                </div>

                                <!-- Action buttons -->
                                <a
                                    v-if="isOnline(m) && (isUpcoming(m) || isLive(m)) && m.online_url"
                                    :href="m.online_url"
                                    target="_blank"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-opacity hover:opacity-90"
                                    style="background: #10B981;"
                                >
                                    <Monitor :size="12" /> Bergabung
                                </a>
                                <Link
                                    :href="`/meetings/${m.id}`"
                                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors"
                                    style="border-color: var(--border-color); color: var(--text-secondary); background: var(--bg-tertiary);"
                                >
                                    Detail <ChevronRight :size="12" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="rounded-xl py-16 text-center"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <CalendarDays :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Belum ada meeting</p>
                <p class="text-sm mb-4" style="color: var(--text-muted);">Jadwalkan meeting pertama Anda</p>
                <button
                    @click="showCreateSlide = true"
                    class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >Jadwalkan Meeting</button>
            </div>

        </div>

        <!-- Create Meeting Slide-over -->
        <Teleport to="body">
            <div v-if="showCreateSlide" class="fixed inset-0 z-50 flex justify-end">
                <!-- Overlay -->
                <div
                    class="absolute inset-0"
                    style="background: rgba(0,0,0,0.4);"
                    @click="showCreateSlide = false"
                />

                <!-- Panel -->
                <div
                    class="relative z-10 w-[480px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">Jadwalkan Meeting</h2>
                        <button @click="showCreateSlide = false" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">

                        <!-- Title -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Judul Meeting <span style="color: #EF4444;">*</span>
                            </label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                placeholder="Contoh: Rapat Koordinasi Bidang..."
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
                                placeholder="Tujuan dan konteks meeting..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Type + Mode (2 columns) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Tipe <span style="color: #EF4444;">*</span>
                                </label>
                                <select
                                    v-model="createForm.meeting_type"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option v-for="t in meetingTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Mode <span style="color: #EF4444;">*</span>
                                </label>
                                <select
                                    v-model="createForm.meeting_mode"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option value="offline">Offline</option>
                                    <option value="online">Online</option>
                                    <option value="hybrid">Hybrid</option>
                                </select>
                            </div>
                        </div>

                        <!-- Scheduled at + Duration (2 columns) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Waktu <span style="color: #EF4444;">*</span>
                                </label>
                                <input
                                    v-model="createForm.scheduled_at"
                                    type="datetime-local"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <p v-if="createForm.errors.scheduled_at" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.scheduled_at }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Durasi (menit) <span style="color: #EF4444;">*</span>
                                </label>
                                <input
                                    v-model.number="createForm.duration_minutes"
                                    type="number"
                                    min="15"
                                    step="15"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                            </div>
                        </div>

                        <!-- Location (conditional) -->
                        <div v-if="createForm.meeting_mode === 'offline' || createForm.meeting_mode === 'hybrid'">
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Lokasi</label>
                            <input
                                v-model="createForm.location"
                                type="text"
                                placeholder="Ruang rapat / alamat..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Online URL (conditional) -->
                        <div v-if="createForm.meeting_mode === 'online' || createForm.meeting_mode === 'hybrid'">
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Link Meeting</label>
                            <input
                                v-model="createForm.online_url"
                                type="url"
                                placeholder="https://meet.google.com/..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Secretary -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Sekretaris Meeting</label>
                            <select
                                v-model="createForm.secretary_id"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option value="">— Pilih sekretaris —</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>

                        <!-- Participants multi-select -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Undang Peserta</label>
                            <div
                                class="max-h-[120px] overflow-y-auto rounded-lg border p-2 space-y-1"
                                style="background: var(--bg-tertiary); border-color: var(--border-color);"
                            >
                                <label
                                    v-for="u in users"
                                    :key="u.id"
                                    class="flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer hover:opacity-80"
                                    :style="createForm.participant_ids.includes(u.id) ? 'background: rgba(59,130,246,0.1);' : ''"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="createForm.participant_ids.includes(u.id)"
                                        @change="toggleParticipant(u.id)"
                                        class="accent-blue-500"
                                    />
                                    <span class="text-xs" style="color: var(--text-secondary);">{{ u.name }}</span>
                                </label>
                            </div>
                            <p class="text-[11px] mt-1" style="color: var(--text-muted);">
                                {{ createForm.participant_ids.length }} peserta dipilih
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="createForm.processing ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : 'Jadwalkan Meeting' }}
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
