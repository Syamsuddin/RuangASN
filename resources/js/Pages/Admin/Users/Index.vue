<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, Search, X, UserCheck, UserX, Pencil, Shield } from 'lucide-vue-next';

interface UserRow {
    id: string; nip: string; name: string; email: string;
    status: string | { value: string };
    user_type: string | { value: string };
    organization?: { id: string; name: string };
    roles: string[];
    avatar_path?: string;
}

interface Props {
    users: { data: UserRow[]; current_page: number; last_page: number; total: number; links: any[] };
    roles: string[];
    organizations: { id: string; name: string; short_name: string }[];
    filters: { search?: string; role?: string; status?: string };
}

const props = defineProps<Props>();
const showCreate = ref(false);
const editTarget = ref<UserRow | null>(null);

const filterSearch = ref(props.filters.search ?? '');
const filterRole   = ref(props.filters.role   ?? '');
const filterStatus = ref(props.filters.status  ?? '');

const applyFilters = () => {
    router.get('/admin/users', {
        search: filterSearch.value || undefined,
        role:   filterRole.value   || undefined,
        status: filterStatus.value || undefined,
    }, { preserveState: true, replace: true });
};

const createForm = useForm({
    nip: '', name: '', email: '', user_type: 'pns',
    organization_id: '', role: '', password: '',
});

const editForm = useForm({
    name: '', email: '', user_type: '', organization_id: '', role: '',
});

const submitCreate = () => {
    createForm.post('/admin/users', {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
};

const openEdit = (user: UserRow) => {
    editTarget.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.user_type = typeof user.user_type === 'string' ? user.user_type : user.user_type.value;
    editForm.organization_id = user.organization?.id ?? '';
    editForm.role = user.roles[0] ?? '';
};

const submitEdit = () => {
    if (!editTarget.value) return;
    editForm.patch(`/admin/users/${editTarget.value.id}`, {
        onSuccess: () => { editTarget.value = null; },
    });
};

const toggleStatus = (user: UserRow) => {
    if (!confirm(`${statusVal(user.status) === 'active' ? 'Nonaktifkan' : 'Aktifkan'} pengguna ${user.name}?`)) return;
    router.patch(`/admin/users/${user.id}/deactivate`);
};

const statusVal = (s: string | { value: string }) => typeof s === 'string' ? s : s.value;

const statusBadge = (s: string | { value: string }) => {
    const v = statusVal(s);
    const map: Record<string, { bg: string; text: string; label: string }> = {
        active:    { bg: 'rgba(16,185,129,0.15)',  text: '#34D399', label: 'Aktif' },
        inactive:  { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: 'Nonaktif' },
        suspended: { bg: 'rgba(239,68,68,0.15)',   text: '#F87171', label: 'Suspended' },
    };
    return map[v] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: v };
};

const roleBadge = (role: string) => {
    const map: Record<string, { bg: string; text: string }> = {
        super_admin:   { bg: 'rgba(239,68,68,0.15)',   text: '#F87171' },
        admin_pemda:   { bg: 'rgba(139,92,246,0.15)',  text: '#A78BFA' },
        kepala_opd:    { bg: 'rgba(59,130,246,0.15)',  text: '#60A5FA' },
        kepala_bidang: { bg: 'rgba(16,185,129,0.15)',  text: '#34D399' },
        asn:           { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' },
    };
    return map[role] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
};

const userTypes = ['pns', 'pppk', 'honorer', 'outsource', 'guest'];

const initials = (name: string) =>
    name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Administrasi</span>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Manajemen Pengguna</span>
        </template>

        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Manajemen Pengguna</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">{{ users.total }} pengguna terdaftar</p>
                </div>
                <button
                    @click="showCreate = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Tambah Pengguna
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 p-4 rounded-xl border" style="background: var(--card-bg); border-color: var(--border-color);">
                <div class="flex items-center gap-2 flex-1 min-w-[200px] px-3 py-2 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                    <Search :size="14" style="color: var(--text-muted);" />
                    <input
                        v-model="filterSearch"
                        placeholder="Cari nama, NIP, email..."
                        class="flex-1 bg-transparent text-sm outline-none"
                        style="color: var(--text-primary);"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <select
                    v-model="filterRole"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg border text-sm outline-none"
                    style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                >
                    <option value="">Semua Role</option>
                    <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                </select>
                <select
                    v-model="filterStatus"
                    @change="applyFilters"
                    class="px-3 py-2 rounded-lg border text-sm outline-none"
                    style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                >
                    <option value="">Semua Status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <button @click="applyFilters" class="px-4 py-2 rounded-lg text-sm font-medium" style="background: #3B82F6; color: white;">Cari</button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border overflow-hidden" style="background: var(--card-bg); border-color: var(--border-color);">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background: var(--bg-secondary);">
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Pengguna</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">NIP</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Unit Kerja</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Role</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Status</th>
                            <th class="text-right px-4 py-3 font-medium" style="color: var(--text-muted);">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-b transition-colors hover:opacity-90"
                            style="border-color: var(--border-color);"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#3B82F6] flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ initials(user.name) }}
                                    </div>
                                    <div>
                                        <p class="font-medium" style="color: var(--text-primary);">{{ user.name }}</p>
                                        <p class="text-xs" style="color: var(--text-muted);">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs" style="color: var(--text-secondary);">{{ user.nip }}</td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">
                                {{ user.organization?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-for="role in user.roles"
                                    :key="role"
                                    class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium mr-1"
                                    :style="{ background: roleBadge(role).bg, color: roleBadge(role).text }"
                                >{{ role }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium"
                                    :style="{ background: statusBadge(user.status).bg, color: statusBadge(user.status).text }"
                                >{{ statusBadge(user.status).label }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openEdit(user)" class="p-1.5 rounded-md hover:opacity-70" style="color: var(--text-muted);" title="Edit">
                                        <Pencil :size="14" />
                                    </button>
                                    <button @click="toggleStatus(user)" class="p-1.5 rounded-md hover:opacity-70" :style="{ color: statusVal(user.status) === 'active' ? '#EF4444' : '#10B981' }" :title="statusVal(user.status) === 'active' ? 'Nonaktifkan' : 'Aktifkan'">
                                        <UserX v-if="statusVal(user.status) === 'active'" :size="14" />
                                        <UserCheck v-else :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-sm" style="color: var(--text-muted);">Tidak ada pengguna ditemukan</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t" style="border-color: var(--border-color);">
                    <span class="text-xs" style="color: var(--text-muted);">Halaman {{ users.current_page }} dari {{ users.last_page }}</span>
                    <div class="flex gap-1">
                        <template v-for="link in users.links" :key="link.label">
                            <button
                                v-if="link.url"
                                @click="router.get(link.url)"
                                class="px-3 py-1.5 rounded text-xs"
                                :style="link.active ? 'background:#3B82F6; color:white;' : 'background: var(--bg-secondary); color: var(--text-secondary);'"
                                v-html="link.label"
                            />
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
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Tambah Pengguna</h2>
                        <button @click="showCreate = false" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">NIP</label>
                            <input v-model="createForm.nip" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="18 digit NIP" maxlength="18" />
                            <p v-if="createForm.errors.nip" class="text-xs text-red-400 mt-1">{{ createForm.errors.nip }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama Lengkap</label>
                            <input v-model="createForm.name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Nama sesuai SK" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-400 mt-1">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Email</label>
                            <input v-model="createForm.email" type="email" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="email@pemda.go.id" />
                            <p v-if="createForm.errors.email" class="text-xs text-red-400 mt-1">{{ createForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tipe Pengguna</label>
                            <select v-model="createForm.user_type" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option v-for="t in userTypes" :key="t" :value="t">{{ t.toUpperCase() }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Unit Kerja</label>
                            <select v-model="createForm.organization_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih unit kerja</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                            <p v-if="createForm.errors.organization_id" class="text-xs text-red-400 mt-1">{{ createForm.errors.organization_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Role</label>
                            <select v-model="createForm.role" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih role</option>
                                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                            </select>
                            <p v-if="createForm.errors.role" class="text-xs text-red-400 mt-1">{{ createForm.errors.role }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Password (opsional — generate otomatis jika kosong)</label>
                            <input v-model="createForm.password" type="password" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Min 8 karakter" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showCreate = false" class="flex-1 px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                                {{ createForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Edit slide-over -->
        <Teleport to="body">
            <div v-if="editTarget" class="fixed inset-0 z-50 flex">
                <div class="flex-1 bg-black/40" @click="editTarget = null" />
                <div class="w-[480px] h-full overflow-y-auto border-l p-6 shadow-2xl" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Edit Pengguna</h2>
                        <button @click="editTarget = null" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama</label>
                            <input v-model="editForm.name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Email</label>
                            <input v-model="editForm.email" type="email" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tipe</label>
                            <select v-model="editForm.user_type" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option v-for="t in userTypes" :key="t" :value="t">{{ t.toUpperCase() }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Unit Kerja</label>
                            <select v-model="editForm.organization_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih unit kerja</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Role</label>
                            <select v-model="editForm.role" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih role</option>
                                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                            </select>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="editTarget = null" class="flex-1 px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                                {{ editForm.processing ? 'Menyimpan...' : 'Perbarui' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
