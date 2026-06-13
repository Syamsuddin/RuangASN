<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, X, Trash2, ChevronLeft } from 'lucide-vue-next';

interface Member {
    id: string; user?: { id: string; name: string; nip: string };
    role: string; joined_at: string; left_at?: string; is_active: boolean;
}

interface Team {
    id: string; name: string; type: string; description?: string;
    is_active: boolean; sk_number?: string;
    organization?: { id: string; name: string };
    members: Member[];
}

interface Props {
    team: Team;
    users: { id: string; name: string; nip: string }[];
}

const props = defineProps<Props>();
const showAddMember = ref(false);

const addForm = useForm({ user_id: '', role: 'member' });

const submitAdd = () => {
    addForm.post(`/admin/teams/${props.team.id}/members`, {
        onSuccess: () => { showAddMember.value = false; addForm.reset(); },
    });
};

const removeMember = (member: Member) => {
    if (!confirm(`Keluarkan ${member.user?.name} dari tim?`)) return;
    router.delete(`/admin/teams/${props.team.id}/members/${member.id}`);
};

const memberRoles = ['leader', 'secretary', 'member', 'observer'];

const formatDate = (d?: string) => d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const initials = (name: string) => name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/admin/teams" style="color: var(--text-muted);" class="hover:opacity-70">Tim</Link>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">{{ team.name }}</span>
        </template>

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <Link href="/admin/teams" class="flex items-center gap-1 text-sm" style="color: var(--text-muted);">
                            <ChevronLeft :size="16" /> Kembali
                        </Link>
                    </div>
                    <h1 class="text-xl font-semibold" style="color: var(--text-primary);">{{ team.name }}</h1>
                    <p v-if="team.organization" class="text-sm mt-0.5" style="color: var(--text-muted);">{{ team.organization.name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="px-3 py-1 rounded-full text-sm font-medium"
                        :style="team.is_active ? 'background:rgba(16,185,129,0.15);color:#34D399' : 'background:rgba(100,116,139,0.15);color:#94A3B8'"
                    >{{ team.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    <button @click="showAddMember = true" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                        <Plus :size="16" /> Tambah Anggota
                    </button>
                </div>
            </div>

            <!-- Info card -->
            <div class="rounded-xl border p-4" style="background: var(--card-bg); border-color: var(--border-color);">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-medium mb-0.5" style="color: var(--text-muted);">Tipe</p>
                        <p style="color: var(--text-primary);">{{ team.type }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium mb-0.5" style="color: var(--text-muted);">Nomor SK</p>
                        <p style="color: var(--text-primary);">{{ team.sk_number ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium mb-0.5" style="color: var(--text-muted);">Total Anggota</p>
                        <p style="color: var(--text-primary);">{{ team.members.filter(m => m.is_active).length }} aktif</p>
                    </div>
                </div>
                <div v-if="team.description" class="mt-4 pt-4 border-t text-sm" style="border-color: var(--border-color); color: var(--text-secondary);">
                    {{ team.description }}
                </div>
            </div>

            <!-- Member list -->
            <div class="rounded-xl border overflow-hidden" style="background: var(--card-bg); border-color: var(--border-color);">
                <div class="px-4 py-3 border-b" style="border-color: var(--border-color);">
                    <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Anggota Tim</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background: var(--bg-secondary);">
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Pengguna</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Peran</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Bergabung</th>
                            <th class="text-left px-4 py-3 font-medium" style="color: var(--text-muted);">Status</th>
                            <th class="text-right px-4 py-3 font-medium" style="color: var(--text-muted);">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in team.members" :key="member.id" class="border-b" style="border-color: var(--border-color);">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full bg-[#3B82F6] flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ member.user ? initials(member.user.name) : '?' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-sm" style="color: var(--text-primary);">{{ member.user?.name ?? '—' }}</p>
                                        <p class="text-xs font-mono" style="color: var(--text-muted);">{{ member.user?.nip }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(59,130,246,0.15); color: #60A5FA;">{{ member.role }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">{{ formatDate(member.joined_at) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] px-2 py-0.5 rounded-full" :style="member.is_active ? 'background:rgba(16,185,129,0.15);color:#34D399' : 'background:rgba(100,116,139,0.15);color:#94A3B8'">
                                    {{ member.is_active ? 'Aktif' : 'Keluar' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="member.is_active" @click="removeMember(member)" class="p-1.5 rounded-md hover:opacity-70" style="color: #EF4444;" title="Keluarkan dari tim">
                                    <Trash2 :size="14" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="team.members.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm" style="color: var(--text-muted);">Belum ada anggota</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add member slide-over -->
        <Teleport to="body">
            <div v-if="showAddMember" class="fixed inset-0 z-50 flex">
                <div class="flex-1 bg-black/40" @click="showAddMember = false" />
                <div class="w-[400px] h-full overflow-y-auto border-l p-6 shadow-2xl" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Tambah Anggota</h2>
                        <button @click="showAddMember = false" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitAdd" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Pilih Pengguna</label>
                            <select v-model="addForm.user_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Pilih pengguna</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.nip }})</option>
                            </select>
                            <p v-if="addForm.errors.user_id" class="text-xs text-red-400 mt-1">{{ addForm.errors.user_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Peran dalam Tim</label>
                            <select v-model="addForm.role" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option v-for="r in memberRoles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showAddMember = false" class="flex-1 px-4 py-2 rounded-lg text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">Batal</button>
                            <button type="submit" :disabled="addForm.processing" class="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-white" style="background: #3B82F6;">
                                {{ addForm.processing ? 'Menambah...' : 'Tambah' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
