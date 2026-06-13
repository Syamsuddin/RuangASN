<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Calendar, ChevronLeft, ChevronRight, Plus, Clock,
    MapPin, CheckSquare, X,
} from 'lucide-vue-next';

// ── Types ──────────────────────────────────────────────────────────────────

interface FeedEvent {
    id: string;
    source: 'event' | 'meeting' | 'task';
    calendar_type: string;
    title: string;
    start: string;
    end: string;
    all_day: boolean;
    color: string;
    url: string | null;
}

interface Props {
    events: FeedEvent[];
    currentMonth: string; // 'YYYY-MM'
    view: string;
    filters: Record<string, string>;
}

const props = defineProps<Props>();

// ── State ──────────────────────────────────────────────────────────────────

const activeView    = ref<'month' | 'week' | 'agenda'>(
    (props.view as 'month' | 'week' | 'agenda') ?? 'month'
);
const showCreate    = ref(false);
const prefillDate   = ref('');
const selectedEvent = ref<FeedEvent | null>(null);

// ── Month navigation ───────────────────────────────────────────────────────

const currentMonthDate = computed(() => {
    const [y, m] = props.currentMonth.split('-').map(Number);
    return new Date(y, m - 1, 1);
});

const monthLabel = computed(() =>
    currentMonthDate.value.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
);

const navigate = (delta: number) => {
    const d = new Date(currentMonthDate.value);
    d.setMonth(d.getMonth() + delta);
    const ym = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    router.get('/calendar', { month: ym, view: activeView.value }, { preserveState: true, replace: true });
};

const goToday = () => {
    const now = new Date();
    const ym  = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    router.get('/calendar', { month: ym, view: activeView.value }, { preserveState: true, replace: true });
};

const switchView = (v: 'month' | 'week' | 'agenda') => {
    activeView.value = v;
    router.get('/calendar', { month: props.currentMonth, view: v }, { preserveState: true, replace: true });
};

// ── Month grid ─────────────────────────────────────────────────────────────

const WEEK_DAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const monthGrid = computed(() => {
    const year  = currentMonthDate.value.getFullYear();
    const month = currentMonthDate.value.getMonth();

    const first = new Date(year, month, 1);
    // Monday = 0 offset: getDay() returns 0=Sun,1=Mon…6=Sat → adjust
    const startOffset = (first.getDay() + 6) % 7; // 0=Mon
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const prevDays    = new Date(year, month, 0).getDate();

    const cells: Array<{ date: Date; isCurrentMonth: boolean }> = [];

    // Previous month tail
    for (let i = startOffset - 1; i >= 0; i--) {
        cells.push({ date: new Date(year, month - 1, prevDays - i), isCurrentMonth: false });
    }
    // Current month
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push({ date: new Date(year, month, d), isCurrentMonth: true });
    }
    // Next month head (fill to complete last row)
    let next = 1;
    while (cells.length % 7 !== 0) {
        cells.push({ date: new Date(year, month + 1, next++), isCurrentMonth: false });
    }

    return cells;
});

const todayStr = new Date().toDateString();

const isToday = (date: Date) => date.toDateString() === todayStr;

const formatDateKey = (date: Date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const eventsForDate = (date: Date): FeedEvent[] => {
    const key = formatDateKey(date);
    return props.events.filter(e => {
        const start = e.start.substring(0, 10);
        const end   = e.end.substring(0, 10);
        return start <= key && key <= end;
    });
};

// ── Agenda view ────────────────────────────────────────────────────────────

const agendaGroups = computed(() => {
    const grouped: Record<string, FeedEvent[]> = {};
    props.events.forEach(e => {
        const key = e.start.substring(0, 10);
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(e);
    });
    return Object.entries(grouped)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([dateKey, evts]) => ({
            dateKey,
            label: new Date(dateKey + 'T00:00:00').toLocaleDateString('id-ID', {
                weekday: 'long', day: 'numeric', month: 'long',
            }),
            events: evts,
        }));
});

// ── Helpers ─────────────────────────────────────────────────────────────────

const formatTime = (iso: string) =>
    new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

const sourceLabel = (source: string) => {
    if (source === 'meeting') return 'Meeting';
    if (source === 'task')    return 'Task';
    return 'Acara';
};

const typeLabel = (type: string) => {
    const map: Record<string, string> = {
        personal: 'Pribadi', team: 'Tim', project: 'Proyek',
        meeting: 'Meeting', org: 'Organisasi', government: 'Pemerintah', holiday: 'Libur',
    };
    return map[type] ?? type;
};

const openCreateForDay = (date: Date) => {
    const y  = date.getFullYear();
    const m  = String(date.getMonth() + 1).padStart(2, '0');
    const d  = String(date.getDate()).padStart(2, '0');
    const dt = `${y}-${m}-${d}T09:00`;
    prefillDate.value = dt;
    createForm.start_at = dt;
    createForm.end_at   = `${y}-${m}-${d}T10:00`;
    showCreate.value = true;
};

const handleChipClick = (ev: FeedEvent) => {
    if (ev.url) {
        router.visit(ev.url);
    } else {
        selectedEvent.value = ev;
        createForm.title         = ev.title;
        createForm.calendar_type = ev.calendar_type;
        createForm.start_at      = ev.start.substring(0, 16);
        createForm.end_at        = ev.end.substring(0, 16);
        showCreate.value = true;
    }
};

// ── Create form ────────────────────────────────────────────────────────────

const calendarTypes = [
    { value: 'personal',   label: 'Pribadi' },
    { value: 'team',       label: 'Tim' },
    { value: 'project',    label: 'Proyek' },
    { value: 'org',        label: 'Organisasi' },
    { value: 'government', label: 'Pemerintah' },
    { value: 'holiday',    label: 'Hari Libur' },
];

const colorSwatches = ['#3B82F6', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#06B6D4'];

const createForm = useForm({
    title:         '',
    description:   '',
    calendar_type: 'personal',
    location:      '',
    start_at:      '',
    end_at:        '',
    all_day:       false,
    color:         '#8B5CF6',
    is_public:     false,
});

const closeSlide = () => {
    showCreate.value  = false;
    selectedEvent.value = null;
    createForm.reset();
};

const submitCreate = () => {
    if (selectedEvent.value) {
        createForm.patch(`/calendar/${selectedEvent.value.id}`, {
            onSuccess: closeSlide,
        });
    } else {
        createForm.post('/calendar', {
            onSuccess: closeSlide,
        });
    }
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Kalender</span>
        </template>

        <div class="space-y-4 max-w-[1400px]">

            <!-- ── Page header ── -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Kalender</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">Acara, Meeting, dan Task Anda</p>
                </div>
                <button
                    @click="showCreate = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Buat Acara
                </button>
            </div>

            <!-- ── Toolbar: nav + view toggle ── -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <!-- Month navigation -->
                <div class="flex items-center gap-2">
                    <button
                        @click="navigate(-1)"
                        class="w-8 h-8 rounded-md flex items-center justify-center border transition-colors hover:opacity-80"
                        style="border-color: var(--border-color); color: var(--text-secondary); background: var(--card-bg);"
                    >
                        <ChevronLeft :size="16" />
                    </button>

                    <span class="font-semibold text-base min-w-[160px] text-center capitalize" style="color: var(--text-primary);">
                        {{ monthLabel }}
                    </span>

                    <button
                        @click="navigate(1)"
                        class="w-8 h-8 rounded-md flex items-center justify-center border transition-colors hover:opacity-80"
                        style="border-color: var(--border-color); color: var(--text-secondary); background: var(--card-bg);"
                    >
                        <ChevronRight :size="16" />
                    </button>

                    <button
                        @click="goToday"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-opacity hover:opacity-80"
                        style="border-color: var(--border-color); color: var(--text-secondary); background: var(--card-bg);"
                    >
                        Hari Ini
                    </button>
                </div>

                <!-- View toggle -->
                <div class="flex gap-1 p-1 rounded-xl" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                    <button
                        v-for="v in [{ key: 'month', label: 'Bulan' }, { key: 'agenda', label: 'Agenda' }]"
                        :key="v.key"
                        @click="switchView(v.key as 'month' | 'agenda')"
                        class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
                        :style="activeView === v.key
                            ? 'background: #3B82F6; color: white;'
                            : 'color: var(--text-secondary); background: transparent;'"
                    >{{ v.label }}</button>
                </div>
            </div>

            <!-- ── Legend ── -->
            <div class="flex items-center gap-4 flex-wrap text-xs" style="color: var(--text-muted);">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background: #8B5CF6;" />
                    Acara
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background: #3B82F6;" />
                    Meeting
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background: #F59E0B;" />
                    Task
                </div>
            </div>

            <!-- ── Month grid ── -->
            <div
                v-if="activeView === 'month'"
                class="rounded-xl overflow-hidden border"
                style="background: var(--card-bg); border-color: var(--border-color);"
            >
                <!-- Day headers -->
                <div class="grid grid-cols-7 border-b" style="border-color: var(--border-color);">
                    <div
                        v-for="day in WEEK_DAYS" :key="day"
                        class="py-2 text-center text-xs font-semibold"
                        style="color: var(--text-muted);"
                    >{{ day }}</div>
                </div>

                <!-- Weeks -->
                <div class="grid grid-cols-7">
                    <div
                        v-for="(cell, idx) in monthGrid"
                        :key="idx"
                        class="min-h-[90px] border-b border-r p-1 cursor-pointer transition-colors hover:opacity-90"
                        :style="`border-color: var(--border-color); background: ${isToday(cell.date) ? 'rgba(59,130,246,0.07)' : 'transparent'};`"
                        @click="openCreateForDay(cell.date)"
                    >
                        <!-- Date number -->
                        <div class="flex items-center justify-center w-6 h-6 rounded-full mb-1 mx-auto text-xs font-semibold"
                            :style="isToday(cell.date)
                                ? 'background: #3B82F6; color: white;'
                                : cell.isCurrentMonth
                                    ? 'color: var(--text-primary);'
                                    : 'color: var(--text-muted);'"
                        >{{ cell.date.getDate() }}</div>

                        <!-- Event chips -->
                        <template v-for="(ev, i) in eventsForDate(cell.date).slice(0, 3)" :key="ev.id">
                            <div
                                class="rounded text-[10px] px-1.5 py-0.5 mb-0.5 truncate cursor-pointer leading-tight"
                                :style="`background: ${ev.color}22; color: ${ev.color};`"
                                @click.stop="handleChipClick(ev)"
                                :title="ev.title"
                            >
                                {{ ev.title }}
                            </div>
                        </template>

                        <!-- +N more -->
                        <div
                            v-if="eventsForDate(cell.date).length > 3"
                            class="text-[10px] font-medium mt-0.5"
                            style="color: var(--text-muted);"
                        >+{{ eventsForDate(cell.date).length - 3 }} lainnya</div>
                    </div>
                </div>
            </div>

            <!-- ── Agenda (list) view ── -->
            <div v-else-if="activeView === 'agenda'" class="space-y-4">
                <!-- Empty state -->
                <div
                    v-if="agendaGroups.length === 0"
                    class="rounded-xl py-16 text-center"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <Calendar :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                    <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Tidak ada acara bulan ini</p>
                    <p class="text-sm mb-4" style="color: var(--text-muted);">Buat acara baru atau cek bulan lain</p>
                    <button
                        @click="showCreate = true"
                        class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        style="background: #3B82F6;"
                    >Buat Acara</button>
                </div>

                <div
                    v-for="group in agendaGroups"
                    :key="group.dateKey"
                    class="rounded-xl overflow-hidden"
                    style="background: var(--card-bg); border: 1px solid var(--border-color);"
                >
                    <!-- Date header -->
                    <div class="px-4 py-2.5 border-b" style="border-color: var(--border-color); background: var(--bg-tertiary);">
                        <span class="text-xs font-semibold capitalize" style="color: var(--text-secondary);">{{ group.label }}</span>
                    </div>

                    <!-- Event rows -->
                    <div>
                        <div
                            v-for="ev in group.events"
                            :key="ev.id"
                            class="flex items-center gap-3 px-4 py-3 border-b last:border-0 cursor-pointer hover:opacity-90 transition-opacity"
                            style="border-color: var(--border-color);"
                            @click="handleChipClick(ev)"
                        >
                            <!-- Colored dot -->
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="`background: ${ev.color};`" />

                            <!-- Time -->
                            <div class="flex items-center gap-1 text-xs shrink-0 w-[80px]" style="color: var(--text-muted);">
                                <Clock :size="11" />
                                <span>{{ ev.all_day ? 'Sepanjang Hari' : formatTime(ev.start) }}</span>
                            </div>

                            <!-- Title -->
                            <span class="flex-1 text-sm font-medium truncate" style="color: var(--text-primary);">
                                {{ ev.title }}
                            </span>

                            <!-- Source label -->
                            <span
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0"
                                :style="`background: ${ev.color}22; color: ${ev.color};`"
                            >{{ sourceLabel(ev.source) }}</span>

                            <!-- Icon hint if URL -->
                            <CheckSquare v-if="ev.source === 'task'" :size="13" style="color: var(--text-muted);" />
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Create / Edit Slide-over ── -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex justify-end">
                <!-- Overlay -->
                <div
                    class="absolute inset-0"
                    style="background: rgba(0,0,0,0.4);"
                    @click="closeSlide"
                />

                <!-- Panel -->
                <div
                    class="relative z-10 w-[480px] h-full overflow-y-auto shadow-2xl flex flex-col"
                    style="background: var(--card-bg); border-left: 1px solid var(--border-color);"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border-color);">
                        <h2 class="font-semibold text-base" style="color: var(--text-primary);">
                            {{ selectedEvent ? 'Edit Acara' : 'Buat Acara' }}
                        </h2>
                        <button @click="closeSlide" class="rounded-md p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="18" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submitCreate" class="flex-1 px-6 py-5 space-y-4">

                        <!-- Title -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Judul <span style="color: #EF4444;">*</span>
                            </label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                placeholder="Nama acara..."
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
                                placeholder="Keterangan acara..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Calendar type -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                Tipe Kalender <span style="color: #EF4444;">*</span>
                            </label>
                            <select
                                v-model="createForm.calendar_type"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <option v-for="t in calendarTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <p v-if="createForm.errors.calendar_type" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.calendar_type }}</p>
                        </div>

                        <!-- Start + End datetime -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Mulai <span style="color: #EF4444;">*</span>
                                </label>
                                <input
                                    v-model="createForm.start_at"
                                    type="datetime-local"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <p v-if="createForm.errors.start_at" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.start_at }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                    Selesai <span style="color: #EF4444;">*</span>
                                </label>
                                <input
                                    v-model="createForm.end_at"
                                    type="datetime-local"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                    style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <p v-if="createForm.errors.end_at" class="mt-1 text-xs" style="color: #EF4444;">{{ createForm.errors.end_at }}</p>
                            </div>
                        </div>

                        <!-- All day toggle -->
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="createForm.all_day" class="accent-blue-500 w-4 h-4" />
                            <span class="text-sm" style="color: var(--text-secondary);">Sepanjang hari</span>
                        </label>

                        <!-- Location -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                                <MapPin :size="11" class="inline mr-1" />Lokasi
                            </label>
                            <input
                                v-model="createForm.location"
                                type="text"
                                placeholder="Ruang rapat / alamat..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <!-- Color swatches -->
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Warna</label>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button
                                    v-for="c in colorSwatches"
                                    :key="c"
                                    type="button"
                                    @click="createForm.color = c"
                                    class="w-6 h-6 rounded-full transition-transform hover:scale-110"
                                    :style="`background: ${c}; outline: 2px solid ${createForm.color === c ? c : 'transparent'}; outline-offset: 2px;`"
                                />
                            </div>
                        </div>

                        <!-- Public toggle -->
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="createForm.is_public" class="accent-blue-500 w-4 h-4" />
                            <span class="text-sm" style="color: var(--text-secondary);">Tampilkan ke seluruh organisasi</span>
                        </label>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-opacity"
                                :style="createForm.processing ? 'background: #3B82F6; opacity: 0.6;' : 'background: #3B82F6;'"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : (selectedEvent ? 'Simpan Perubahan' : 'Buat Acara') }}
                            </button>
                            <button
                                type="button"
                                @click="closeSlide"
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
