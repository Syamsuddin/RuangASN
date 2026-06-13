<script setup lang="ts">
import { computed } from 'vue';
import { Sparkles, Check, X, CheckCircle2, XCircle } from 'lucide-vue-next';
import type { AiMessage, ProposedAction } from '@/composables/useAiChat';

const props = defineProps<{
    message: AiMessage;
    action: ProposedAction;
    index: number;
    loading?: boolean;
}>();

const emit = defineEmits<{
    confirm: [index: number];
    reject: [index: number];
}>();

const actionLabels: Record<string, string> = {
    create_task: 'Buat Task',
    schedule_meeting: 'Jadwalkan Rapat',
    generate_report_draft: 'Buat Draft Laporan',
    add_calendar_event: 'Tambah Agenda Kalender',
};

const title = computed(() => {
    const label = actionLabels[props.action.type] ?? props.action.type;
    const payloadTitle = (props.action.payload?.title as string) ?? '';
    return payloadTitle ? `${label}: ${payloadTitle}` : label;
});

// null = pending, true = confirmed, false = rejected
const state = computed(() => props.message.action_confirmed);
const isPending = computed(() => state.value === null);
</script>

<template>
    <div
        class="mt-2 rounded-lg p-3"
        style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.25);"
    >
        <div class="flex items-start gap-2">
            <Sparkles :size="15" class="shrink-0 mt-0.5" style="color: #8B5CF6;" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium" style="color: var(--text-primary);">{{ title }}</p>
                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Aksi diusulkan AI — perlu konfirmasi Anda untuk dijalankan.
                </p>
            </div>
        </div>

        <!-- Pending: action buttons -->
        <div v-if="isPending" class="flex items-center gap-2 mt-2.5">
            <button
                type="button"
                :disabled="loading"
                @click="emit('confirm', index)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold text-white disabled:opacity-50"
                style="background: #8B5CF6;"
            >
                <Check :size="13" /> Setuju
            </button>
            <button
                type="button"
                :disabled="loading"
                @click="emit('reject', index)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium disabled:opacity-50"
                style="background: transparent; color: var(--text-secondary);"
            >
                <X :size="13" /> Tolak
            </button>
        </div>

        <!-- Confirmed -->
        <div v-else-if="state === true" class="flex items-center gap-1.5 mt-2.5 text-xs font-medium" style="color: #10B981;">
            <CheckCircle2 :size="14" /> Disetujui &amp; dijalankan
        </div>

        <!-- Rejected -->
        <div v-else class="flex items-center gap-1.5 mt-2.5 text-xs font-medium" style="color: #F87171;">
            <XCircle :size="14" /> Ditolak
        </div>
    </div>
</template>
