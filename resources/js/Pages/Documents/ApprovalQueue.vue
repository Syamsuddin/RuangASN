<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ClipboardCheck, ChevronRight, FileText, Clock } from 'lucide-vue-next';

interface QueueItem {
    id: string;
    title: string;
    document_type: string | { value: string };
    data_classification: number;
    classification_label: string;
    owner?: { id: string; name: string };
    my_step?: number;
    submitted_at?: string;
    status: string | { value: string };
}

interface Props {
    queue: QueueItem[];
    count: number;
}

const props = defineProps<Props>();

const classificationBadge = (level: number) => {
    const map: Record<number, { bg: string; text: string; border?: string }> = {
        1: { bg: 'rgba(16,185,129,0.15)',  text: '#34D399' },
        2: { bg: 'rgba(59,130,246,0.15)',  text: '#60A5FA' },
        3: { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D' },
        4: { bg: 'rgba(239,68,68,0.15)',   text: '#F87171', border: 'rgba(239,68,68,0.3)' },
    };
    return map[level] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
};

const classificationStyle = (level: number) => {
    const cfg = classificationBadge(level);
    const border = cfg.border ? `border: 1px solid ${cfg.border};` : '';
    return `background: ${cfg.bg}; color: ${cfg.text}; ${border}`;
};

const typeLabels: Record<string, string> = {
    letter: 'Surat', regulation: 'Peraturan', sop: 'SOP', report: 'Laporan',
    minutes: 'Notulensi', decision: 'Keputusan', memo: 'Memo', template: 'Template',
    reference: 'Referensi', contract: 'Kontrak', project_doc: 'Dok. Proyek',
    performance_doc: 'Dok. Kinerja',
};

const typeLabel = (t: string | { value: string }) => {
    const key = typeof t === 'string' ? t : t.value;
    return typeLabels[key] ?? key;
};

const formatDate = (iso?: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/documents" style="color: var(--text-muted);" class="hover:opacity-80">Dokumen</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Antrean Persetujuan</span>
        </template>

        <div class="space-y-4 max-w-[1000px]">

            <!-- Page header -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(245,158,11,0.15);">
                    <ClipboardCheck :size="20" style="color: #F59E0B;" />
                </div>
                <div>
                    <h1 class="text-xl font-bold" style="color: var(--text-primary);">Antrean Persetujuan</h1>
                    <p class="text-sm" style="color: var(--text-muted);">
                        <span v-if="count > 0">{{ count }} dokumen menunggu persetujuan Anda</span>
                        <span v-else>Tidak ada dokumen menunggu</span>
                    </p>
                </div>
            </div>

            <!-- Table -->
            <div
                v-if="queue.length > 0"
                class="rounded-xl overflow-hidden"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Dokumen</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Klasifikasi</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Pemilik</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Langkah</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Diajukan</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in queue"
                            :key="item.id"
                            class="border-b hover:opacity-80 transition-opacity"
                            style="border-color: var(--border-color);"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <FileText :size="15" style="color: #F59E0B; shrink-0" />
                                    <div>
                                        <p class="font-medium text-sm line-clamp-1" style="color: var(--text-primary);">{{ item.title }}</p>
                                        <p class="text-xs" style="color: var(--text-muted);">{{ typeLabel(item.document_type) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                    :style="classificationStyle(item.data_classification)"
                                >{{ item.classification_label }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">
                                {{ item.owner?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <Clock :size="12" style="color: #F59E0B;" />
                                    <span class="text-xs font-medium" style="color: #F59E0B;">
                                        Langkah {{ item.my_step ?? '?' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">{{ formatDate(item.submitted_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/documents/${item.id}`"
                                    class="flex items-center gap-1 text-xs font-semibold hover:opacity-80 whitespace-nowrap"
                                    style="color: #F59E0B;"
                                >Tinjau <ChevronRight :size="12" /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="rounded-xl py-16 text-center"
                style="background: var(--card-bg); border: 1px solid var(--border-color);"
            >
                <ClipboardCheck :size="40" class="mx-auto mb-3" style="color: var(--text-muted);" />
                <p class="text-base font-medium mb-1" style="color: var(--text-secondary);">
                    Tidak ada dokumen menunggu persetujuan Anda
                </p>
                <p class="text-sm" style="color: var(--text-muted);">
                    Semua dokumen sudah diproses atau belum ada yang diajukan
                </p>
                <Link
                    href="/documents"
                    class="inline-block mt-4 px-5 py-2 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background: #3B82F6;"
                >Ke Daftar Dokumen</Link>
            </div>

        </div>
    </AppLayout>
</template>
