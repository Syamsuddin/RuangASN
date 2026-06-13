<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BrainCircuit, Trash2, ArrowLeft } from 'lucide-vue-next';

interface Memory {
    id: string;
    memory_type: string;
    scope: string;
    content: string;
    source_type?: string | null;
    expires_at?: string | null;
    created_at?: string | null;
}

defineProps<{ memories: Memory[] }>();

const typeLabels: Record<string, string> = {
    fact: 'Fakta',
    preference: 'Preferensi',
    context: 'Konteks',
    summary: 'Ringkasan',
};

const scopeLabels: Record<string, string> = {
    global: 'Global',
    organization: 'Organisasi',
    user: 'Pengguna',
    conversation: 'Percakapan',
};

const label = (map: Record<string, string>, key: string) => map[key] ?? key;

const formatDate = (iso?: string | null) =>
    iso ? new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';

const remove = (id: string) => {
    if (!window.confirm('Hapus memori AI ini?')) return;
    router.patch(`/ai/memories/${id}`, { action: 'delete' }, { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/ai" style="color: var(--text-muted);" class="hover:opacity-80">AI Assistant</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Memori AI</span>
        </template>

        <div class="max-w-[900px] space-y-4">
            <!-- Header -->
            <div class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                <Link href="/ai" class="flex items-center gap-1.5 text-sm font-medium hover:opacity-80 mb-3" style="color: #3B82F6;">
                    <ArrowLeft :size="16" /> Kembali ke AI Assistant
                </Link>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.12);">
                        <BrainCircuit :size="20" style="color: #8B5CF6;" />
                    </div>
                    <div>
                        <h1 class="text-lg font-bold" style="color: var(--text-primary);">Memori AI</h1>
                        <p class="text-xs" style="color: var(--text-muted);">
                            Catatan yang diingat asisten untuk membantu Anda. Anda dapat menghapus kapan saja.
                        </p>
                    </div>
                </div>
            </div>

            <!-- List -->
            <div class="rounded-xl overflow-hidden" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                <div v-if="memories.length" class="divide-y" style="border-color: var(--border-color);">
                    <div
                        v-for="m in memories"
                        :key="m.id"
                        class="flex items-start gap-3 px-5 py-4"
                        style="border-color: var(--border-color);"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" style="background: rgba(139,92,246,0.15); color: #A78BFA;">
                                    {{ label(typeLabels, m.memory_type) }}
                                </span>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" style="background: var(--bg-tertiary); color: var(--text-muted);">
                                    {{ label(scopeLabels, m.scope) }}
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">{{ m.content }}</p>
                            <p class="text-[11px] mt-1" style="color: var(--text-muted);">Dibuat {{ formatDate(m.created_at) }}</p>
                        </div>
                        <button
                            type="button"
                            @click="remove(m.id)"
                            class="shrink-0 w-8 h-8 rounded-md flex items-center justify-center transition-colors hover:opacity-80"
                            style="color: #F87171; background: rgba(239,68,68,0.1);"
                            title="Hapus memori"
                        >
                            <Trash2 :size="15" />
                        </button>
                    </div>
                </div>

                <div v-else class="px-5 py-12 text-center">
                    <BrainCircuit :size="32" class="mx-auto mb-2" style="color: rgba(139,92,246,0.4);" />
                    <p class="text-sm" style="color: var(--text-muted);">Belum ada memori AI yang tersimpan.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
