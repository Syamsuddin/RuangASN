<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Activity, Plug, PlayCircle, Wifi, CheckCircle, XCircle, AlertTriangle,
    Clock, ArrowDownLeft, ArrowUpRight, ShieldCheck, ShieldAlert,
} from 'lucide-vue-next';

interface Run {
    id: string;
    provider: string;
    direction: string;
    operation: string;
    status: string;
    items_processed: number;
    items_failed: number;
    summary: string | null;
    error_message: string | null;
    started_at: string | null;
    finished_at: string | null;
    triggered_by: string | null;
    created_at: string | null;
}
interface WebhookEvent {
    id: string;
    provider: string;
    event_id: string | null;
    signature_valid: boolean;
    processed: boolean;
    body_excerpt: string | null;
    created_at: string | null;
}
interface ProviderCard {
    value: string;
    label: string;
    configured: boolean;
}

const props = defineProps<{
    runs: Run[];
    events: WebhookEvent[];
    providers: ProviderCard[];
    canRun: boolean;
    canManage: boolean;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});
const successMsg = computed(() => flash.value.success as string | null);
const errorMsg = computed(() => {
    const e = (page.props as any).errors ?? {};
    return (e.connection || e.provider) as string | undefined;
});

const statusStyle = (status: string): { bg: string; color: string } => {
    switch (status) {
        case 'success': return { bg: 'rgba(16,185,129,0.12)', color: '#10B981' };
        case 'partial': return { bg: 'rgba(245,158,11,0.12)', color: '#F59E0B' };
        case 'failed':  return { bg: 'rgba(239,68,68,0.12)', color: '#EF4444' };
        case 'running': return { bg: 'rgba(59,130,246,0.12)', color: '#3B82F6' };
        default:        return { bg: 'var(--bg-tertiary)', color: 'var(--text-muted)' };
    }
};

const fmt = (iso: string | null): string => {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }); }
    catch { return iso; }
};

const triggerRun = (provider: string) => {
    router.post('/admin/integrations/run', { provider }, { preserveScroll: true });
};
const testConnection = (provider: string) => {
    router.post('/admin/integrations/test', { provider }, { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">
                <a href="/admin/integrations" style="color: var(--text-muted);">Integrasi</a>
            </span>
            <span class="mx-1.5" style="color: var(--text-muted);">/</span>
            <span style="color: var(--text-primary);">Monitor</span>
        </template>

        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs mb-1" style="color: var(--text-muted);">
                <Activity :size="13" /> Administrasi / Integrasi / Monitor
            </div>
            <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Monitor Integrasi</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-muted);">
                Riwayat sinkronisasi &amp; webhook masuk. Nilai rahasia/PII disunting dari ringkasan.
            </p>
        </div>

        <div v-if="successMsg" class="mb-5 rounded-lg px-4 py-3 text-sm flex items-center gap-2"
            style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10B981;">
            <CheckCircle :size="16" /> {{ successMsg }}
        </div>
        <div v-if="errorMsg" class="mb-5 rounded-lg px-4 py-3 text-sm flex items-center gap-2"
            style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #EF4444;">
            <XCircle :size="16" /> {{ errorMsg }}
        </div>

        <!-- Provider action cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div v-for="p in providers" :key="p.value"
                class="rounded-xl border p-4 flex flex-col gap-3"
                style="background: var(--card-bg); border-color: var(--border-color);">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Plug :size="16" style="color: var(--text-muted);" />
                        <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ p.label }}</span>
                    </div>
                    <span class="text-[10px] rounded px-1.5 py-0.5 inline-flex items-center gap-1"
                        :style="p.configured
                            ? 'background: rgba(16,185,129,0.12); color:#10B981;'
                            : 'background: var(--bg-tertiary); color: var(--text-muted);'">
                        <ShieldCheck v-if="p.configured" :size="11" />
                        <ShieldAlert v-else :size="11" />
                        {{ p.configured ? 'Terkonfigurasi' : 'Belum diatur' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="canRun" type="button" @click="triggerRun(p.value)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white transition"
                        style="background: #8B5CF6;">
                        <PlayCircle :size="14" /> Jalankan Sinkronisasi
                    </button>
                    <button v-if="canManage" type="button" @click="testConnection(p.value)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition"
                        style="border-color: var(--border-color); color: var(--text-secondary);">
                        <Wifi :size="14" /> Tes Koneksi
                    </button>
                </div>
            </div>
        </section>

        <!-- Runs table -->
        <section class="rounded-xl border overflow-hidden mb-8"
            style="background: var(--card-bg); border-color: var(--border-color);">
            <div class="px-5 py-3 border-b" style="border-color: var(--border-color);">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Riwayat Sinkronisasi</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="color: var(--text-muted);">
                            <th class="text-left font-medium px-5 py-2">Provider</th>
                            <th class="text-left font-medium px-5 py-2">Arah</th>
                            <th class="text-left font-medium px-5 py-2">Operasi</th>
                            <th class="text-left font-medium px-5 py-2">Status</th>
                            <th class="text-left font-medium px-5 py-2">Item</th>
                            <th class="text-left font-medium px-5 py-2">Selesai</th>
                            <th class="text-left font-medium px-5 py-2">Pemicu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="runs.length === 0">
                            <td colspan="7" class="px-5 py-6 text-center" style="color: var(--text-muted);">
                                Belum ada riwayat sinkronisasi.
                            </td>
                        </tr>
                        <tr v-for="r in runs" :key="r.id" class="border-t" style="border-color: var(--border-color);">
                            <td class="px-5 py-2.5 font-medium capitalize" style="color: var(--text-primary);">{{ r.provider }}</td>
                            <td class="px-5 py-2.5">
                                <span class="inline-flex items-center gap-1" style="color: var(--text-muted);">
                                    <ArrowDownLeft v-if="r.direction === 'inbound'" :size="13" />
                                    <ArrowUpRight v-else :size="13" />
                                    {{ r.direction }}
                                </span>
                            </td>
                            <td class="px-5 py-2.5" style="color: var(--text-secondary);">{{ r.operation }}</td>
                            <td class="px-5 py-2.5">
                                <span class="inline-flex items-center gap-1 text-xs rounded px-2 py-0.5 capitalize"
                                    :style="{ background: statusStyle(r.status).bg, color: statusStyle(r.status).color }">
                                    <CheckCircle v-if="r.status === 'success'" :size="12" />
                                    <AlertTriangle v-else-if="r.status === 'partial'" :size="12" />
                                    <XCircle v-else-if="r.status === 'failed'" :size="12" />
                                    <Clock v-else :size="12" />
                                    {{ r.status }}
                                </span>
                            </td>
                            <td class="px-5 py-2.5" style="color: var(--text-secondary);">
                                {{ r.items_processed }}<span v-if="r.items_failed" style="color:#EF4444;"> / {{ r.items_failed }} gagal</span>
                            </td>
                            <td class="px-5 py-2.5" style="color: var(--text-muted);">{{ fmt(r.finished_at) }}</td>
                            <td class="px-5 py-2.5" style="color: var(--text-muted);">{{ r.triggered_by ?? 'Sistem' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Webhook events table -->
        <section class="rounded-xl border overflow-hidden"
            style="background: var(--card-bg); border-color: var(--border-color);">
            <div class="px-5 py-3 border-b" style="border-color: var(--border-color);">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Webhook Masuk</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="color: var(--text-muted);">
                            <th class="text-left font-medium px-5 py-2">Provider</th>
                            <th class="text-left font-medium px-5 py-2">Event ID</th>
                            <th class="text-left font-medium px-5 py-2">Tanda Tangan</th>
                            <th class="text-left font-medium px-5 py-2">Diproses</th>
                            <th class="text-left font-medium px-5 py-2">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="events.length === 0">
                            <td colspan="5" class="px-5 py-6 text-center" style="color: var(--text-muted);">
                                Belum ada webhook masuk.
                            </td>
                        </tr>
                        <tr v-for="e in events" :key="e.id" class="border-t" style="border-color: var(--border-color);">
                            <td class="px-5 py-2.5 font-medium capitalize" style="color: var(--text-primary);">{{ e.provider }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs" style="color: var(--text-muted);">{{ e.event_id ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                <span class="inline-flex items-center gap-1 text-xs"
                                    :style="e.signature_valid ? 'color:#10B981;' : 'color:#EF4444;'">
                                    <ShieldCheck v-if="e.signature_valid" :size="13" />
                                    <ShieldAlert v-else :size="13" />
                                    {{ e.signature_valid ? 'Valid' : 'Invalid' }}
                                </span>
                            </td>
                            <td class="px-5 py-2.5" style="color: var(--text-secondary);">{{ e.processed ? 'Ya' : 'Tidak' }}</td>
                            <td class="px-5 py-2.5" style="color: var(--text-muted);">{{ fmt(e.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
