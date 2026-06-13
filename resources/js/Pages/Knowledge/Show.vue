<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, Eye, ThumbsUp, User, Clock,
    BookOpen, ChevronRight, Edit2, Archive, Plus,
} from 'lucide-vue-next';

interface ArticleVersion {
    id: string;
    version_number: number;
    status: string;
    is_latest: boolean;
    created_at?: string;
}

interface ArticleCard {
    id: string;
    title: string;
    excerpt?: string;
    knowledge_type: string;
    type_label: string;
    status: string;
    view_count: number;
    helpful_count: number;
    author?: { id: string; name: string };
    updated_at?: string;
}

interface Article {
    id: string;
    title: string;
    content?: string;
    excerpt?: string;
    knowledge_type: string;
    type_label?: string;
    status: string;
    version_number: number;
    is_latest: boolean;
    view_count: number;
    helpful_count: number;
    data_classification: number;
    classification_label?: string;
    tags?: string[];
    ai_summary?: string;
    author?: { id: string; name: string };
    publisher?: { id: string; name: string };
    category?: { id: string; name: string; slug: string };
    versions?: ArticleVersion[];
    published_at?: string;
    updated_at?: string;
}

interface Props {
    article: Article;
    can: { update: boolean; publish: boolean; archive: boolean; createVersion: boolean };
    related: ArticleCard[];
}

const props = defineProps<Props>();

const activeTab = ref<'content' | 'versions'>('content');

const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:     { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    in_review: { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Dalam Review' },
    published: { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Published' },
    archived:  { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan' },
    outdated:  { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Kedaluwarsa' },
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

const statusBadgeStyle = (s: string) => {
    const cfg = statusConfig[s] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8' };
    return `background: ${cfg.bg}; color: ${cfg.text};`;
};

const typeBadgeStyle = (t: string) => {
    const cfg = typeBadge[t] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
    return `background: ${cfg.bg}; color: ${cfg.text};`;
};

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const doPublish = () => {
    router.post(`/knowledge/${props.article.id}/publish`, {}, { preserveScroll: true });
};

const doArchive = () => {
    if (confirm('Arsipkan artikel ini?')) {
        router.post(`/knowledge/${props.article.id}/archive`, {}, { preserveScroll: true });
    }
};

const doMarkHelpful = () => {
    router.post(`/knowledge/${props.article.id}/helpful`, {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/knowledge" style="color: var(--text-muted);" class="hover:opacity-80">Knowledge Base</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span v-if="article.category" style="color: var(--text-muted);" class="hover:opacity-80">
                {{ article.category.name }}
                <span class="mx-1">/</span>
            </span>
            <span style="color: var(--text-primary);" class="font-medium truncate max-w-[200px]">{{ article.title }}</span>
        </template>

        <div class="max-w-[1200px] space-y-4">

            <!-- Header card -->
            <div class="rounded-xl p-6" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                <!-- Back + action buttons -->
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <Link href="/knowledge" class="flex items-center gap-1.5 text-sm font-medium hover:opacity-80" style="color: #3B82F6;">
                        <ArrowLeft :size="16" /> Kembali
                    </Link>
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link
                            v-if="can.update"
                            :href="`/knowledge/${article.id}/edit`"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                            style="background: #8B5CF6;"
                        >
                            <Edit2 :size="14" /> Edit
                        </Link>
                        <button
                            v-if="can.publish"
                            @click="doPublish"
                            class="px-3 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                            style="background: #10B981;"
                        >Publish</button>
                        <button
                            v-if="can.createVersion"
                            @click="$router?.visit(`/knowledge/${article.id}/edit`)"
                            class="px-3 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                            style="border-color: var(--border-color); color: var(--text-secondary);"
                        >
                            <Plus :size="14" class="inline mr-1" />Versi Baru
                        </button>
                        <button
                            v-if="can.archive"
                            @click="doArchive"
                            class="px-3 py-2 rounded-lg text-sm border hover:opacity-80"
                            style="border-color: var(--border-color); color: #94A3B8;"
                        >
                            <Archive :size="14" class="inline mr-1" />Arsipkan
                        </button>
                    </div>
                </div>

                <!-- Badges row -->
                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <span
                        class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
                        :style="typeBadgeStyle(article.knowledge_type)"
                    >{{ article.type_label ?? article.knowledge_type }}</span>
                    <span
                        class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
                        :style="statusBadgeStyle(article.status)"
                    >{{ statusConfig[article.status]?.label ?? article.status }}</span>
                    <span v-if="article.is_latest" class="text-[10px] font-bold px-2 py-1 rounded-full" style="background: rgba(16,185,129,0.2); color: #10B981;">
                        v{{ article.version_number }} (Terbaru)
                    </span>
                </div>

                <h1 class="text-2xl font-bold mb-4" style="color: var(--text-primary);">{{ article.title }}</h1>

                <!-- Meta -->
                <div class="flex items-center gap-5 flex-wrap text-sm mb-4" style="color: var(--text-secondary);">
                    <div v-if="article.author" class="flex items-center gap-1.5">
                        <User :size="14" style="color: var(--text-muted);" />
                        <span>{{ article.author.name }}</span>
                    </div>
                    <div v-if="article.published_at" class="flex items-center gap-1.5">
                        <Clock :size="14" style="color: var(--text-muted);" />
                        <span>{{ formatDate(article.published_at) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Eye :size="14" style="color: var(--text-muted);" />
                        <span>{{ article.view_count }} dilihat</span>
                    </div>
                    <div v-if="article.category" class="flex items-center gap-1">
                        <BookOpen :size="14" style="color: var(--text-muted);" />
                        <span>{{ article.category.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Content grid -->
            <div class="grid lg:grid-cols-[1fr_280px] gap-4 items-start">

                <!-- Main content -->
                <div class="rounded-xl overflow-hidden" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                    <!-- Tabs -->
                    <div class="flex border-b" style="border-color: var(--border-color);">
                        <button
                            @click="activeTab = 'content'"
                            class="px-4 py-3 text-sm font-medium border-b-2 transition-colors"
                            :style="activeTab === 'content'
                                ? 'border-color: #3B82F6; color: #3B82F6;'
                                : 'border-color: transparent; color: var(--text-muted);'"
                        >Konten</button>
                        <button
                            v-if="article.versions?.length"
                            @click="activeTab = 'versions'"
                            class="px-4 py-3 text-sm font-medium border-b-2 transition-colors"
                            :style="activeTab === 'versions'
                                ? 'border-color: #3B82F6; color: #3B82F6;'
                                : 'border-color: transparent; color: var(--text-muted);'"
                        >Riwayat Versi ({{ article.versions?.length ?? 0 }})</button>
                    </div>

                    <div class="p-6">
                        <!-- Content tab -->
                        <div v-if="activeTab === 'content'">
                            <!-- Rendered article content -->
                            <div
                                v-if="article.content"
                                class="prose-rte"
                                v-html="article.content"
                            />
                            <p v-else class="text-sm" style="color: var(--text-muted);">Belum ada konten.</p>

                            <!-- AI summary -->
                            <div
                                v-if="article.ai_summary"
                                class="mt-6 rounded-lg p-4"
                                style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.2);"
                            >
                                <p class="text-xs font-semibold mb-1" style="color: #8B5CF6;">Ringkasan AI</p>
                                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ article.ai_summary }}</p>
                            </div>

                            <!-- Helpful button -->
                            <div class="mt-8 pt-6 border-t flex items-center gap-4" style="border-color: var(--border-color);">
                                <p class="text-sm" style="color: var(--text-muted);">Apakah artikel ini bermanfaat?</p>
                                <button
                                    @click="doMarkHelpful"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border transition-colors hover:opacity-80"
                                    style="border-color: var(--border-color); color: var(--text-secondary);"
                                >
                                    <ThumbsUp :size="14" />
                                    Ya, Bermanfaat ({{ article.helpful_count }})
                                </button>
                            </div>
                        </div>

                        <!-- Versions tab -->
                        <div v-else-if="activeTab === 'versions'">
                            <div v-if="article.versions?.length" class="space-y-2">
                                <div
                                    v-for="v in article.versions"
                                    :key="v.id"
                                    class="flex items-center justify-between rounded-lg px-4 py-3"
                                    style="background: var(--bg-tertiary);"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                            style="background: #3B82F6;"
                                        >v{{ v.version_number }}</span>
                                        <div>
                                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                                Versi {{ v.version_number }}
                                                <span v-if="v.is_latest" class="ml-2 text-[10px] font-bold px-1.5 py-0.5 rounded-full" style="background: rgba(16,185,129,0.2); color: #10B981;">Terbaru</span>
                                            </p>
                                            <p class="text-xs" style="color: var(--text-muted);">{{ formatDate(v.created_at) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                            :style="statusBadgeStyle(v.status)"
                                        >{{ statusConfig[v.status]?.label ?? v.status }}</span>
                                        <Link :href="`/knowledge/${v.id}`" class="text-xs hover:opacity-80" style="color: #3B82F6;">
                                            Lihat <ChevronRight :size="12" class="inline" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Info card -->
                    <div class="rounded-xl p-4 space-y-3" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                        <h3 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Info Artikel</h3>

                        <div class="space-y-2.5">
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Tipe</span>
                                <span style="color: var(--text-secondary);">{{ article.type_label ?? article.knowledge_type }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Versi</span>
                                <span style="color: var(--text-secondary);">v{{ article.version_number }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Dilihat</span>
                                <span style="color: var(--text-secondary);">{{ article.view_count }}x</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Bermanfaat</span>
                                <span style="color: var(--text-secondary);">{{ article.helpful_count }}</span>
                            </div>
                            <div v-if="article.publisher" class="flex justify-between text-xs">
                                <span style="color: var(--text-muted);">Dipublish oleh</span>
                                <span style="color: var(--text-secondary);">{{ article.publisher.name }}</span>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div v-if="article.tags?.length" class="border-t pt-3" style="border-color: var(--border-color);">
                            <p class="text-xs mb-2" style="color: var(--text-muted);">Tags</p>
                            <div class="flex gap-1.5 flex-wrap">
                                <span
                                    v-for="tag in article.tags"
                                    :key="tag"
                                    class="text-[11px] px-2 py-0.5 rounded-full"
                                    style="background: var(--bg-tertiary); color: var(--text-muted);"
                                >#{{ tag }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Related articles -->
                    <div v-if="related.length > 0" class="rounded-xl p-4" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                        <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-muted);">Artikel Terkait</h3>
                        <div class="space-y-3">
                            <Link
                                v-for="r in related"
                                :key="r.id"
                                :href="`/knowledge/${r.id}`"
                                class="block group"
                            >
                                <p class="text-sm font-medium leading-snug group-hover:opacity-80 mb-1" style="color: var(--text-primary);">{{ r.title }}</p>
                                <p class="text-xs flex items-center gap-2" style="color: var(--text-muted);">
                                    <Eye :size="10" />{{ r.view_count }}
                                </p>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
