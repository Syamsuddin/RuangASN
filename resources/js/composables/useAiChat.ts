import { ref } from 'vue';

declare global {
    interface Window {
        axios: {
            get: (url: string) => Promise<{ data: { data: AiConversation } }>;
            post: (url: string, body?: Record<string, unknown>) => Promise<{ data: { data: AiMessage; created?: unknown } }>;
        };
    }
}

export interface Citation {
    source_type: string;
    source_id: string;
    title: string;
    excerpt: string;
}

export interface ProposedAction {
    type: string;
    payload: Record<string, unknown>;
}

export interface AiMessage {
    id: string;
    conversation_id: string;
    role: 'user' | 'assistant' | string;
    content: string;
    citations: Citation[];
    proposed_actions: ProposedAction[];
    action_confirmed: boolean | null;
    has_pending_actions: boolean;
    created_at?: string;
}

export interface AiConversation {
    id: string;
    agent_type: string | { value: string };
    title: string | null;
    context_type?: string | null;
    context_id?: string | null;
    updated_at?: string;
    messages?: AiMessage[];
}

/**
 * Shared chat state + actions for the AI panel and the full AI page. Talks to
 * the existing /ai JSON endpoints (ai.send / ai.confirm / ai.reject / ai.show)
 * via axios. Deterministic-friendly: works with the fake provider.
 */
export function useAiChat() {
    const conversationId = ref<string | null>(null);
    const messages = ref<AiMessage[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const reset = () => {
        conversationId.value = null;
        messages.value = [];
        error.value = null;
    };

    const loadConversation = async (id: string) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await window.axios.get(`/ai/conversations/${id}`);
            conversationId.value = data.data.id;
            messages.value = data.data.messages ?? [];
        } catch {
            error.value = 'Gagal memuat percakapan.';
        } finally {
            loading.value = false;
        }
    };

    /**
     * Send a user message. Optionally tied to an agent + a page context
     * (e.g. { context_type: 'meeting', context_id }). Appends the optimistic
     * user bubble immediately, then the assistant reply on success.
     */
    const send = async (
        content: string,
        opts: { agent?: string; context?: Record<string, unknown> } = {},
    ) => {
        const trimmed = content.trim();
        if (!trimmed || loading.value) return;

        loading.value = true;
        error.value = null;

        // Optimistic user bubble.
        messages.value.push({
            id: `tmp-${Date.now()}`,
            conversation_id: conversationId.value ?? '',
            role: 'user',
            content: trimmed,
            citations: [],
            proposed_actions: [],
            action_confirmed: null,
            has_pending_actions: false,
            created_at: new Date().toISOString(),
        });

        const context = { ...(opts.context ?? {}) };
        if (opts.agent) context.agent = opts.agent;

        try {
            const { data } = await window.axios.post('/ai/send', {
                content: trimmed,
                conversation_id: conversationId.value,
                context,
            });
            const assistant = data.data;
            conversationId.value = assistant.conversation_id;
            messages.value.push(assistant);
        } catch {
            error.value = 'Gagal mengirim pesan. Coba lagi.';
            // Drop the optimistic bubble on failure.
            messages.value = messages.value.filter((m) => !m.id.startsWith('tmp-'));
        } finally {
            loading.value = false;
        }
    };

    const replaceMessage = (updated: AiMessage) => {
        const idx = messages.value.findIndex((m) => m.id === updated.id);
        if (idx !== -1) messages.value[idx] = updated;
    };

    const confirmAction = async (message: AiMessage, actionIndex: number) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await window.axios.post(`/ai/messages/${message.id}/confirm`, {
                action_index: actionIndex,
            });
            replaceMessage(data.data);
            return data.created;
        } catch {
            error.value = 'Gagal mengeksekusi aksi (mungkin Anda tidak memiliki izin).';
            return null;
        } finally {
            loading.value = false;
        }
    };

    const rejectAction = async (message: AiMessage, actionIndex: number) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await window.axios.post(`/ai/messages/${message.id}/reject`, {
                action_index: actionIndex,
            });
            replaceMessage(data.data);
        } catch {
            error.value = 'Gagal menolak aksi.';
        } finally {
            loading.value = false;
        }
    };

    return {
        conversationId,
        messages,
        loading,
        error,
        reset,
        loadConversation,
        send,
        confirmAction,
        rejectAction,
    };
}

/** Indonesian labels for AiAgentType values. */
export const agentLabels: Record<string, string> = {
    secretary: 'Sekretaris',
    meeting: 'Notulis Rapat',
    report: 'Penyusun Laporan',
    document: 'Dokumen',
    knowledge: 'Basis Pengetahuan',
    performance: 'Kinerja / SKP',
    executive: 'Eksekutif',
    workload: 'Beban Kerja',
    general: 'Umum',
};

export const agentLabel = (agent: string | { value: string } | null | undefined): string => {
    if (!agent) return 'Umum';
    const key = typeof agent === 'string' ? agent : agent.value;
    return agentLabels[key] ?? key;
};
