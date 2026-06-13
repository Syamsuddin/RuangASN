<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, X, Search, Users, ChevronRight } from 'lucide-vue-next';

interface Team {
    id: string; name: string; type: string; description?: string;
    is_active: boolean; is_cross_opd: boolean; start_date?: string; end_date?: string;
    sk_number?: string; members_count: number;
    organization?: { id: string; name: string };
}

interface Props {
    teams: { data: Team[]; current_page: number; last_page: number; total: number; links: any[] };
    users: { id: string; name: string; nip: string }[];
    organizations: { id: string; name: string }[];
    filters: { search?: string };
}

const props = defineProps<Props>();
const showCreate = ref(false);
const filterSearch = ref(props.filters.search ?? '');

const createForm = useForm({
    name: '', type: 'task_force', organization_id: '', description: '',
    is_cross_opd: false, start_date: '', end_date: '', sk_number: '',
});

const applyFilter = () => {
    router.get('/admin/teams', { search: filterSearch.value || undefined }, { preserveState: true, replace: true });
};

const submitCreate = () => {
    createForm.post('/admin/teams', {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
};

const teamTypes = ['task_force', 'project', 'committee', 'working_group', 'cross_opd', 'adhoc'];

const formatDate = (d?: string) => d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Administrasi</span>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Tim</span>
        </template>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Manajemen Tim</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ teams.total }} tim terdaftar</p>
                </div>
                <button @click="showCreate = true" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                    <Plus :size="16" /> Buat Tim
                </button>
            </div>

            <!-- Filter -->
            <div class="flex gap-3 p-4 rounded-xl border" style="background: var(--card-bg); border-color: var(--border-color);">
                <div class="flex items-center gap-2 flex-1 px-3 py-2 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                    <Search :size="14" style="color: var(--text-muted);" />
                    <input v-model="filterSearch" @keyup.enter="applyFilter" placeholder="Cari nama tim..." class="flex-1 bg-transparent text-sm outline-none" style="color: var(--text-primary);" />
                </div>
                <button @click="applyFilter" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">Cari</button>
            </div>

            <!-- Cards grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="team in teams.data"
                    :key="team.id"
                    class="rounded-xl border p-4 hover:opacity-90 transition-opacity"
                    style="background: var(--card-bg); border-color: var(--border-color);"
                >
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-sm truncate" style="color: var(--text-primary);">{{ team.name }}</h3>
                            <p v-if="team.organization" class="text-xs mt-0.5 truncate" style="color: var(--text-muted);">{{ team.organization.name }}</p>
                        </div>
                        <span
                            class="shrink-0 text-[11px] px-2 py-0.5 rounded-full font-medium"
                            :style="team.is_active ? 'background:rgba(16,185,129,0.15);color:#34D399' : 'background:rgba(100,116,139,0.15);color:#94A3B8'"
                        >{{ team.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <p v-if="team.description" class="text-xs mb-3 line-clamp-2" style="color: var(--text-secondary);">{{ team.description }}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1 text-xs" style="color: var(--text-muted);">
                            <Users :size="13" />
                            <span>{{ team.members_count }} anggota</span>
                        </div>
                        <Link :href="`/admin/teams/${team.id}`" class="flex items-center gap-1 text-xs font-medium" style="color: #3B82F6;">
                            Detail <ChevronRight :size="13" />
                        </Link>
                    </div>
                    <div v-if="team.sk_number" class="mt-2 pt-2 border-t text-xs" style="border-color: var(--border-color); color: var(--text-muted);">
                        SK: {{ team.sk_number }}
                    </div>
                </div>
                <div v-if="teams.data.length === 0" class="col-span-3 text-center py-12 text-sm" style="color: var(--text-muted);">
                    Belum ada tim
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="teams.last_page > 1" class="flex justify-end gap-1">
                <template v-for="link in teams.links" :key="link.label">
                    <button v-if="link.url" @click="router.get(link.url)" class="px-3 py-1.5 rounded text-xs" :style="link.active ? 'background:#3B82F6;color:white' : 'background:var(--bg-secondary);color:var(--text-secondary)'" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Create slide-over -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex">
                <div class="flex-1 bg-black/40" @click="showCreate = false" />
                <div class="w-[480px] h-full overflow-y-auto border-l p-6 shadow-2xl" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Buat Tim Baru</h2>
                        <button @click="showCreate = false" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama Tim</label>
                            <input v-model="createForm.name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-400 mt-1">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tipe Tim</label>
                            <select v-model="createForm.type" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option v-for="t in teamTypes" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Unit Kerja Penanggung Jawab</label>
                            <select v-model="createForm.organization_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih unit kerja</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Deskripsi</label>
                            <textarea v-model="createForm.description" rows="3" class="w-full px-3 py-2 rounded-lg border text-sm outline-none resize-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tanggal Mulai</label>
                                <input v-model="createForm.start_date" type="date" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tanggal Selesai</label>
                                <input v-model="createForm.end_date" type="date" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nomor SK</label>
                            <input v-model="createForm.sk_number" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Mis. 001/SK/2026" />
                        </div>
                        <div class="flex items-center gap-3">
                            <input id="is_cross_opd" v-model="createForm.is_cross_opd" type="checkbox" class="w-4 h-4 rounded" />
                            <label for="is_cross_opd" class="text-sm" style="color: var(--text-secondary);">Tim lintas OPD</label>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showCreate = false" class="flex-1 px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                                {{ createForm.processing ? 'Menyimpan...' : 'Buat Tim' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
