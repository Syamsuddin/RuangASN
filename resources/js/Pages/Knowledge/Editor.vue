<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';

interface Article {
    id: string;
    title: string;
    content?: string;
    excerpt?: string;
    knowledge_type: string;
    category_id?: string;
    data_classification: number;
    tags?: string[];
    status: string;
}

interface Category {
    id: string;
    name: string;
    parent_id: string | null;
}

interface Props {
    article: Article | null;
    categories: Category[];
}

const props = defineProps<Props>();

const isEdit = computed(() => props.article !== null);

const form = useForm({
    title:               props.article?.title ?? '',
    content:             props.article?.content ?? '',
    excerpt:             props.article?.excerpt ?? '',
    knowledge_type:      props.article?.knowledge_type ?? 'wiki',
    category_id:         props.article?.category_id ?? '',
    data_classification: props.article?.data_classification ?? 2,
    tags:                props.article?.tags ?? [] as string[],
});

const tagInput = ref('');
const showNewCatForm = ref(false);
const newCatName = ref('');

const addTag = () => {
    const tag = tagInput.value.trim().toLowerCase();
    if (tag && !form.tags.includes(tag) && form.tags.length < 10) {
        form.tags.push(tag);
        tagInput.value = '';
    }
};

const removeTag = (tag: string) => {
    form.tags = form.tags.filter((t) => t !== tag);
};

const saveDraft = () => {
    if (isEdit.value && props.article) {
        form.patch(`/knowledge/${props.article.id}`, {
            onSuccess: () => router.visit(`/knowledge/${props.article!.id}`),
        });
    } else {
        form.post('/knowledge', {
            onSuccess: () => {},
        });
    }
};

const submitForReview = () => {
    if (isEdit.value && props.article) {
        form.patch(`/knowledge/${props.article.id}`, {
            onSuccess: () => {
                router.post(`/knowledge/${props.article!.id}/transition`, { status: 'in_review' });
            },
        });
    } else {
        form.post('/knowledge', {
            onSuccess: () => {},
        });
    }
};

const addCategory = () => {
    if (!newCatName.value.trim()) return;
    router.post('/knowledge/categories', { name: newCatName.value.trim() }, {
        onSuccess: () => {
            newCatName.value = '';
            showNewCatForm.value = false;
        },
        preserveState: false,
    });
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

const classificationOptions = [
    { value: 1, label: 'Publik' },
    { value: 2, label: 'Internal' },
    { value: 3, label: 'Rahasia' },
    { value: 4, label: 'Sangat Rahasia' },
];
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/knowledge" style="color: var(--text-muted);" class="hover:opacity-80">Knowledge Base</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">{{ isEdit ? 'Edit Artikel' : 'Tulis Artikel' }}</span>
        </template>

        <div class="max-w-[900px] space-y-4">

            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link href="/knowledge" class="flex items-center gap-1.5 text-sm font-medium hover:opacity-80" style="color: #3B82F6;">
                        <ArrowLeft :size="16" />
                    </Link>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ isEdit ? 'Edit Artikel' : 'Tulis Artikel Baru' }}
                    </h1>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="saveDraft"
                        :disabled="form.processing"
                        class="px-4 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                        style="border-color: var(--border-color); color: var(--text-secondary); background: var(--bg-tertiary);"
                    >Simpan Draft</button>
                    <button
                        type="button"
                        @click="submitForReview"
                        :disabled="form.processing || !form.title"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90 transition-opacity"
                        :style="form.processing || !form.title ? 'background: #3B82F6; opacity:0.5;' : 'background: #3B82F6;'"
                    >{{ isEdit ? 'Simpan & Submit' : 'Submit Review' }}</button>
                </div>
            </div>

            <!-- Form card -->
            <div class="rounded-xl p-6 space-y-5" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">

                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                        Judul Artikel <span style="color: #EF4444;">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Judul artikel yang informatif..."
                        class="w-full px-3 py-2.5 rounded-lg text-sm border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs" style="color: #EF4444;">{{ form.errors.title }}</p>
                </div>

                <!-- Type + Classification -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                            Tipe Artikel <span style="color: #EF4444;">*</span>
                        </label>
                        <select
                            v-model="form.knowledge_type"
                            class="w-full px-3 py-2.5 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                        >
                            <option v-for="t in knowledgeTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="form.errors.knowledge_type" class="mt-1 text-xs" style="color: #EF4444;">{{ form.errors.knowledge_type }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">
                            Klasifikasi Data <span style="color: #EF4444;">*</span>
                        </label>
                        <select
                            v-model.number="form.data_classification"
                            class="w-full px-3 py-2.5 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                        >
                            <option v-for="c in classificationOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                        <p v-if="form.errors.data_classification" class="mt-1 text-xs" style="color: #EF4444;">{{ form.errors.data_classification }}</p>
                    </div>
                </div>

                <!-- Category -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold" style="color: var(--text-secondary);">Kategori</label>
                        <button
                            type="button"
                            @click="showNewCatForm = !showNewCatForm"
                            class="text-xs flex items-center gap-1 hover:opacity-80"
                            style="color: #3B82F6;"
                        ><Plus :size="12" /> Kategori Baru</button>
                    </div>
                    <select
                        v-model="form.category_id"
                        class="w-full px-3 py-2.5 rounded-lg text-sm border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                    >
                        <option value="">Tanpa Kategori</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.parent_id ? '  └ ' : '' }}{{ cat.name }}
                        </option>
                    </select>

                    <!-- Inline new category form -->
                    <div v-if="showNewCatForm" class="mt-2 flex gap-2">
                        <input
                            v-model="newCatName"
                            type="text"
                            placeholder="Nama kategori baru..."
                            class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            @keyup.enter="addCategory"
                        />
                        <button
                            type="button"
                            @click="addCategory"
                            class="px-3 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background: #3B82F6;"
                        >Tambah</button>
                        <button
                            type="button"
                            @click="showNewCatForm = false"
                            class="px-3 py-2 rounded-lg text-sm border"
                            style="border-color: var(--border-color); color: var(--text-muted);"
                        ><X :size="14" /></button>
                    </div>
                </div>

                <!-- Tags -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Tags</label>
                    <div class="flex gap-2 flex-wrap mb-2">
                        <span
                            v-for="tag in form.tags"
                            :key="tag"
                            class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-full"
                            style="background: rgba(59,130,246,0.15); color: #60A5FA;"
                        >
                            #{{ tag }}
                            <button type="button" @click="removeTag(tag)" class="ml-1 hover:opacity-70">
                                <X :size="10" />
                            </button>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="tagInput"
                            type="text"
                            placeholder="Tambah tag..."
                            class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            @keyup.enter="addTag"
                        />
                        <button type="button" @click="addTag" class="px-3 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">
                            <Plus :size="14" />
                        </button>
                    </div>
                </div>

                <!-- Rich text editor -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Konten Artikel</label>
                    <RichTextEditor
                        v-model="form.content"
                        placeholder="Tulis konten artikel di sini... Gunakan toolbar untuk memformat teks."
                    />
                    <p v-if="form.errors.content" class="mt-1 text-xs" style="color: #EF4444;">{{ form.errors.content }}</p>
                </div>

                <!-- Excerpt (optional) -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Ringkasan (opsional)</label>
                    <textarea
                        v-model="form.excerpt"
                        rows="3"
                        placeholder="Ringkasan singkat yang tampil di daftar artikel. Jika kosong, akan digenerate otomatis dari konten."
                        class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                    />
                </div>

            </div>

        </div>
    </AppLayout>
</template>
