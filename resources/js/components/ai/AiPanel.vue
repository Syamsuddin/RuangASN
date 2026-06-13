<script setup lang="ts">
import { ref, nextTick, watch, onMounted, onUnmounted } from 'vue';
import { Sparkles, RotateCcw, X, Send, Loader2, ChevronDown } from 'lucide-vue-next';
import AiMessageBubble from './AiMessageBubble.vue';
import { useAiChat, agentLabels, type AiMessage } from '@/composables/useAiChat';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();

const { messages, loading, error, reset, send, confirmAction, rejectAction } = useAiChat();

const input = ref('');
const selectedAgent = ref<string>('secretary');
const agentMenuOpen = ref(false);
const chatArea = ref<HTMLElement | null>(null);
const textarea = ref<HTMLTextAreaElement | null>(null);

const agentOptions = Object.entries(agentLabels).map(([value, label]) => ({ value, label }));

const scrollToBottom = async () => {
    await nextTick();
    if (chatArea.value) chatArea.value.scrollTop = chatArea.value.scrollHeight;
};

watch(() => messages.value.length, scrollToBottom);

watch(() => props.open, (isOpen) => {
    if (isOpen) nextTick(() => textarea.value?.focus());
});

const submit = async () => {
    const content = input.value;
    if (!content.trim() || loading.value) return;
    input.value = '';
    await send(content, { agent: selectedAgent.value });
    await scrollToBottom();
};

const onConfirm = (message: AiMessage, index: number) => confirmAction(message, index);
const onReject = (message: AiMessage, index: number) => rejectAction(message, index);

const doReset = () => {
    reset();
    input.value = '';
};

const handleKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.open) emit('close');
};

onMounted(() => document.addEventListener('keydown', handleKey));
onUnmounted(() => document.removeEventListener('keydown', handleKey));
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <transition name="ai-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-40"
                style="background: rgba(0,0,0,0.3);"
                @click="emit('close')"
            />
        </transition>

        <!-- Slide-over -->
        <transition name="ai-slide">
            <aside
                v-if="open"
                class="fixed top-0 right-0 z-50 w-[400px] max-w-[100vw] h-full flex flex-col"
                style="background: var(--card-bg); border-left: 1px solid var(--border-color); box-shadow: -4px 0 24px rgba(0,0,0,0.15);"
                role="dialog"
                aria-label="AI RuangASN"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between px-4 py-3 border-b shrink-0"
                    style="border-color: var(--border-color); box-shadow: inset 0 0 0 1px rgba(139,92,246,0.3);"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <Sparkles :size="18" style="color: #8B5CF6;" />
                        <div class="min-w-0">
                            <p class="text-sm font-semibold truncate" style="color: var(--text-primary);">AI RuangASN</p>
                            <!-- Agent selector -->
                            <div class="relative">
                                <button
                                    type="button"
                                    @click.stop="agentMenuOpen = !agentMenuOpen"
                                    class="flex items-center gap-0.5 text-xs"
                                    style="color: var(--text-secondary);"
                                >
                                    {{ agentLabels[selectedAgent] }}
                                    <ChevronDown :size="12" />
                                </button>
                                <div
                                    v-if="agentMenuOpen"
                                    class="absolute left-0 top-5 z-10 w-44 rounded-lg border shadow-xl overflow-hidden py-1"
                                    style="background: var(--card-bg); border-color: var(--border-color);"
                                >
                                    <button
                                        v-for="opt in agentOptions"
                                        :key="opt.value"
                                        type="button"
                                        @click="selectedAgent = opt.value; agentMenuOpen = false"
                                        class="w-full text-left px-3 py-1.5 text-xs transition-colors hover:opacity-80"
                                        :style="opt.value === selectedAgent
                                            ? 'color: #8B5CF6; background: rgba(139,92,246,0.08);'
                                            : 'color: var(--text-secondary);'"
                                    >{{ opt.label }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            title="Reset percakapan"
                            @click="doReset"
                            class="w-8 h-8 rounded-md flex items-center justify-center transition-colors hover:opacity-70"
                            style="color: var(--text-muted);"
                        >
                            <RotateCcw :size="16" />
                        </button>
                        <button
                            type="button"
                            title="Tutup"
                            @click="emit('close')"
                            class="w-8 h-8 rounded-md flex items-center justify-center transition-colors hover:opacity-70"
                            style="color: var(--text-muted);"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                </div>

                <!-- Chat area -->
                <div ref="chatArea" class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Empty state -->
                    <div v-if="!messages.length" class="h-full flex flex-col items-center justify-center text-center px-6">
                        <div
                            class="w-12 h-12 rounded-xl flex items-center justify-center mb-3"
                            style="background: rgba(139,92,246,0.12);"
                        >
                            <Sparkles :size="24" style="color: #8B5CF6;" />
                        </div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">Halo, saya asisten RuangASN</p>
                        <p class="text-xs mt-1" style="color: var(--text-muted);">
                            Minta saya membuat tugas, menjadwalkan rapat, atau menjawab dari basis pengetahuan.
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

                    <!-- Loading -->
                    <div v-if="loading" class="flex justify-start">
                        <div class="rounded-xl rounded-tl-sm px-3.5 py-2.5 flex items-center gap-2" style="background: var(--bg-tertiary); color: var(--text-muted);">
                            <Loader2 :size="14" class="animate-spin" />
                            <span class="text-xs">Sedang berpikir...</span>
                        </div>
                    </div>

                    <p v-if="error" class="text-xs text-center" style="color: #F87171;">{{ error }}</p>
                </div>

                <!-- Input footer -->
                <div class="border-t px-4 py-3 shrink-0" style="border-color: var(--border-color);">
                    <div class="flex items-end gap-2">
                        <textarea
                            ref="textarea"
                            v-model="input"
                            rows="1"
                            placeholder="Tulis pesan untuk AI..."
                            class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none resize-none max-h-28"
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
            </aside>
        </transition>
    </Teleport>
</template>

<style scoped>
.ai-fade-enter-active, .ai-fade-leave-active { transition: opacity 0.2s ease; }
.ai-fade-enter-from, .ai-fade-leave-to { opacity: 0; }
.ai-slide-enter-active, .ai-slide-leave-active { transition: transform 0.25s ease; }
.ai-slide-enter-from, .ai-slide-leave-to { transform: translateX(100%); }
</style>
