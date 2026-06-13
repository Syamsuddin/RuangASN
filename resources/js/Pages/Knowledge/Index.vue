<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus, Search, BookOpen, Eye, ThumbsUp,
    FolderOpen, Clock, TrendingUp,
} from 'lucide-vue-next';

interface ArticleCard {
    id: string;
    title: string;
    excerpt?: string;
    knowledge_type: string;
    type_label: string;
    status: string;
    version_number: number;
    is_latest: boolean;
    view_count: number;
    helpful_count: number;
    tags: string[];
    author?: { id: string; name: string };
    category?: { id: string; name: string };
    updated_at?: string;
}

interface Category {
    id: string;
    name: string;
    slug: string;
    parent_id: string | null;
}

interface Props {
    articles: ArticleCard[];
    categories: Category[];
    filters: { category?: string; type?: string; status?: string; search?: string };
    popular: ArticleCard[];
    recent: ArticleCard[];
}

const props = defineProps<Props>();

const searchQuery   = ref(props.filters.search ?? '');
const activeCategory = ref(props.filters.category ?? '');
const filterType    = ref(props.filters.type ?? '');

const applyFilters = () => {
    router.get('/knowledge', {
        search:   searchQuery.value || undefined,
        category: activeCategory.value || undefined,
        type:     filterType.value || undefined,
    }, { preserveState: true, replace: true });
};

const typeBadge: Record<string, { bg: string; text: string }> = {
    wiki:            { bg: 'rgba(59,130,246,0.15)', text: '#60A5FA' },
    faq:             { bg: 'rgba(16,185,129,0.15)', text: '#34D399' },
    sop:             { bg: 'rgba(245,158,11,0.15)', text: '#FCD34D' },
    best_practice:   { bg: 'rgba(139,92,246,0.15)', text: '#A78BFA' },
    lesson_learned:  { bg: 'rgba(236,72,153,0.15)', text: '#F472B6' },
    glossary:        { bg: 'rgba(6,182,212,0.15)',  text: '#22D3EE' },
    regulation_note: { bg: 'rgba(239,68,68,0.15)',  text: '#F87171' },
    template:        { bg: 'rgba(100,116,139,0.15)',text: '#94A3B8' },
    directory:       { bg: 'rgba(100,116,139,0.15)',text: '#94A3B8' },
};

const typeBadgeStyle = (type: string) => {
    const cfg = typeBadge[type] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
    return `background: ${cfg.bg}; color: ${cfg.text};`;
};

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const knowledgeTypes = [
    { value: 'wiki', label: 'Wiki' },
    { value: 'faq', label: 'FAQ' },
    { value: 'sop', label: 'SOP' },
    { value: 'best_practice', label: 'Praktik Terbaik' },
    { value: 'lesson_learned', label: 'Pelajaran' },
    { value: 'glossary', label: 'Glosarium' },
    { value: 'regulation_note', label: 'Catatan Regulasi' },
    { value: 'template', label: 'Template' },
    { value: 'directory', label: 'Direktori' },
];
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Knowledge Base</span>
        </template>

        <div class="max-w-[1280px] space-y-6">

            <!-- Hero search bar -->
            <div
                class="rounded-2xl px-8 py-10 text-center"
                style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(139,92,246,0.1)); border: 1px solid var(--border-color);"
            >
                <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">Knowledge Base ASN</h1>
                <p class="text-sm mb-6" style="color: var(--text-muted);">Pusat pengetahuan, panduan, dan SOP untuk ASN</p>
                <div class="relative max-w-xl mx-auto">
                    <Search :size="18" class="absolute left-4 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari artikel, SOP, panduan..."
                        class="w-full pl-11 pr-4 py-3 rounded-full text-sm border-2 outline-none transition-colors"
                        style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </div>

            <!-- Main grid -->
            <div class="grid lg:grid-cols-[220px_1fr] gap-6">

                <!-- Category sidebar -->
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider px-2 mb-3" style="color: var(--text-muted);">Kategori</p>

                    <button
                        @click="activeCategory = ''; applyFilters()"
                        class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                        :style="!activeCategory
                            ? 'background: rgba(59,130,246,0.1); color: #3B82F6;'
                            : 'color: var(--text-secondary); background: transparent;'"
                    >Semua Artikel</button>

                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="activeCategory = cat.id; applyFilters()"
                        class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
                        :style="activeCategory === cat.id
                            ? 'background: rgba(59,130,246,0.1); color: #3B82F6; font-weight: 500;'
                            : 'color: var(--text-secondary); background: transparent;'"
                    >{{ cat.name }}</button>

                    <!-- Type filter -->
                    <div class="pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wider px-2 mb-2" style="color: var(--text-muted);">Tipe</p>
                        <select
                            v-model="filterType"
                            @change="applyFilters"
                            class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-secondary);"
                        >
                            <option value="">Semua Tipe</option>
                            <option v-for="t in knowledgeTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Articles area -->
                <div class="space-y-6">

                    <!-- Action button -->
                    <div class="flex justify-end">
                        <Link
                            href="/knowledge/create"
                            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90 transition-opacity"
                            style="background: #3B82F6;"
                        >
                            <Plus :size="16" /> Tulis Artikel
                        </Link>
                    </div>

                    <!-- Article grid -->
                    <div v-if="articles.length > 0" class="grid gap-4">
                        <Link
                            v-for="a in articles"
                            :key="a.id"
                            :href="`/knowledge/${a.id}`"
                            class="block rounded-xl p-5 hover:opacity-90 transition-opacity"
                            style="background: var(--card-bg); border: 1px solid var(--border-color);"
                        >
                            <!-- Header row -->
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <span
                                    class="text-[11px] font-semibold px-2.5 py-1 rounded-full shrink-0"
                                    :style="typeBadgeStyle(a.knowledge_type)"
                                >{{ a.type_label }}</span>
                                <span v-if="a.status !== 'published'" class="text-[11px] px-2.5 py-1 rounded-full" style="background: rgba(100,116,139,0.15); color: #94A3B8;">
                                    {{ a.status === 'draft' ? 'Draft' : a.status === 'in_review' ? 'Review' : a.status }}
                                </span>
                            </div>

                            <!-- Title -->
                            <h3 class="text-base font-semibold mb-1 leading-snug" style="color: var(--text-primary);">{{ a.title }}</h3>

                            <!-- Excerpt -->
                            <p v-if="a.excerpt" class="text-sm line-clamp-2 mb-3" style="color: var(--text-secondary);">{{ a.excerpt }}</p>

                            <!-- Tags -->
                            <div v-if="a.tags?.length" class="flex gap-1.5 flex-wrap mb-3">
                                <span
                                    v-for="tag in a.tags.slice(0, 4)"
                                    :key="tag"
                                    class="text-[11px] px-2 py-0.5 rounded-full"
                                    style="background: var(--bg-tertiary); color: var(--text-muted);"
                                >#{{ tag }}</span>
                            </div>

                            <!-- Footer -->
                            <div class="flex items-center justify-between text-xs" style="color: var(--text-muted);">
                                <div class="flex items-center gap-3">
                                    <span v-if="a.author" class="flex items-center gap-1">
                                        <div
                                            class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white"
                                            style="background: #3B82F6;"
                                        >{{ a.author.name.charAt(0).toUpperCase() }}</div>
                                        {{ a.author.name }}
                                    </span>
                                    <span v-if="a.category">{{ a.category.name }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center gap-1"><Eye :size="12" /> {{ a.view_count }}</span>
                                    <span class="flex items-center gap-1"><ThumbsUp :size="12" /> {{ a.helpful_count }}</span>
                                    <span>{{ formatDate(a.updated_at) }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="articles.length === 0"
                        class="rounded-xl py-16 text-center"
                        style="background: var(--card-bg); border: 1px solid var(--border-color);"
                    >
                        <BookOpen :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                        <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">Belum ada artikel</p>
                        <p class="text-sm mb-4" style="color: var(--text-muted);">Mulai berbagi pengetahuan</p>
                        <Link
                            href="/knowledge/create"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background: #3B82F6;"
                        ><Plus :size="14" /> Tulis Artikel</Link>
                    </div>

                </div>
            </div>

            <!-- Popular + Recent sections -->
            <div v-if="popular.length > 0 || recent.length > 0" class="grid md:grid-cols-2 gap-6">
                <!-- Popular -->
                <div v-if="popular.length > 0" class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="flex items-center gap-2 mb-4">
                        <TrendingUp :size="16" style="color: #F59E0B;" />
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Artikel Populer</h3>
                    </div>
                    <div class="space-y-3">
                        <Link
                            v-for="(a, idx) in popular"
                            :key="a.id"
                            :href="`/knowledge/${a.id}`"
                            class="flex items-start gap-3 group"
                        >
                            <span
                                class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                style="background: rgba(245,158,11,0.2); color: #F59E0B;"
                            >{{ idx + 1 }}</span>
                            <div>
                                <p class="text-sm font-medium leading-snug group-hover:opacity-80" style="color: var(--text-primary);">{{ a.title }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                                    <Eye :size="10" class="inline mr-1" />{{ a.view_count }} views
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Recent -->
                <div v-if="recent.length > 0" class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="flex items-center gap-2 mb-4">
                        <Clock :size="16" style="color: #3B82F6;" />
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Terbaru</h3>
                    </div>
                    <div class="space-y-3">
                        <Link
                            v-for="a in recent"
                            :key="a.id"
                            :href="`/knowledge/${a.id}`"
                            class="flex items-start gap-3 group"
                        >
                            <span
                                class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                style="background: rgba(59,130,246,0.1);"
                            >
                                <BookOpen :size="12" style="color: #3B82F6;" />
                            </span>
                            <div>
                                <p class="text-sm font-medium leading-snug group-hover:opacity-80" style="color: var(--text-primary);">{{ a.title }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ formatDate(a.updated_at) }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
