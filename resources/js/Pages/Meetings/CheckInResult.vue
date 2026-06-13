<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle2, Clock, AlertCircle } from 'lucide-vue-next';

interface MeetingInfo {
    id: string;
    title: string;
    scheduled_at: string;
}

interface Props {
    meeting: MeetingInfo;
    status: string;
    statusLabel: string;
    checkInAt: string;
}

const props = defineProps<Props>();

const isPresent = props.status === 'present';

const formatDateTime = (iso: string) =>
    new Date(iso).toLocaleString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Absensi Meeting</span>
        </template>

        <div class="max-w-[480px] mx-auto">
            <div
                class="rounded-xl p-8 flex flex-col items-center text-center gap-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <!-- Icon -->
                <div
                    class="w-20 h-20 rounded-full flex items-center justify-center"
                    :style="isPresent
                        ? 'background: rgba(16,185,129,0.15);'
                        : 'background: rgba(245,158,11,0.15);'"
                >
                    <component
                        :is="isPresent ? CheckCircle2 : AlertCircle"
                        :size="40"
                        :style="isPresent ? 'color: #10B981;' : 'color: #F59E0B;'"
                    />
                </div>

                <div>
                    <h1 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">
                        Absensi Berhasil
                    </h1>
                    <p class="text-base font-semibold" :style="isPresent ? 'color: #10B981;' : 'color: #F59E0B;'">
                        Status: {{ statusLabel }}
                    </p>
                </div>

                <div class="w-full rounded-lg p-4 space-y-2 text-sm" style="background: var(--bg-tertiary);">
                    <div class="flex justify-between">
                        <span style="color: var(--text-muted);">Meeting</span>
                        <span class="font-medium text-right max-w-[220px] truncate" style="color: var(--text-primary);">{{ meeting.title }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-muted);">Check-in</span>
                        <span style="color: var(--text-secondary);">{{ formatDateTime(checkInAt) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 text-xs" style="color: var(--text-muted);">
                    <Clock :size="12" />
                    <span>Dijadwalkan: {{ formatDateTime(meeting.scheduled_at) }}</span>
                </div>

                <Link
                    :href="`/meetings/${meeting.id}`"
                    class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                    style="background: #3B82F6;"
                >
                    Lihat Detail Meeting
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
