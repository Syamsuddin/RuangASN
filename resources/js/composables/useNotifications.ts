import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export interface Notification {
    id: string;
    title: string;
    body: string;
    notification_type: string;
    read_at: string | null;
    created_at: string;
    data?: Record<string, unknown>;
}

interface NotificationsShared {
    unread_count: number;
    recent: Notification[];
}

export function useNotifications() {
    const page = usePage();

    const shared = computed(() => (page.props as any).notifications as NotificationsShared | null);

    const unreadCount = ref<number>(shared.value?.unread_count ?? 0);
    const notifications = ref<Notification[]>(shared.value?.recent ?? []);

    // Keep in sync with Inertia page prop updates
    const syncFromPage = () => {
        const data = shared.value;
        if (data) {
            unreadCount.value = data.unread_count;
            notifications.value = data.recent;
        }
    };

    const markRead = (id: string) => {
        router.patch(`/notifications/${id}/read`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                const notif = notifications.value.find(n => n.id === id);
                if (notif && !notif.read_at) {
                    notif.read_at = new Date().toISOString();
                    unreadCount.value = Math.max(0, unreadCount.value - 1);
                }
            },
        });
    };

    const markAllRead = () => {
        router.patch('/notifications/read-all', {}, {
            preserveScroll: true,
            onSuccess: () => {
                notifications.value.forEach(n => {
                    n.read_at = n.read_at ?? new Date().toISOString();
                });
                unreadCount.value = 0;
            },
        });
    };

    // Real-time listener via Laravel Echo / Reverb (optional)
    const startEchoListener = (userId: string) => {
        if (typeof window === 'undefined' || !(window as any).Echo) {
            return;
        }

        try {
            (window as any).Echo
                .private(`user.${userId}`)
                .listen('.notification.sent', (payload: Notification) => {
                    notifications.value.unshift(payload);
                    if (notifications.value.length > 5) {
                        notifications.value = notifications.value.slice(0, 5);
                    }
                    unreadCount.value += 1;
                });
        } catch {
            // Reverb not available in this environment — fail silently
        }
    };

    return {
        notifications,
        unreadCount,
        markRead,
        markAllRead,
        syncFromPage,
        startEchoListener,
    };
}
