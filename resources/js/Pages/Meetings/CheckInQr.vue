<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, QrCode, Clock, MapPin } from 'lucide-vue-next';

interface MeetingInfo {
    id: string;
    title: string;
    scheduled_at: string;
    location?: string;
}

interface Props {
    meeting: MeetingInfo;
    qrSvg: string;
    signedUrl: string;
}

const props = defineProps<Props>();

const formatDateTime = (iso: string) =>
    new Date(iso).toLocaleString('id-ID', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });

const copyUrl = () => {
    navigator.clipboard.writeText(props.signedUrl);
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link :href="`/meetings/${meeting.id}`" style="color: var(--text-muted);" class="hover:opacity-80">
                {{ meeting.title }}
            </Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">QR Absensi</span>
        </template>

        <div class="max-w-[600px] mx-auto space-y-4">
            <!-- Header -->
            <div
                class="rounded-xl p-5"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <Link :href="`/meetings/${meeting.id}`" class="flex items-center gap-1.5 text-sm font-medium hover:opacity-80 mb-4" style="color: #3B82F6;">
                    <ArrowLeft :size="16" /> Kembali ke Meeting
                </Link>

                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.12);">
                        <QrCode :size="20" style="color: #3B82F6;" />
                    </div>
                    <div>
                        <h1 class="text-lg font-bold" style="color: var(--text-primary);">QR Absensi</h1>
                        <p class="text-sm" style="color: var(--text-muted);">{{ meeting.title }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs mt-3" style="color: var(--text-muted);">
                    <div class="flex items-center gap-1.5">
                        <Clock :size="13" />
                        <span>{{ formatDateTime(meeting.scheduled_at) }}</span>
                    </div>
                    <div v-if="meeting.location" class="flex items-center gap-1.5">
                        <MapPin :size="13" />
                        <span>{{ meeting.location }}</span>
                    </div>
                </div>
            </div>

            <!-- QR Code display -->
            <div
                class="rounded-xl p-8 flex flex-col items-center gap-6"
                style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow);"
            >
                <p class="text-sm font-medium" style="color: var(--text-secondary);">
                    Tampilkan QR ini kepada peserta untuk scan absensi
                </p>

                <!-- SVG QR (rendered server-side) -->
                <div
                    class="rounded-xl overflow-hidden p-4 bg-white"
                    style="width: 280px; height: 280px;"
                    v-html="qrSvg"
                />

                <p class="text-xs text-center" style="color: var(--text-muted);">
                    QR berlaku 4 jam. Peserta harus login terlebih dahulu.
                </p>

                <!-- Fallback URL -->
                <div class="w-full">
                    <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);">URL Absensi (fallback)</p>
                    <div
                        class="flex items-center gap-2 rounded-lg px-3 py-2"
                        style="background: var(--bg-tertiary); border: 1px solid var(--border-color);"
                    >
                        <span class="text-xs flex-1 truncate font-mono" style="color: var(--text-secondary);">{{ signedUrl }}</span>
                        <button
                            @click="copyUrl"
                            class="text-xs px-2 py-1 rounded font-medium shrink-0 hover:opacity-80"
                            style="background: rgba(59,130,246,0.1); color: #3B82F6;"
                        >Salin</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
