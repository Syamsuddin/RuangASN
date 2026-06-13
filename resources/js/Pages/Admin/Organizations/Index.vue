<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, X, ChevronRight, ChevronDown, Pencil, Building2 } from 'lucide-vue-next';

interface OrgNode {
    id: string; name: string; short_name?: string; type: string | { value: string };
    code?: string; depth: number; parent_id?: string; is_active: boolean;
    users_count?: number; children: OrgNode[];
}

interface Props {
    tree: OrgNode[];
    flat: { id: string; name: string; type: string; depth: string; parent_id?: string }[];
    types: string[];
}

const props = defineProps<Props>();
const showCreate = ref(false);
const editTarget = ref<OrgNode | null>(null);
const expanded = ref<Record<string, boolean>>({});

const toggle = (id: string) => { expanded.value[id] = !expanded.value[id]; };

const createForm = useForm({
    name: '', short_name: '', type: 'department', code: '', parent_id: '', is_active: true,
});

const editForm = useForm({
    name: '', short_name: '', code: '', is_active: true,
});

const submitCreate = () => {
    createForm.post('/admin/organizations', {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
};

const openEdit = (org: OrgNode) => {
    editTarget.value = org;
    editForm.name = org.name;
    editForm.short_name = org.short_name ?? '';
    editForm.code = org.code ?? '';
    editForm.is_active = org.is_active;
};

const submitEdit = () => {
    if (!editTarget.value) return;
    editForm.patch(`/admin/organizations/${editTarget.value.id}`, {
        onSuccess: () => { editTarget.value = null; },
    });
};

const typeVal = (t: string | { value: string }) => typeof t === 'string' ? t : t.value;

const typeBadge = (t: string | { value: string }) => {
    const v = typeVal(t);
    const map: Record<string, { bg: string; text: string }> = {
        government:   { bg: 'rgba(239,68,68,0.15)',   text: '#F87171' },
        department:   { bg: 'rgba(59,130,246,0.15)',  text: '#60A5FA' },
        unit:         { bg: 'rgba(16,185,129,0.15)',  text: '#34D399' },
        sub_unit:     { bg: 'rgba(139,92,246,0.15)',  text: '#A78BFA' },
        team:         { bg: 'rgba(245,158,11,0.15)',  text: '#FCD34D' },
        committee:    { bg: 'rgba(236,72,153,0.15)',  text: '#F472B6' },
        working_group:{ bg: 'rgba(20,184,166,0.15)',  text: '#2DD4BF' },
    };
    return map[v] ?? { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8' };
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">Administrasi</span>
            <span style="color: var(--text-muted);" class="mx-1">/</span>
            <span style="color: var(--text-primary);" class="font-medium">Struktur Organisasi</span>
        </template>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Struktur Organisasi</h1>
                    <p class="text-sm mt-0.5" style="color: var(--text-muted);">Pohon hierarki unit kerja</p>
                </div>
                <button
                    @click="showCreate = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
                    style="background: #3B82F6;"
                >
                    <Plus :size="16" />
                    Tambah Unit
                </button>
            </div>

            <!-- Tree -->
            <div class="rounded-xl border p-4" style="background: var(--card-bg); border-color: var(--border-color);">
                <OrgTreeNode
                    v-for="node in tree"
                    :key="node.id"
                    :node="node"
                    :expanded="expanded"
                    :type-badge="typeBadge"
                    :type-val="typeVal"
                    @toggle="toggle"
                    @edit="openEdit"
                />
                <div v-if="tree.length === 0" class="text-center py-12 text-sm" style="color: var(--text-muted);">
                    Belum ada unit organisasi
                </div>
            </div>
        </div>

        <!-- Create slide-over -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex">
                <div class="flex-1 bg-black/40" @click="showCreate = false" />
                <div class="w-[480px] h-full overflow-y-auto border-l p-6 shadow-2xl" style="background: var(--card-bg); border-color: var(--border-color);">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Tambah Unit Organisasi</h2>
                        <button @click="showCreate = false" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama Unit</label>
                            <input v-model="createForm.name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Nama lengkap unit" />
                            <p v-if="createForm.errors.name" class="text-xs text-red-400 mt-1">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama Singkat</label>
                            <input v-model="createForm.short_name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Mis. BKPSDM" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Tipe</label>
                            <select v-model="createForm.type" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Kode Unit</label>
                            <input v-model="createForm.code" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" placeholder="Mis. BKPSDM-001" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Unit Induk (opsional)</label>
                            <select v-model="createForm.parent_id" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Tidak ada (unit root)</option>
                                <option v-for="org in flat" :key="org.id" :value="org.id">
                                    {{ '─'.repeat(Number(org.depth)) }} {{ org.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3">
                            <input id="is_active" v-model="createForm.is_active" type="checkbox" class="w-4 h-4 rounded" />
                            <label for="is_active" class="text-sm" style="color: var(--text-secondary);">Unit aktif</label>
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
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Edit Unit: {{ editTarget.name }}</h2>
                        <button @click="editTarget = null" style="color: var(--text-muted);"><X :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama Unit</label>
                            <input v-model="editForm.name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Nama Singkat</label>
                            <input v-model="editForm.short_name" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Kode</label>
                            <input v-model="editForm.code" class="w-full px-3 py-2 rounded-lg border text-sm outline-none" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" />
                        </div>
                        <div class="flex items-center gap-3">
                            <input id="edit_is_active" v-model="editForm.is_active" type="checkbox" class="w-4 h-4 rounded" />
                            <label for="edit_is_active" class="text-sm" style="color: var(--text-secondary);">Unit aktif</label>
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

<script lang="ts">
// Recursive tree node component
import { defineComponent, h } from 'vue';
import { ChevronRight, ChevronDown, Pencil, Building2 } from 'lucide-vue-next';

const OrgTreeNode = defineComponent({
    name: 'OrgTreeNode',
    props: {
        node: { type: Object as () => any, required: true },
        expanded: { type: Object as () => Record<string, boolean>, required: true },
        typeBadge: { type: Function, required: true },
        typeVal: { type: Function, required: true },
        depth: { type: Number, default: 0 },
    },
    emits: ['toggle', 'edit'],
    setup(props, { emit }) {
        return () => {
            const node = props.node;
            const isExpanded = props.expanded[node.id];
            const hasChildren = node.children?.length > 0;
            return h('div', { class: 'select-none' }, [
                h('div', {
                    class: 'flex items-center gap-2 px-3 py-2 rounded-lg mb-0.5 group hover:opacity-90 cursor-pointer transition-colors',
                    style: { marginLeft: `${props.depth * 20}px`, background: 'transparent' },
                    onClick: () => hasChildren && emit('toggle', node.id),
                }, [
                    h('span', { class: 'w-4 shrink-0', style: { color: 'var(--text-muted)' } },
                        hasChildren
                            ? h(isExpanded ? ChevronDown : ChevronRight, { size: 14 })
                            : h('span', { class: 'w-4' })
                    ),
                    h(Building2, { size: 16, style: { color: 'var(--text-muted)', flexShrink: 0 } }),
                    h('span', {
                        class: 'text-sm font-medium flex-1',
                        style: { color: 'var(--text-primary)' }
                    }, node.name),
                    node.short_name && h('span', {
                        class: 'text-xs px-1.5 py-0.5 rounded font-mono',
                        style: { background: 'var(--bg-tertiary)', color: 'var(--text-muted)' }
                    }, node.short_name),
                    h('span', {
                        class: 'text-[11px] px-2 py-0.5 rounded-full font-medium',
                        style: { background: props.typeBadge(node.type).bg, color: props.typeBadge(node.type).text }
                    }, props.typeVal(node.type)),
                    node.users_count != null && h('span', {
                        class: 'text-xs',
                        style: { color: 'var(--text-muted)' }
                    }, `${node.users_count} pengguna`),
                    !node.is_active && h('span', {
                        class: 'text-[11px] px-2 py-0.5 rounded-full',
                        style: { background: 'rgba(100,116,139,0.15)', color: '#94A3B8' }
                    }, 'Nonaktif'),
                    h('button', {
                        class: 'opacity-0 group-hover:opacity-100 p-1 rounded transition-opacity',
                        style: { color: 'var(--text-muted)' },
                        onClick: (e: Event) => { e.stopPropagation(); emit('edit', node); },
                    }, h(Pencil, { size: 13 })),
                ]),
                isExpanded && hasChildren && h('div', {},
                    node.children.map((child: any) =>
                        h(OrgTreeNode, {
                            key: child.id,
                            node: child,
                            expanded: props.expanded,
                            typeBadge: props.typeBadge,
                            typeVal: props.typeVal,
                            depth: props.depth + 1,
                            onToggle: (id: string) => emit('toggle', id),
                            onEdit: (n: any) => emit('edit', n),
                        })
                    )
                ),
            ]);
        };
    },
});

export default { components: { OrgTreeNode } };
</script>
