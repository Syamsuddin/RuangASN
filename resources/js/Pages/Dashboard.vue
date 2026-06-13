<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ClipboardList, CalendarCheck, TrendingUp, FileCheck,
    Sparkles, Plus, Video, Upload, MessageSquare,
    ChevronRight, Circle,
} from 'lucide-vue-next';

interface Task {
    id: string;
    title: string;
    status: string;
    priority: string;
    due_date?: string;
    assignee?: { name: string };
}

interface Meeting {
    id: string;
    title: string;
    start_time: string;
    end_time?: string;
    mode: string;
    location?: string;
    link?: string;
}

interface ActivityItem {
    id: string;
    description: string;
    created_at: string;
    actor?: { name: string };
}

const props = defineProps<{
    auth: { user: { name: string; jabatan?: string; nip?: string; organization?: { name: string } } };
    taskStats: { total: number; in_progress: number; due_today: number; overdue: number };
    recentTasks: Task[];
    todayMeetings?: Meeting[];
    recentActivity?: ActivityItem[];
}>();

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat Pagi';
    if (h < 15) return 'Selamat Siang';
    if (h < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

const todayDate = computed(() =>
    new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

const statusBadge = (status: string) => ({
    draft:          { label: 'Draft',       bg: 'rgba(100,116,139,0.2)', color: '#94A3B8' },
    open:           { label: 'Open',        bg: 'rgba(59,130,246,0.2)',  color: '#60A5FA' },
    in_progress:    { label: 'In Progress', bg: 'rgba(245,158,11,0.2)',  color: '#FCD34D' },
    waiting_review: { label: 'Review',      bg: 'rgba(139,92,246,0.2)',  color: '#A78BFA' },
    completed:      { label: 'Selesai',     bg: 'rgba(16,185,129,0.2)',  color: '#34D399' },
    overdue:        { label: 'Overdue',     bg: 'rgba(239,68,68,0.2)',   color: '#F87171' },
}[status] ?? { label: status, bg: 'rgba(100,116,139,0.2)', color: '#94A3B8' });

const priorityBadge = (priority: string) => ({
    high:   { label: 'Tinggi', bg: 'rgba(239,68,68,0.15)',  color: '#F87171' },
    medium: { label: 'Sedang', bg: 'rgba(245,158,11,0.15)', color: '#FCD34D' },
    low:    { label: 'Rendah', bg: 'rgba(100,116,139,0.15)',color: '#94A3B8' },
}[priority] ?? { label: priority, bg: 'rgba(100,116,139,0.15)', color: '#94A3B8' });

const isOverdue = (task: Task) => {
    if (!task.due_date) return false;
    return new Date(task.due_date) < new Date() && !['completed', 'cancelled', 'closed'].includes(task.status);
};

const formatDue = (dateStr?: string) => {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    const today = new Date(); today.setHours(0,0,0,0);
    const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
    const due = new Date(d); due.setHours(0,0,0,0);
    if (due < today) return { label: 'Terlambat', urgent: true };
    if (due.getTime() === today.getTime()) return { label: 'Hari ini', urgent: true };
    if (due.getTime() === tomorrow.getTime()) return { label: 'Besok', urgent: false };
    return { label: d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }), urgent: false };
};

const kpis = computed(() => [
    {
        label: 'Tasks Hari Ini',
        value: props.taskStats.total,
        sub: props.taskStats.overdue > 0 ? `${props.taskStats.overdue} overdue` : 'Semua on track',
        subDanger: props.taskStats.overdue > 0,
        icon: ClipboardList,
        accent: '#3B82F6',
        accentBg: 'rgba(59,130,246,0.15)',
    },
    {
        label: 'Meeting Hari Ini',
        value: props.todayMeetings?.length ?? 0,
        sub: props.todayMeetings?.length ? 'Terjadwal hari ini' : 'Tidak ada meeting',
        subDanger: false,
        icon: CalendarCheck,
        accent: '#10B981',
        accentBg: 'rgba(16,185,129,0.15)',
    },
    {
        label: 'Sedang Dikerjakan',
        value: props.taskStats.in_progress,
        sub: `Due hari ini: ${props.taskStats.due_today}`,
        subDanger: props.taskStats.due_today > 0,
        icon: TrendingUp,
        accent: '#F59E0B',
        accentBg: 'rgba(245,158,11,0.15)',
    },
    {
        label: 'Menunggu Review',
        value: 0,
        sub: 'Tidak ada pending',
        subDanger: false,
        icon: FileCheck,
        accent: '#8B5CF6',
        accentBg: 'rgba(139,92,246,0.15)',
    },
]);

const quickActions = [
    { label: 'Buat Task',        icon: Plus,         href: '/tasks/create' },
    { label: 'Jadwalkan Meeting', icon: Video,        href: '/meetings/create' },
    { label: 'Upload Dokumen',    icon: Upload,       href: '/documents/upload' },
    { label: 'Tanya AI',          icon: MessageSquare, href: '/ai' },
];
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Dashboard</span>
        </template>

        <div class="space-y-6 max-w-[1400px]">

            <!-- ── Greeting ── -->
            <div
                class="rounded-xl p-5 flex flex-col sm:flex-row sm:items-center gap-4"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <div class="flex-1">
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ greeting }}, {{ auth.user.name }}! 👋
                    </h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                        {{ todayDate }} · {{ auth.user.organization?.name }}
                    </p>
                </div>

                <!-- AI Morning Brief -->
                <div
                    class="flex items-start gap-3 rounded-lg px-4 py-3 text-sm sm:max-w-xs"
                    style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.25);"
                >
                    <Sparkles :size="16" class="mt-0.5 shrink-0" style="color: #8B5CF6;" />
                    <span style="color: var(--text-secondary);">
                        <template v-if="taskStats.overdue > 0">
                            <b style="color: #F87171;">{{ taskStats.overdue }} task overdue</b> ·
                        </template>
                        {{ taskStats.in_progress }} sedang dikerjakan
                        <template v-if="todayMeetings?.length">
                            · <b style="color: #60A5FA;">{{ todayMeetings.length }} meeting</b> hari ini
                        </template>
                    </span>
                </div>
            </div>

            <!-- ── KPI Cards ── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="rounded-xl p-5"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" :style="`background: ${kpi.accentBg};`">
                            <component :is="kpi.icon" :size="20" :style="`color: ${kpi.accent};`" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold" style="color: var(--text-primary);">{{ kpi.value }}</p>
                    <p class="text-sm mt-0.5" style="color: var(--text-secondary);">{{ kpi.label }}</p>
                    <p class="text-xs mt-1" :style="kpi.subDanger ? 'color: #F87171;' : 'color: var(--text-muted);'">
                        {{ kpi.sub }}
                    </p>
                </div>
            </div>

            <!-- ── Main grid: Tasks | Meetings + Quick Actions ── -->
            <div class="grid lg:grid-cols-5 gap-4">

                <!-- Tasks (left, wider) -->
                <div
                    class="lg:col-span-3 rounded-xl"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <div
                        class="flex items-center justify-between px-5 py-4 border-b"
                        style="border-color: var(--border-color);"
                    >
                        <h2 class="font-semibold text-sm" style="color: var(--text-primary);">Tasks Saya</h2>
                        <a href="/tasks" class="text-xs flex items-center gap-1" style="color: #3B82F6;">
                            Lihat Semua <ChevronRight :size="14" />
                        </a>
                    </div>

                    <div class="divide-y" style="--tw-divide-opacity:1; border-color: var(--border-color);">
                        <template v-if="recentTasks.length">
                            <div
                                v-for="task in recentTasks.slice(0, 7)"
                                :key="task.id"
                                class="flex items-start gap-3 px-5 py-3 transition-colors"
                                :style="isOverdue(task) ? 'background: rgba(239,68,68,0.04);' : ''"
                            >
                                <Circle :size="14" class="mt-0.5 shrink-0" style="color: var(--text-muted);" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ task.title }}</p>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span
                                            class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                                            :style="`background: ${statusBadge(isOverdue(task) ? 'overdue' : task.status).bg}; color: ${statusBadge(isOverdue(task) ? 'overdue' : task.status).color};`"
                                        >{{ statusBadge(isOverdue(task) ? 'overdue' : task.status).label }}</span>
                                        <span
                                            class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                                            :style="`background: ${priorityBadge(task.priority).bg}; color: ${priorityBadge(task.priority).color};`"
                                        >{{ priorityBadge(task.priority).label }}</span>
                                    </div>
                                </div>
                                <div v-if="task.due_date" class="text-right shrink-0">
                                    <span
                                        class="text-xs"
                                        :style="formatDue(task.due_date)?.urgent ? 'color: #F87171;' : 'color: var(--text-muted);'"
                                    >{{ formatDue(task.due_date)?.label }}</span>
                                </div>
                            </div>
                        </template>
                        <div v-else class="px-5 py-10 text-center text-sm" style="color: var(--text-muted);">
                            Belum ada task. <a href="/tasks/create" style="color: #3B82F6;">Buat yang pertama!</a>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- Meetings -->
                    <div
                        class="rounded-xl"
                        style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                    >
                        <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--border-color);">
                            <h2 class="font-semibold text-sm" style="color: var(--text-primary);">Meeting Hari Ini</h2>
                            <a href="/meetings" class="text-xs" style="color: #3B82F6;">Lihat Semua</a>
                        </div>

                        <div class="px-5 py-3 space-y-3">
                            <template v-if="todayMeetings?.length">
                                <div
                                    v-for="m in todayMeetings"
                                    :key="m.id"
                                    class="rounded-lg p-3"
                                    style="background: var(--bg-tertiary); border: 1px solid var(--border-color);"
                                >
                                    <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ m.title }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-muted);">
                                        {{ m.start_time }}<template v-if="m.end_time"> – {{ m.end_time }}</template>
                                        · {{ m.location ?? m.mode }}
                                    </p>
                                    <a
                                        v-if="m.link"
                                        :href="m.link"
                                        target="_blank"
                                        class="inline-block mt-2 text-xs px-3 py-1 rounded-md font-medium text-white"
                                        style="background: #3B82F6;"
                                    >Bergabung</a>
                                </div>
                            </template>
                            <p v-else class="text-sm text-center py-4" style="color: var(--text-muted);">
                                Tidak ada meeting hari ini
                            </p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div
                        class="rounded-xl p-5"
                        style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                    >
                        <h2 class="font-semibold text-sm mb-3" style="color: var(--text-primary);">Aksi Cepat</h2>
                        <div class="grid grid-cols-2 gap-2">
                            <a
                                v-for="action in quickActions"
                                :key="action.label"
                                :href="action.href"
                                class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium border transition-colors"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-secondary);"
                            >
                                <component :is="action.icon" :size="15" style="color: #3B82F6;" />
                                <span class="text-xs">{{ action.label }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Recent Activity ── -->
            <div
                class="rounded-xl"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <div class="px-5 py-4 border-b" style="border-color: var(--border-color);">
                    <h2 class="font-semibold text-sm" style="color: var(--text-primary);">Aktivitas Terbaru</h2>
                </div>
                <div class="px-5 py-4">
                    <template v-if="recentActivity?.length">
                        <div
                            v-for="(item, i) in recentActivity.slice(0, 5)"
                            :key="item.id"
                            class="flex items-start gap-3 py-2.5"
                            :class="i < (recentActivity.length - 1) && i < 4 ? 'border-b' : ''"
                            :style="i < (recentActivity.length - 1) && i < 4 ? 'border-color: var(--border-color);' : ''"
                        >
                            <div class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center text-xs font-bold text-white" style="background: #3B82F6;">
                                {{ item.actor?.name?.charAt(0) ?? '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <b style="color: var(--text-primary);">{{ item.actor?.name }}</b>
                                    {{ item.description }}
                                </p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ item.created_at }}</p>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-sm text-center py-4" style="color: var(--text-muted);">
                        Belum ada aktivitas terbaru
                    </p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
