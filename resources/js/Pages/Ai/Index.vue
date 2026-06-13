<script setup lang="ts">
import { ref, nextTick, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AiMessageBubble from '@/components/ai/AiMessageBubble.vue';
import { Sparkles, Plus, Send, Loader2, BrainCircuit } from 'lucide-vue-next';
import { useAiChat, agentLabel, type AiMessage } from '@/composables/useAiChat';

interface ConversationSummary {
    id: string;
    title: string | null;
    agent_type: string | { value: string };
    updated_at?: string;
}

interface Props {
    conversations: ConversationSummary[];
}

const props = defineProps<Props>();

const list = ref<ConversationSummary[]>([...props.conversations]);
const activeId = ref<string | null>(null);
const input = ref('');
const chatArea = ref<HTMLElement | null>(null);

const {
    conversationId, messages, loading, error,
    reset, loadConversation, send, confirmAction, rejectAction,
} = useAiChat();

const scrollToBottom = async () => {
    await nextTick();
    if (chatArea.value) chatArea.value.scrollTop = chatArea.value.scrollHeight;
};

const openConversation = async (id: string) => {
    if (activeId.value === id) return;
    activeId.value = id;
    await loadConversation(id);
    await scrollToBottom();
};

const newConversation = () => {
    activeId.value = null;
    reset();
    input.value = '';
};

const submit = async () => {
    const content = input.value;
    if (!content.trim() || loading.value) return;
    input.value = '';
    const wasNew = !conversationId.value;
    await send(content);
    await scrollToBottom();

    // Reflect a freshly-created conversation in the sidebar list.
    if (wasNew && conversationId.value) {
        activeId.value = conversationId.value;
        if (!list.value.some((c) => c.id === conversationId.value)) {
            list.value.unshift({
                id: conversationId.value,
                title: content.slice(0, 60),
                agent_type: 'general',
            });
        }
    }
};

const onConfirm = (message: AiMessage, index: number) => confirmAction(message, index);
const onReject = (message: AiMessage, index: number) => rejectAction(message, index);

const formatDate = (iso?: string) =>
    iso ? new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';

const hasThread = computed(() => messages.value.length > 0 || !!conversationId.value);
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">AI Assistant</span>
        </template>

        <div class="max-w-[1200px] h-[calc(100vh-7rem)]">
            <div
                class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-4 h-full"
            >
                <!-- Conversation list -->
                <div
                    class="rounded-xl flex flex-col overflow-hidden"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <div class="flex items-center justify-between px-4 py-3 border-b shrink-0" style="border-color: var(--border-color);">
                        <div class="flex items-center gap-2">
                            <Sparkles :size="16" style="color: #8B5CF6;" />
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">Percakapan</span>
                        </div>
                        <Link
                            href="/ai/memories"
                            class="text-xs flex items-center gap-1 hover:opacity-80"
                            style="color: var(--text-muted);"
                            title="Memori AI"
                        >
                            <BrainCircuit :size="14" /> Memori
                        </Link>
                    </div>

                    <div class="px-3 py-2 shrink-0">
                        <button
                            type="button"
                            @click="newConversation"
                            class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background: #3B82F6;"
                        >
                            <Plus :size="15" /> Percakapan Baru
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-2 pb-2 space-y-1">
                        <button
                            v-for="c in list"
                            :key="c.id"
                            type="button"
                            @click="openConversation(c.id)"
                            class="w-full text-left rounded-lg px-3 py-2 transition-colors"
                            :style="activeId === c.id
                                ? 'background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.25);'
                                : 'background: transparent; border: 1px solid transparent;'"
                        >
                            <p class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                {{ c.title ?? 'Percakapan' }}
                            </p>
                            <div class="flex items-center justify-between mt-0.5">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" style="background: rgba(139,92,246,0.15); color: #A78BFA;">
                                    {{ agentLabel(c.agent_type) }}
                                </span>
                                <span class="text-[10px]" style="color: var(--text-muted);">{{ formatDate(c.updated_at) }}</span>
                            </div>
                        </button>

                        <div v-if="!list.length" class="px-3 py-6 text-center text-xs" style="color: var(--text-muted);">
                            Belum ada percakapan.
                        </div>
                    </div>
                </div>

                <!-- Thread -->
                <div
                    class="rounded-xl flex flex-col overflow-hidden"
                    style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
                >
                    <!-- Chat area -->
                    <div ref="chatArea" class="flex-1 overflow-y-auto p-5 space-y-4">
                        <div v-if="!hasThread" class="h-full flex flex-col items-center justify-center text-center px-6">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background: rgba(139,92,246,0.12);">
                                <Sparkles :size="28" style="color: #8B5CF6;" />
                            </div>
                            <h2 class="text-lg font-bold" style="color: var(--text-primary);">AI RuangASN</h2>
                            <p class="text-sm mt-1 max-w-sm" style="color: var(--text-muted);">
                                Mulai percakapan baru. Saya bisa membantu membuat tugas, menjadwalkan rapat,
                                menyusun draft, atau menjawab dari basis pengetahuan organisasi.
                                Saya hanya mengusulkan — Anda yang mengonfirmasi.
                            </p>
                        </div>

                        <AiMessageBubble
                            v-for="m in messages"
                            :key="m.id"
                            :message="m"
                            :loading="loading"
                            @confirm="onConfirm"
                            @reject="onReject"
                        />

                        <div v-if="loading" class="flex justify-start">
                            <div class="rounded-xl rounded-tl-sm px-3.5 py-2.5 flex items-center gap-2" style="background: var(--bg-tertiary); color: var(--text-muted);">
                                <Loader2 :size="14" class="animate-spin" />
                                <span class="text-xs">Sedang berpikir...</span>
                            </div>
                        </div>

                        <p v-if="error" class="text-xs text-center" style="color: #F87171;">{{ error }}</p>
                    </div>

                    <!-- Input -->
                    <div class="border-t px-4 py-3 shrink-0" style="border-color: var(--border-color);">
                        <div class="flex items-end gap-2">
                            <textarea
                                v-model="input"
                                rows="1"
                                placeholder="Tulis pesan untuk AI..."
                                class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none resize-none max-h-32"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                @keydown.enter.exact.prevent="submit"
                            />
                            <button
                                type="button"
                                :disabled="loading || !input.trim()"
                                @click="submit"
                                class="w-9 h-9 rounded-lg flex items-center justify-center text-white shrink-0 disabled:opacity-50"
                                style="background: #3B82F6;"
                            >
                                <Send :size="16" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
