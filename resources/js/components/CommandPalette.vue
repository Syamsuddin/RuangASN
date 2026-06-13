<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { CheckSquare, Video, FolderOpen, FileText, BookOpen, Search, X } from 'lucide-vue-next';

interface SearchResult {
    type: string;
    id: string;
    title: string;
    snippet: string;
    meta: Record<string, string>;
    url: string;
}

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit  = defineEmits<{
    (e: 'close'): void;
}>();

const query      = ref('');
const results    = ref<SearchResult[]>([]);
const loading    = ref(false);
const activeIdx  = ref(-1);
const inputRef   = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const typeIcon: Record<string, typeof CheckSquare> = {
    task:      CheckSquare,
    meeting:   Video,
    document:  FolderOpen,
    report:    FileText,
    knowledge: BookOpen,
};

const typeLabel: Record<string, string> = {
    task:      'Task',
    meeting:   'Meeting',
    document:  'Dokumen',
    report:    'Laporan',
    knowledge: 'Knowledge',
};

const typeColor: Record<string, string> = {
    task:      '#3B82F6',
    meeting:   '#8B5CF6',
    document:  '#F59E0B',
    report:    '#10B981',
    knowledge: '#EC4899',
};

async function fetchSuggestions(): Promise<void> {
    if (!query.value.trim()) {
        results.value = [];
        activeIdx.value = -1;
        return;
    }

    loading.value = true;
    try {
        const res  = await fetch(`/search/quick?q=${encodeURIComponent(query.value)}`);
        const data = await res.json() as { results: SearchResult[] };
        results.value  = data.results ?? [];
        activeIdx.value = results.value.length > 0 ? 0 : -1;
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
}

watch(query, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchSuggestions, 250);
});

watch(() => props.open, async (val) => {
    if (val) {
        await nextTick();
        inputRef.value?.focus();
    } else {
        query.value     = '';
        results.value   = [];
        activeIdx.value = -1;
    }
});

function handleKeyDown(e: KeyboardEvent): void {
    if (!props.open) return;
    if (e.key === 'Escape') {
        emit('close');
        return;
    }
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx.value = Math.min(activeIdx.value + 1, results.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx.value = Math.max(activeIdx.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const item = results.value[activeIdx.value];
        if (item) navigate(item.url);
        else if (query.value.trim()) goToFull();
    }
}

function navigate(url: string): void {
    emit('close');
    router.visit(url);
}

function goToFull(): void {
    emit('close');
    router.visit(`/search?q=${encodeURIComponent(query.value)}`);
}

onMounted(() => document.addEventListener('keydown', handleKeyDown));
onUnmounted(() => document.removeEventListener('keydown', handleKeyDown));
</script>

<template>
    <Teleport to="body">
        <Transition name="palette-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[999] flex items-start justify-center pt-[10vh]"
                style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);"
                @click.self="emit('close')"
            >
                <div
                    class="w-[560px] max-w-[calc(100vw-2rem)] rounded-xl border shadow-2xl overflow-hidden"
                    style="background: var(--card-bg); border-color: var(--border-color); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);"
                >
                    <!-- Input row -->
                    <div
                        class="flex items-center gap-3 px-4 py-3 border-b"
                        style="border-color: var(--border-color);"
                    >
                        <Search :size="16" style="color: var(--text-muted);" class="shrink-0" />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Cari task, meeting, dokumen..."
                            class="flex-1 bg-transparent text-sm outline-none"
                            style="color: var(--text-primary);"
                        />
                        <button
                            v-if="query"
                            @click="query = ''"
                            class="rounded p-0.5 transition-opacity hover:opacity-70"
                            style="color: var(--text-muted);"
                        >
                            <X :size="14" />
                        </button>
                        <kbd
                            class="text-[10px] px-1.5 py-0.5 rounded font-mono shrink-0"
                            style="background: var(--bg-tertiary); color: var(--text-muted); border: 1px solid var(--border-color);"
                        >Esc</kbd>
                    </div>

                    <!-- Results -->
                    <div class="max-h-[360px] overflow-y-auto">
                        <div v-if="loading" class="px-4 py-6 text-center text-sm" style="color: var(--text-muted);">
                            Mencari...
                        </div>

                        <div v-else-if="results.length === 0 && query.trim()" class="px-4 py-6 text-center text-sm" style="color: var(--text-muted);">
                            Tidak ada hasil untuk "<span style="color: var(--text-primary);">{{ query }}</span>"
                        </div>

                        <div v-else-if="results.length === 0 && !query.trim()" class="px-4 py-6 text-center text-sm" style="color: var(--text-muted);">
                            Ketik untuk mulai mencari...
                        </div>

                        <button
                            v-for="(item, idx) in results"
                            :key="item.type + item.id"
                            @click="navigate(item.url)"
                            class="w-full flex items-start gap-3 px-4 py-3 text-left transition-colors border-b"
                            :style="{
                                borderColor: 'var(--border-color)',
                                background: idx === activeIdx ? 'var(--bg-tertiary)' : 'transparent',
                            }"
                        >
                            <div
                                class="mt-0.5 w-6 h-6 rounded flex items-center justify-center shrink-0"
                                :style="{ background: typeColor[item.type] + '22' }"
                            >
                                <component
                                    :is="typeIcon[item.type] ?? FileText"
                                    :size="13"
                                    :style="{ color: typeColor[item.type] }"
                                />
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ item.title }}</span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded shrink-0"
                                        :style="{ background: typeColor[item.type] + '22', color: typeColor[item.type] }"
                                    >{{ typeLabel[item.type] ?? item.type }}</span>
                                </div>
                                <p v-if="item.snippet" class="text-xs mt-0.5 truncate" style="color: var(--text-muted);">
                                    {{ item.snippet }}
                                </p>
                            </div>
                        </button>
                    </div>

                    <!-- Footer -->
                    <div
                        v-if="query.trim()"
                        class="px-4 py-2.5 border-t flex items-center justify-between"
                        style="border-color: var(--border-color);"
                    >
                        <span class="text-xs" style="color: var(--text-muted);">
                            <kbd class="font-mono px-1 rounded" style="background: var(--bg-tertiary);">↑↓</kbd> navigasi
                            <kbd class="font-mono px-1 rounded ml-1" style="background: var(--bg-tertiary);">Enter</kbd> pilih
                        </span>
                        <button
                            @click="goToFull"
                            class="text-xs font-medium transition-opacity hover:opacity-70"
                            style="color: var(--color-primary);"
                        >
                            Lihat semua hasil untuk "{{ query }}" &rarr;
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.palette-fade-enter-active,
.palette-fade-leave-active {
    transition: opacity 0.15s ease;
}
.palette-fade-enter-from,
.palette-fade-leave-to {
    opacity: 0;
}
</style>
