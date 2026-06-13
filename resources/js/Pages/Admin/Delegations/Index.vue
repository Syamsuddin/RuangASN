<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, X, Ban, ArrowRight } from 'lucide-vue-next';

interface Delegation {
    id: string;
    delegator?: { id: string; name: string; nip: string };
    delegate?: { id: string; name: string; nip: string };
    type: string | { value: string };
    reason: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    sk_number?: string;
}

interface Props {
    delegations: { data: Delegation[]; current_page: number; last_page: number; total: number; links: any[] };
    users: { id: string; name: string; nip: string }[];
    types: string[];
}

const props = defineProps<Props>();
const showCreate = ref(false);

const createForm = useForm({
    delegator_id: '', delegate_id: '', type: 'plt', reason: '',
    start_date: '', end_date: '', sk_number: '',
});

const submitCreate = () => {
    createForm.post('/admin/delegations', {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
};

const revoke = (d: Delegation) => {
    if (!confirm('Cabut delegasi ini?')) return;
    router.patch(`/admin/delegations/${d.id}/revoke`);
};

const typeVal = (t: string | { value: string }) => typeof t === 'string' ? t : t.value;

const typeBadge = (t: string | { value: string }) => {
    const v = typeVal(t).toUpperCase();
    const map: Record<string, { bg: string; text: string }> = {
        PLT:            { bg: 'rgba(239,68,68,0.15)',   text: '#F87171' },
        PLH:            { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D' },
        SPECIAL_DUTY:   { bg: 'rgba(59,130,246,0.15)',  text: '#60A5FA' },
        APPROVAL_ONLY:  { bg: 'rgba(16,185,129,0.15)',  text: '#34D399' },
        FULL_AUTHORITY: { bg: 'rgba(139,92,246,0.15)',  text: '#A78BFA' },
    };
    return map[v] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
};

const formatDate = (d: string) => new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Administrasi</span>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Delegasi</span>
        </template>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Manajemen Delegasi</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">PLT, PLH, dan delegasi wewenang</p>
                </div>
                <button @click="showCreate = true" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                    <Plus :size="16" /> Buat Delegasi
                </button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border overflow-hidden" style="background: var(--card-bg); border-color: var(--border-color);">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background: var(--bg-secondary);">
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Delegator → Penerima</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Tipe</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Periode</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Status</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">SK</th>
                            <th class="text-right px-4 py-3 font-medium" style="color: var(--text-muted);">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in delegations.data" :key="d.id" class="border-b" style="border-color: var(--border-color);">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div>
                                        <p class="font-medium text-sm" style="color: var(--text-primary);">{{ d.delegator?.name ?? '—' }}</p>
                                        <p class="text-xs font-mono" style="color: var(--text-muted);">{{ d.delegator?.nip }}</p>
                                    </div>
                                    <ArrowRight :size="14" style="color: var(--text-muted);" class="shrink-0" />
                                    <div>
                                        <p class="font-medium text-sm" style="color: var(--text-primary);">{{ d.delegate?.name ?? '—' }}</p>
                                        <p class="text-xs font-mono" style="color: var(--text-muted);">{{ d.delegate?.nip }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="text-[11px] px-2 py-0.5 rounded-full font-semibold uppercase"
                                    :style="{ background: typeBadge(d.type).bg, color: typeBadge(d.type).text }"
                                >{{ typeVal(d.type) }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">
                                {{ formatDate(d.start_date) }} — {{ formatDate(d.end_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="text-[11px] px-2 py-0.5 rounded-full"
                                    :style="d.is_active ? 'background:rgba(16,185,129,0.15);color:#34D399' : 'background:rgba(100,116,139,0.15);color:#94A3B8'"
                                >{{ d.is_active ? 'Aktif' : 'Dicabut' }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono" style="color: var(--text-secondary);">{{ d.sk_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="d.is_active"
                                    @click="revoke(d)"
                                    class="flex items-center gap-1 px-2 py-1 rounded text-xs"
                                    style="background: rgba(239,68,68,0.1); color: #F87171;"
                                    title="Cabut delegasi"
                                >
                                    <Ban :size="12" /> Cabut
                                </button>
                            </td>
                        </tr>
                        <tr v-if="delegations.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-sm" style="color: var(--text-muted);">Belum ada delegasi</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="delegations.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t" style="border-color: var(--border-color);">
                    <span class="text-xs" style="color: var(--text-muted);">Halaman {{ delegations.current_page }} dari {{ delegations.last_page }}</span>
                    <div class="flex gap-1">
                        <template v-for="link in delegations.links" :key="link.label">
                            <button v-if="link.url" @click="router.get(link.url)" class="px-3 py-1.5 rounded text-xs" :style="link.active ? 'background:#3B82F6;color:white' : 'background:var(--bg-secondary);color:var(--text-secondary)'" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create slide-over -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex">
                <div class="flex-1 bg-black/40" @click="showCreate = false" />
                <div class="w-[480px] h-full overflow-y-auto border-l p-6 shadow-2xl" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Buat Delegasi</h2>
                        <button @click="showCreate = false" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tipe Delegasi</label>
                            <select v-model="createForm.type" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option v-for="t in types" :key="t" :value="t">{{ t.toUpperCase() }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Delegator (yang mendelegasikan)</label>
                            <select v-model="createForm.delegator_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih delegator</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.nip }})</option>
                            </select>
                            <p v-if="createForm.errors.delegator_id" class="text-xs text-red-400 mt-1">{{ createForm.errors.delegator_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Penerima Delegasi</label>
                            <select v-model="createForm.delegate_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih penerima</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.nip }})</option>
                            </select>
                            <p v-if="createForm.errors.delegate_id" class="text-xs text-red-400 mt-1">{{ createForm.errors.delegate_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Alasan / Dasar Hukum</label>
                            <textarea v-model="createForm.reason" rows="3" class="w-full px-3 py-2 rounded-lg border text-sm outline-none resize-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                            <p v-if="createForm.errors.reason" class="text-xs text-red-400 mt-1">{{ createForm.errors.reason }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tanggal Mulai</label>
                                <input v-model="createForm.start_date" type="date" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                                <p v-if="createForm.errors.start_date" class="text-xs text-red-400 mt-1">{{ createForm.errors.start_date }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tanggal Selesai</label>
                                <input v-model="createForm.end_date" type="date" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                                <p v-if="createForm.errors.end_date" class="text-xs text-red-400 mt-1">{{ createForm.errors.end_date }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nomor SK</label>
                            <input v-model="createForm.sk_number" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Opsional" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showCreate = false" class="flex-1 px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                                {{ createForm.processing ? 'Menyimpan...' : 'Buat Delegasi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
