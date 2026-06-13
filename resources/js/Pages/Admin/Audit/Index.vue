<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronDown, ChevronRight, Search } from 'lucide-vue-next';

interface AuditLog {
    id: string; action: string; auditable_type?: string; auditable_id?: string;
    old_values?: Record<string, any>; new_values?: Record<string, any>;
    ip_address?: string; user_agent?: string; created_at: string;
    user?: { id: string; name: string; nip: string };
}

interface Props {
    logs: { data: AuditLog[]; current_page: number; last_page: number; total: number; links: any[] };
    actions: string[];
    filters: { action?: string; user_id?: string; auditable_type?: string; date_from?: string; date_to?: string };
    canViewAll: boolean;
}

const props = defineProps<Props>();
const expanded = ref<Record<string, boolean>>({});

const filterAction       = ref(props.filters.action        ?? '');
const filterAuditableType = ref(props.filters.auditable_type ?? '');
const filterDateFrom     = ref(props.filters.date_from     ?? '');
const filterDateTo       = ref(props.filters.date_to       ?? '');

const applyFilters = () => {
    router.get('/admin/audit', {
        action:         filterAction.value        || undefined,
        auditable_type: filterAuditableType.value || undefined,
        date_from:      filterDateFrom.value      || undefined,
        date_to:        filterDateTo.value        || undefined,
    }, { preserveState: true, replace: true });
};

const toggleExpand = (id: string) => { expanded.value[id] = !expanded.value[id]; };

const actionBadge = (action: string) => {
    const map: Record<string, { bg: string; text: string }> = {
        created:        { bg: 'rgba(16,185,129,0.15)',  text: '#34D399' },
        updated:        { bg: 'rgba(59,130,246,0.15)',  text: '#60A5FA' },
        deleted:        { bg: 'rgba(239,68,68,0.15)',   text: '#F87171' },
        login:          { bg: 'rgba(139,92,246,0.15)',  text: '#A78BFA' },
        logout:         { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' },
        status_changed: { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D' },
        role_assigned:  { bg: 'rgba(20,184,166,0.15)',  text: '#2DD4BF' },
    };
    return map[action] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
};

const formatTime = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const shortType = (t?: string) => t ? t.split('\\').pop() ?? t : '—';

const jsonPretty = (val?: Record<string, any>) => val ? JSON.stringify(val, null, 2) : null;
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Administrasi</span>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Audit Log</span>
        </template>

        <div class="space-y-4">
            <div>
                <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Audit Log</h1>
                <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ logs.total }} catatan aktivitas{{ !canViewAll ? ' (hanya milik Anda)' : '' }}</p>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 p-4 rounded-xl border" style="background: var(--card-bg); border-color: var(--border-color);">
                <select
                    v-model="filterAction"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg border text-sm outline-none"
                    style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                >
                    <option value="">Semua Aksi</option>
                    <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                </select>
                <input
                    v-model="filterAuditableType"
                    @keyup.enter="applyFilters"
                    placeholder="Tipe data (misal: User)"
                    class="px-3 py-2 rounded-lg border text-sm outline-none"
                    style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                />
                <input v-model="filterDateFrom" type="date" @change="applyFilters" class="px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                <input v-model="filterDateTo" type="date" @change="applyFilters" class="px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                <button @click="applyFilters" class="px-4 py-2 rounded-lg text-sm font-medium text-white flex items-center gap-1" style="background: #3B82F6;">
                    <Search :size="14" /> Filter
                </button>
            </div>

            <!-- Log timeline -->
            <div class="rounded-xl border overflow-hidden" style="background: var(--card-bg); border-color: var(--border-color);">
                <div v-for="log in logs.data" :key="log.id" class="border-b last:border-b-0" style="border-color: var(--border-color);">
                    <div
                        class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:opacity-90 transition-opacity"
                        @click="(log.old_values || log.new_values) && toggleExpand(log.id)"
                    >
                        <!-- Action badge -->
                        <span
                            class="mt-0.5 shrink-0 text-[11px] px-2 py-0.5 rounded-full font-medium"
                            :style="{ background: actionBadge(log.action).bg, color: actionBadge(log.action).text }"
                        >{{ log.action }}</span>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ log.user?.name ?? 'Sistem' }}</span>
                                <span v-if="log.user?.nip" class="text-xs font-mono" style="color: var(--text-muted);">{{ log.user.nip }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span v-if="log.auditable_type" class="text-xs px-1.5 py-0.5 rounded font-mono" style="background: var(--bg-tertiary); color: var(--text-muted);">{{ shortType(log.auditable_type) }}</span>
                                <span v-if="log.auditable_id" class="text-xs font-mono" style="color: var(--text-muted);">{{ log.auditable_id.slice(0, 8) }}…</span>
                            </div>
                        </div>

                        <!-- Right: time + ip + expand -->
                        <div class="text-right shrink-0">
                            <p class="text-xs" style="color: var(--text-muted);">{{ formatTime(log.created_at) }}</p>
                            <p v-if="log.ip_address" class="text-[10px] font-mono mt-0.5" style="color: var(--text-muted);">{{ log.ip_address }}</p>
                        </div>
                        <div v-if="log.old_values || log.new_values" class="shrink-0 mt-1" style="color: var(--text-muted);">
                            <ChevronDown v-if="expanded[log.id]" :size="14" />
                            <ChevronRight v-else :size="14" />
                        </div>
                    </div>

                    <!-- Expanded diff -->
                    <div v-if="expanded[log.id] && (log.old_values || log.new_values)" class="px-4 pb-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div v-if="log.old_values">
                                <p class="text-[10px] font-semibold mb-1 uppercase tracking-wider" style="color: var(--text-muted);">Sebelum</p>
                                <pre class="text-[11px] p-2 rounded-lg overflow-x-auto" style="background: rgba(239,68,68,0.06); color: #F87171; white-space: pre-wrap; word-break: break-all;">{{ jsonPretty(log.old_values) }}</pre>
                            </div>
                            <div v-if="log.new_values">
                                <p class="text-[10px] font-semibold mb-1 uppercase tracking-wider" style="color: var(--text-muted);">Sesudah</p>
                                <pre class="text-[11px] p-2 rounded-lg overflow-x-auto" style="background: rgba(16,185,129,0.06); color: #34D399; white-space: pre-wrap; word-break: break-all;">{{ jsonPretty(log.new_values) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="logs.data.length === 0" class="px-4 py-12 text-center text-sm" style="color: var(--text-muted);">
                    Tidak ada log audit
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="flex items-center justify-between">
                <span class="text-xs" style="color: var(--text-muted);">Halaman {{ logs.current_page }} dari {{ logs.last_page }}</span>
                <div class="flex gap-1">
                    <template v-for="link in logs.links" :key="link.label">
                        <button v-if="link.url" @click="router.get(link.url)" class="px-3 py-1.5 rounded text-xs" :style="link.active ? 'background:#3B82F6;color:white' : 'background:var(--bg-secondary);color:var(--text-secondary)'" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
