import { ref } from 'vue';

declare global {
    interface Window {
        Echo?: {
            private: (channel: string) => {
                listen: (event: string, cb: (payload: unknown) => void) => unknown;
            };
            leave: (channel: string) => void;
        };
    }
}

export interface ChatUser {
    id: string;
    name: string | null;
}

export interface ChatMessage {
    id: string;
    channel_id: string;
    parent_id: string | null;
    sender: ChatUser | null;
    content: string;
    content_type: string;
    attachments: unknown[] | null;
    mentions: string[] | null;
    reactions: Record<string, string[]> | null;
    is_pinned: boolean;
    edited_at: string | null;
    created_at: string | null;
    is_mine: boolean;
    reply_count?: number;
}

export interface ChatChannelMember {
    id: string;
    user_id: string;
    name: string | null;
    role: string;
    last_read_at: string | null;
}

export interface ChatChannel {
    id: string;
    channel_type: string | { value: string };
    name: string | null;
    description: string | null;
    is_archived: boolean;
    member_count: number;
    unread_count: number;
    counterpart: ChatUser | null;
    last_message: { content: string; sender_id: string; created_at: string | null } | null;
    members?: ChatChannelMember[];
    created_at: string | null;
}

interface BroadcastMessage {
    id: string;
    channel_id: string;
    sender: { id: string; name: string | null };
    content: string;
    content_type: string;
    created_at: string;
    parent_id: string | null;
}

/**
 * Shared chat state + actions. Talks to the /chat JSON endpoints via
 * window.axios and (optionally) subscribes to the per-channel private Reverb
 * channel to append messages in real time. Degrades gracefully when Echo/Reverb
 * is unavailable (the page still works via send + optimistic append).
 */
export function useChat() {
    const channel = ref<ChatChannel | null>(null);
    const messages = ref<ChatMessage[]>([]);
    const members = ref<ChatChannelMember[]>([]);
    const loading = ref(false);
    const sending = ref(false);
    const error = ref<string | null>(null);

    let subscribedChannelId: string | null = null;

    const axios = () => (window as unknown as { axios: {
        get: (url: string) => Promise<{ data: { data: unknown; meta?: unknown } }>;
        post: (url: string, body?: Record<string, unknown>) => Promise<{ data: { data: ChatMessage } }>;
        patch: (url: string, body?: Record<string, unknown>) => Promise<{ data: { data: ChatMessage } }>;
        delete: (url: string) => Promise<{ data: unknown }>;
    } }).axios;

    /** Load an active channel payload (channel + recent messages) from the server. */
    const loadChannel = async (channelId: string) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios().get(`/chat/channels/${channelId}`);
            const payload = data.data as { channel: ChatChannel; messages: ChatMessage[] };
            channel.value = payload.channel;
            messages.value = payload.messages;
            members.value = payload.channel.members ?? [];
            subscribe(channelId);
        } catch {
            error.value = 'Gagal memuat percakapan.';
        } finally {
            loading.value = false;
        }
    };

    /** Initialize with a channel already supplied by the Inertia page. */
    const setActive = (payload: { channel: ChatChannel; messages: ChatMessage[] } | null) => {
        if (!payload) {
            channel.value = null;
            messages.value = [];
            members.value = [];
            return;
        }
        channel.value = payload.channel;
        messages.value = payload.messages;
        members.value = payload.channel.members ?? [];
        subscribe(payload.channel.id);
    };

    const send = async (content: string, opts: { mentions?: string[]; parent_id?: string } = {}) => {
        if (!channel.value || (!content.trim())) return;
        sending.value = true;
        error.value = null;
        try {
            const { data } = await axios().post(`/chat/channels/${channel.value.id}/messages`, {
                content,
                mentions: opts.mentions ?? [],
                parent_id: opts.parent_id ?? null,
            });
            messages.value.push(data.data);
        } catch {
            error.value = 'Gagal mengirim pesan.';
        } finally {
            sending.value = false;
        }
    };

    const edit = async (message: ChatMessage, content: string) => {
        try {
            const { data } = await axios().patch(`/chat/messages/${message.id}`, { content });
            replaceMessage(data.data);
        } catch {
            error.value = 'Gagal menyunting pesan.';
        }
    };

    const remove = async (message: ChatMessage) => {
        try {
            await axios().delete(`/chat/messages/${message.id}`);
            messages.value = messages.value.filter(m => m.id !== message.id);
        } catch {
            error.value = 'Gagal menghapus pesan.';
        }
    };

    const react = async (message: ChatMessage, emoji: string) => {
        try {
            const { data } = await axios().post(`/chat/messages/${message.id}/react`, { emoji });
            replaceMessage(data.data);
        } catch {
            error.value = 'Gagal menambahkan reaksi.';
        }
    };

    const markRead = async () => {
        if (!channel.value) return;
        try {
            await axios().post(`/chat/channels/${channel.value.id}/read`, {});
        } catch {
            /* non-fatal */
        }
    };

    const replaceMessage = (updated: ChatMessage) => {
        const idx = messages.value.findIndex(m => m.id === updated.id);
        if (idx !== -1) messages.value[idx] = updated;
    };

    // ── realtime ─────────────────────────────────────────────────────────────

    const subscribe = (channelId: string) => {
        if (typeof window === 'undefined' || !window.Echo) return;
        if (subscribedChannelId === channelId) return;

        unsubscribe();
        try {
            window.Echo
                .private(`chat.channel.${channelId}`)
                .listen('.chat.message.sent', (raw: unknown) => {
                    const payload = raw as BroadcastMessage;
                    // Ignore our own echoed messages (toOthers already filters,
                    // but guard against duplicates by id).
                    if (messages.value.some(m => m.id === payload.id)) return;
                    messages.value.push({
                        id: payload.id,
                        channel_id: payload.channel_id,
                        parent_id: payload.parent_id,
                        sender: payload.sender,
                        content: payload.content,
                        content_type: payload.content_type,
                        attachments: null,
                        mentions: null,
                        reactions: null,
                        is_pinned: false,
                        edited_at: null,
                        created_at: payload.created_at,
                        is_mine: false,
                    });
                });
            subscribedChannelId = channelId;
        } catch {
            /* Reverb not available — silent fallback */
        }
    };

    const unsubscribe = () => {
        if (subscribedChannelId && window.Echo) {
            try {
                window.Echo.leave(`chat.channel.${subscribedChannelId}`);
            } catch {
                /* ignore */
            }
        }
        subscribedChannelId = null;
    };

    return {
        channel,
        messages,
        members,
        loading,
        sending,
        error,
        loadChannel,
        setActive,
        send,
        edit,
        remove,
        react,
        markRead,
        unsubscribe,
    };
}
