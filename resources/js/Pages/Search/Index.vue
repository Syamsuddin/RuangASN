<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    CheckSquare, Video, FolderOpen, FileText, BookOpen, Search,
} from 'lucide-vue-next';

interface SearchResult {
    type: string;
    id: string;
    title: string;
    snippet: string;
    meta: Record<string, string>;
    url: string;
}

interface Props {
    query: string;
    types: string[];
    results: Record<string, SearchResult[]>;
    counts: Record<string, number>;
}

const props = defineProps<Props>();

const searchQuery  = ref(props.query);
const activeType   = ref(props.types[0] ?? '');

const typeConfig = [
    { key: '',          label: 'Semua',     icon: Search },
    { key: 'task',      label: 'Task',      icon: CheckSquare },
    { key: 'meeting',   label: 'Meeting',   icon: Video },
    { key: 'document',  label: 'Dokumen',   icon: FolderOpen },
    { key: 'report',    label: 'Laporan',   icon: FileText },
    { key: 'knowledge', label: 'Knowledge', icon: BookOpen },
];

const typeColor: Record<string, string> = {
    task:      '#3B82F6',
    meeting:   '#8B5CF6',
    document:  '#F59E0B',
    report:    '#10B981',
    knowledge: '#EC4899',
};

const typeLabel: Record<string, string> = {
    task: 'Task', meeting: 'Meeting', document: 'Dokumen', report: 'Laporan', knowledge: 'Knowledge',
};

const typeIcon: Record<string, typeof Search> = {
    task: CheckSquare, meeting: Video, document: FolderOpen, report: FileText, knowledge: BookOpen,
};

const totalCount = computed(() => Object.values(props.counts).reduce((s, n) => s + n, 0));

const visibleResults = computed((): SearchResult[] => {
    if (!activeType.value) {
        return Object.values(props.results).flat();
    }
    return props.results[activeType.value] ?? [];
});

function applySearch(): void {
    router.get('/search', {
        q:     searchQuery.value || undefined,
        types: activeType.value ? [activeType.value] : undefined,
    }, { preserveState: true, replace: true });
}

function setType(key: string): void {
    activeType.value = key;
    router.get('/search', {
        q:     searchQuery.value || undefined,
        types: key ? [key] : undefined,
    }, { preserveState: true, replace: true });
}

function navigate(url: string): void {
    router.visit(url);
}

function tabCount(key: string): number {
    if (!key) return totalCount.value;
    return props.counts[key] ?? 0;
}
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Pencarian</span>
            <span class="mx-1" style="color: var(--text-muted);">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Hasil</span>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Search input -->
            <div
                class="flex items-center gap-3 px-4 py-3 rounded-xl border mb-6"
                style="background: var(--card-bg); border-color: var(--border-color);"
            >
                <Search :size="16" style="color: var(--text-muted);" class="shrink-0" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari task, meeting, dokumen, laporan, knowledge..."
                    class="flex-1 bg-transparent text-sm outline-none"
                    style="color: var(--text-primary);"
                    @keydown.enter="applySearch"
                />
                <button
                    @click="applySearch"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-opacity hover:opacity-80"
                    style="background: var(--color-primary); color: #fff;"
                >Cari</button>
            </div>

            <!-- Type filter tabs -->
            <div class="flex items-center gap-2 flex-wrap mb-6">
                <button
                    v-for="tab in typeConfig"
                    :key="tab.key"
                    @click="setType(tab.key)"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all border"
                    :style="activeType === tab.key
                        ? 'background: var(--color-primary); color: #fff; border-color: var(--color-primary);'
                        : 'background: var(--bg-tertiary); color: var(--text-secondary); border-color: var(--border-color);'"
                >
                    <component :is="tab.icon" :size="12" />
                    {{ tab.label }}
                    <span
                        v-if="query && tabCount(tab.key) > 0"
                        class="ml-0.5 px-1.5 py-0.5 rounded-full text-[10px]"
                        :style="activeType === tab.key
                            ? 'background: rgba(255,255,255,0.25); color: #fff;'
                            : 'background: var(--border-color); color: var(--text-muted);'"
                    >{{ tabCount(tab.key) }}</span>
                </button>
            </div>

            <!-- Results -->
            <div v-if="!query" class="text-center py-16" style="color: var(--text-muted);">
                <Search :size="40" class="mx-auto mb-4 opacity-30" />
                <p class="text-sm">Masukkan kata kunci untuk mulai mencari</p>
            </div>

            <div v-else-if="visibleResults.length === 0" class="text-center py-16" style="color: var(--text-muted);">
                <Search :size="40" class="mx-auto mb-4 opacity-30" />
                <p class="text-base font-medium mb-1" style="color: var(--text-primary);">Tidak ada hasil</p>
                <p class="text-sm">Tidak ada hasil untuk "<strong>{{ query }}</strong>"</p>
                <p class="text-sm mt-1">Coba kata kunci yang berbeda atau periksa ejaan</p>
            </div>

            <div v-else class="space-y-3">
                <p class="text-xs mb-4" style="color: var(--text-muted);">
                    Ditemukan {{ visibleResults.length }} hasil untuk "<strong style="color: var(--text-primary);">{{ query }}</strong>"
                </p>

                <button
                    v-for="item in visibleResults"
                    :key="item.type + item.id"
                    @click="navigate(item.url)"
                    class="w-full flex items-start gap-4 p-4 rounded-xl border text-left transition-all hover:opacity-90"
                    style="background: var(--card-bg); border-color: var(--border-color);"
                >
                    <div
                        class="mt-0.5 w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                        :style="{ background: (typeColor[item.type] ?? '#6B7280') + '22' }"
                    >
                        <component
                            :is="typeIcon[item.type] ?? FileText"
                            :size="18"
                            :style="{ color: typeColor[item.type] ?? '#6B7280' }"
                        />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ item.title }}</span>
                            <span
                                class="shrink-0 text-[10px] px-1.5 py-0.5 rounded font-medium"
                                :style="{ background: (typeColor[item.type] ?? '#6B7280') + '22', color: typeColor[item.type] ?? '#6B7280' }"
                            >{{ typeLabel[item.type] ?? item.type }}</span>
                        </div>

                        <p v-if="item.snippet" class="text-xs leading-relaxed mb-2" style="color: var(--text-secondary);">
                            {{ item.snippet }}
                        </p>

                        <div class="flex items-center gap-3 flex-wrap">
                            <span
                                v-for="(val, key) in item.meta"
                                :key="key"
                                class="text-[10px] px-1.5 py-0.5 rounded"
                                style="background: var(--bg-tertiary); color: var(--text-muted);"
                            >{{ val }}</span>
                        </div>
                    </div>

                    <span class="shrink-0 text-xs" style="color: var(--text-muted);">&rarr;</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
