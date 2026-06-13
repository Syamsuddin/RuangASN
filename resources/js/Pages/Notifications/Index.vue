<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell, Check, CheckCheck, Settings, Trash2, BellOff, Clock } from 'lucide-vue-next';

interface Notification {
    id: string;
    title: string;
    body: string;
    notification_type: string;
    read_at: string | null;
    created_at: string;
    data?: Record<string, unknown>;
}

interface PaginatedNotifications {
    data: Notification[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    total: number;
}

const props = defineProps<{
    notifications: PaginatedNotifications;
    unread_count: number;
}>();

const unread = computed(() => props.notifications.data.filter(n => !n.read_at));
const read   = computed(() => props.notifications.data.filter(n => n.read_at));

const relativeTime = (iso: string): string => {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'baru saja';
    if (mins < 60) return `${mins} menit lalu`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} jam lalu`;
    return `${Math.floor(hours / 24)} hari lalu`;
};

const markRead = (id: string) => {
    router.patch(`/notifications/${id}/read`, {}, { preserveScroll: true });
};

const markAllRead = () => {
    router.patch('/notifications/read-all', {}, { preserveScroll: true });
};

const destroy = (id: string) => {
    router.delete(`/notifications/${id}`, { preserveScroll: true });
};

const typeIcon = (type: string) => {
    const icons: Record<string, string> = {
        task_assigned: '📋', task_due: '⏰', task_overdue: '🚨',
        meeting_invite: '📅', approval_request: '📝',
        system: 'ℹ️', security: '🔒',
    };
    return icons[type] ?? '🔔';
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Notifikasi</span>
        </template>

        <div class="max-w-3xl mx-auto space-y-4">

            <!-- Page header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Bell :size="20" style="color: var(--text-primary);" />
                    <h1 class="text-lg font-semibold" style="color: var(--text-primary);">Notifikasi</h1>
                    <span
                        v-if="unread_count > 0"
                        class="text-xs font-semibold px-2 py-0.5 rounded-full bg-[#EF4444] text-white"
                    >{{ unread_count }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        v-if="unread_count > 0"
                        @click="markAllRead"
                        class="flex items-center gap-1.5 text-sm transition-colors"
                        style="color: var(--color-primary);"
                    >
                        <CheckCheck :size="16" />
                        Tandai semua dibaca
                    </button>
                    <Link
                        href="/notifications/preferences"
                        class="flex items-center gap-1.5 text-sm transition-colors"
                        style="color: var(--text-muted);"
                    >
                        <Settings :size="16" />
                        Preferensi
                    </Link>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="notifications.data.length === 0"
                class="flex flex-col items-center justify-center py-20 rounded-lg border"
                style="background: var(--card-bg); border-color: var(--border-color);"
            >
                <BellOff :size="48" class="mb-4 opacity-30" style="color: var(--text-muted);" />
                <p class="text-base font-medium" style="color: var(--text-primary);">Tidak ada notifikasi</p>
                <p class="text-sm mt-1" style="color: var(--text-muted);">Semua notifikasi akan muncul di sini.</p>
            </div>

            <!-- Unread section -->
            <div v-if="unread.length > 0">
                <p class="text-xs font-semibold mb-2 px-1" style="color: var(--text-muted);">BELUM DIBACA</p>
                <div class="rounded-lg border overflow-hidden" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div
                        v-for="(notif, idx) in unread"
                        :key="notif.id"
                        class="flex items-start gap-4 px-4 py-4"
                        :class="{ 'border-t': idx > 0 }"
                        :style="{ borderColor: 'var(--border-color)', background: 'rgba(59,130,246,0.04)' }"
                    >
                        <span class="text-xl shrink-0 mt-0.5">{{ typeIcon(notif.notification_type) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">{{ notif.title }}</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">{{ notif.body }}</p>
                            <p class="flex items-center gap-1 text-xs mt-1.5" style="color: var(--text-muted);">
                                <Clock :size="12" />
                                {{ relativeTime(notif.created_at) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                @click="markRead(notif.id)"
                                class="p-1.5 rounded-md transition-colors hover:opacity-80"
                                style="color: var(--color-primary);"
                                title="Tandai dibaca"
                            >
                                <Check :size="16" />
                            </button>
                            <button
                                @click="destroy(notif.id)"
                                class="p-1.5 rounded-md transition-colors hover:opacity-80"
                                style="color: var(--text-muted);"
                                title="Hapus"
                            >
                                <Trash2 :size="16" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Read section -->
            <div v-if="read.length > 0">
                <p class="text-xs font-semibold mb-2 px-1" style="color: var(--text-muted);">SUDAH DIBACA</p>
                <div class="rounded-lg border overflow-hidden" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div
                        v-for="(notif, idx) in read"
                        :key="notif.id"
                        class="flex items-start gap-4 px-4 py-4"
                        :class="{ 'border-t': idx > 0 }"
                        :style="{ borderColor: 'var(--border-color)' }"
                    >
                        <span class="text-xl shrink-0 mt-0.5 opacity-50">{{ typeIcon(notif.notification_type) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm" style="color: var(--text-secondary);">{{ notif.title }}</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ notif.body }}</p>
                            <p class="flex items-center gap-1 text-xs mt-1.5" style="color: var(--text-muted);">
                                <Clock :size="12" />
                                {{ relativeTime(notif.created_at) }}
                            </p>
                        </div>
                        <button
                            @click="destroy(notif.id)"
                            class="p-1.5 rounded-md transition-colors hover:opacity-80 shrink-0"
                            style="color: var(--text-muted);"
                            title="Hapus"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="notifications.last_page > 1" class="flex items-center justify-center gap-3 pt-2">
                <Link
                    v-if="notifications.prev_page_url"
                    :href="notifications.prev_page_url"
                    class="px-4 py-1.5 rounded-md text-sm border transition-colors"
                    style="border-color: var(--border-color); color: var(--text-primary); background: var(--card-bg);"
                >Sebelumnya</Link>
                <span class="text-sm" style="color: var(--text-muted);">
                    Hal {{ notifications.current_page }} / {{ notifications.last_page }}
                </span>
                <Link
                    v-if="notifications.next_page_url"
                    :href="notifications.next_page_url"
                    class="px-4 py-1.5 rounded-md text-sm border transition-colors"
                    style="border-color: var(--border-color); color: var(--text-primary); background: var(--card-bg);"
                >Berikutnya</Link>
            </div>
        </div>
    </AppLayout>
</template>
