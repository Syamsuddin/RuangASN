<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, Target, Flag, AlertTriangle, Users, History,
    Plus, CheckCircle2, Circle, Trash2, Wallet, CalendarDays,
    User as UserIcon, X, ChevronRight, CheckSquare, Video,
} from 'lucide-vue-next';

interface Member {
    id: string; user_id: string; role: string;
    joined_at?: string; left_at?: string | null;
    user?: { id: string; name: string } | null;
}
interface Milestone {
    id: string; name: string; description?: string;
    status: string | { value: string };
    due_date?: string; completed_at?: string | null; sort_order: number;
}
interface Risk {
    id: string; title: string; description?: string;
    risk_level: string | { value: string };
    probability?: number | null; impact?: number | null;
    mitigation?: string; status: string; owner_id?: string | null;
    owner?: { id: string; name: string } | null;
}
interface StatusHistory {
    id: string; from_status?: string | null; to_status: string;
    notes?: string | null; changed_at?: string;
    changed_by?: { id: string; name: string } | null;
}
interface Project {
    id: string; name: string; description?: string; objectives?: string;
    status: string | { value: string }; status_label: string;
    planned_start_date?: string; planned_end_date?: string;
    actual_start_date?: string; actual_end_date?: string;
    budget: number | null; budget_spent: number; budget_utilization: number;
    progress_percent: number; computed_progress: number;
    tags: string[]; data_classification: number; classification_label: string;
    tasks_count?: number; meetings_count?: number;
    owner?: { id: string; name: string } | null;
    manager?: { id: string; name: string } | null;
    team?: { id: string; name: string } | null;
    members: Member[]; milestones: Milestone[]; risks: Risk[];
    status_histories: StatusHistory[];
}
interface Props {
    project: { data: Project } | Project;
    linked: {
        tasks: Array<{ id: string; title: string; status: string | { value: string } }>;
        meetings: Array<{ id: string; title: string; status: string | { value: string }; scheduled_at?: string }>;
    };
    users: Array<{ id: string; name: string; nip?: string }>;
    transitions: string[];
    can: {
        update: boolean; manageMembers: boolean; manageMilestone: boolean;
        manageRisk: boolean; transition: boolean; close: boolean; delete: boolean;
    };
}

const props = defineProps<Props>();
const project = computed<Project>(() => ('data' in props.project ? props.project.data : props.project) as Project);

const activeTab = ref('overview');
const tabs = [
    { key: 'overview',   label: 'Ringkasan', icon: Target },
    { key: 'milestones', label: 'Milestone', icon: Flag },
    { key: 'risks',      label: 'Risiko',    icon: AlertTriangle },
    { key: 'members',    label: 'Anggota',   icon: Users },
    { key: 'history',    label: 'Riwayat',   icon: History },
];

const val = (s: string | { value: string }) => (typeof s === 'string' ? s : s.value);

// Status config
const statusConfig: Record<string, { bg: string; text: string; label: string }> = {
    draft:      { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: 'Draft' },
    planning:   { bg: 'rgba(59,130,246,0.2)',  text: '#60A5FA', label: 'Perencanaan' },
    active:     { bg: 'rgba(16,185,129,0.2)',  text: '#34D399', label: 'Aktif' },
    on_hold:    { bg: 'rgba(245,158,11,0.2)',  text: '#FCD34D', label: 'Ditangguhkan' },
    monitoring: { bg: 'rgba(139,92,246,0.2)',  text: '#A78BFA', label: 'Monitoring' },
    closing:    { bg: 'rgba(245,158,11,0.2)',  text: '#FBBF24', label: 'Penutupan' },
    completed:  { bg: 'rgba(16,185,129,0.3)',  text: '#10B981', label: 'Selesai' },
    cancelled:  { bg: 'rgba(239,68,68,0.2)',   text: '#F87171', label: 'Dibatalkan' },
    archived:   { bg: 'rgba(100,116,139,0.15)',text: '#64748B', label: 'Diarsipkan' },
};
const statusBadge = (s: string | { value: string }) =>
    statusConfig[val(s)] ?? { bg: 'rgba(100,116,139,0.2)', text: '#94A3B8', label: val(s) };
const transitionLabel = (s: string) => statusConfig[s]?.label ?? s;

// Milestone status config
const milestoneConfig: Record<string, { color: string; label: string }> = {
    pending:     { color: '#94A3B8', label: 'Menunggu' },
    in_progress: { color: '#60A5FA', label: 'Berjalan' },
    completed:   { color: '#10B981', label: 'Selesai' },
    missed:      { color: '#EF4444', label: 'Terlewat' },
    skipped:     { color: '#64748B', label: 'Dilewati' },
};
const milestoneCfg = (s: string | { value: string }) => milestoneConfig[val(s)] ?? milestoneConfig.pending;

// Risk level config
const riskConfig: Record<string, { bg: string; text: string; label: string }> = {
    none:     { bg: 'rgba(100,116,139,0.15)', text: '#94A3B8', label: 'Tidak Ada' },
    low:      { bg: 'rgba(100,116,139,0.2)',  text: '#94A3B8', label: 'Rendah' },
    medium:   { bg: 'rgba(245,158,11,0.2)',   text: '#FCD34D', label: 'Sedang' },
    high:     { bg: 'rgba(249,115,22,0.2)',   text: '#FB923C', label: 'Tinggi' },
    critical: { bg: 'rgba(239,68,68,0.2)',    text: '#F87171', label: 'Kritis' },
};
const riskCfg = (s: string | { value: string }) => riskConfig[val(s)] ?? riskConfig.medium;

const formatDate = (iso?: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
const formatRupiah = (n: number | null) => {
    if (n === null || n === undefined) return '—';
    return `Rp ${n.toLocaleString('id-ID')}`;
};

// ── Transition ──
const transitionForm = useForm({ status: '', notes: '' });
const doTransition = (status: string) => {
    transitionForm.status = status;
    transitionForm.post(`/projects/${project.value.id}/transition`, { preserveScroll: true });
};
const doClose = () => {
    router.post(`/projects/${project.value.id}/close`, {}, { preserveScroll: true });
};

// ── Milestones ──
const showMilestoneForm = ref(false);
const milestoneForm = useForm({ name: '', description: '', due_date: '', status: 'pending' });
const addMilestone = () => {
    milestoneForm.post(`/projects/${project.value.id}/milestones`, {
        preserveScroll: true,
        onSuccess: () => { showMilestoneForm.value = false; milestoneForm.reset(); },
    });
};
const completeMilestone = (m: Milestone) => {
    router.post(`/projects/milestones/${m.id}/complete`, {}, { preserveScroll: true });
};
const deleteMilestone = (m: Milestone) => {
    if (confirm(`Hapus milestone "${m.name}"?`)) {
        router.delete(`/projects/milestones/${m.id}`, { preserveScroll: true });
    }
};

// ── Risks ──
const showRiskForm = ref(false);
const riskForm = useForm({ title: '', description: '', risk_level: 'medium', probability: null as number | null, impact: null as number | null, mitigation: '' });
const addRisk = () => {
    riskForm.post(`/projects/${project.value.id}/risks`, {
        preserveScroll: true,
        onSuccess: () => { showRiskForm.value = false; riskForm.reset(); },
    });
};
const closeRisk = (r: Risk) => {
    router.post(`/projects/risks/${r.id}/close`, {}, { preserveScroll: true });
};

// ── Members ──
const showMemberForm = ref(false);
const memberForm = useForm({ user_id: '', role: 'member' });
const addMember = () => {
    memberForm.post(`/projects/${project.value.id}/members`, {
        preserveScroll: true,
        onSuccess: () => { showMemberForm.value = false; memberForm.reset(); },
    });
};
const removeMember = (m: Member) => {
    if (confirm(`Keluarkan anggota dari proyek?`)) {
        router.delete(`/projects/${project.value.id}/members/${m.id}`, { preserveScroll: true });
    }
};

const activeMembers = computed(() => project.value.members.filter(m => !m.left_at));
const progress = computed(() => project.value.computed_progress ?? project.value.progress_percent);
// Progress ring geometry
const ringCirc = 2 * Math.PI * 26;
const ringOffset = computed(() => ringCirc - (progress.value / 100) * ringCirc);
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <Link href="/projects" style="color: var(--text-muted);">Proyek</Link>
            <ChevronRight :size="14" />
            <span style="color: var(--text-primary);" class="font-medium truncate max-w-[280px]">{{ project.name }}</span>
        </template>

        <div class="space-y-5 max-w-[1100px]">

            <!-- Back -->
            <Link href="/projects" class="inline-flex items-center gap-1.5 text-sm" style="color: var(--text-muted);">
                <ArrowLeft :size="15" /> Kembali ke Proyek
            </Link>

            <!-- Header card -->
            <div class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="flex items-start gap-5 flex-wrap">
                    <!-- Progress ring -->
                    <div class="relative w-[68px] h-[68px] shrink-0">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 60 60">
                            <circle cx="30" cy="30" r="26" fill="none" stroke="var(--bg-tertiary)" stroke-width="6" />
                            <circle cx="30" cy="30" r="26" fill="none" stroke="#3B82F6" stroke-width="6"
                                stroke-linecap="round" :stroke-dasharray="ringCirc" :stroke-dashoffset="ringOffset" />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold" style="color: var(--text-primary);">
                            {{ progress }}%
                        </span>
                    </div>

                    <div class="flex-1 min-w-[240px]">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h1 class="text-xl font-bold" style="color: var(--text-primary);">{{ project.name }}</h1>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                :style="`background: ${statusBadge(project.status).bg}; color: ${statusBadge(project.status).text};`">
                                {{ statusBadge(project.status).label }}</span>
                        </div>
                        <p v-if="project.description" class="text-sm mb-3" style="color: var(--text-muted);">{{ project.description }}</p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div>
                                <p style="color: var(--text-muted);">Owner</p>
                                <p class="font-medium mt-0.5" style="color: var(--text-secondary);">{{ project.owner?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p style="color: var(--text-muted);">Manajer</p>
                                <p class="font-medium mt-0.5" style="color: var(--text-secondary);">{{ project.manager?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p style="color: var(--text-muted);">Target Selesai</p>
                                <p class="font-medium mt-0.5" style="color: var(--text-secondary);">{{ formatDate(project.planned_end_date) }}</p>
                            </div>
                            <div>
                                <p style="color: var(--text-muted);">Anggaran</p>
                                <p class="font-medium mt-0.5" style="color: var(--text-secondary);">{{ formatRupiah(project.budget) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div v-if="(can.transition && transitions.length) || can.close" class="flex items-center gap-2 flex-wrap mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <template v-if="can.transition">
                        <button
                            v-for="t in transitions"
                            :key="t"
                            @click="doTransition(t)"
                            :disabled="transitionForm.processing"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-opacity hover:opacity-90"
                            style="border-color: var(--border-color); color: var(--text-secondary); background: var(--bg-tertiary);"
                        >→ {{ transitionLabel(t) }}</button>
                    </template>
                    <button
                        v-if="can.close"
                        @click="doClose"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-opacity hover:opacity-90 ml-auto"
                        style="background: #10B981;"
                    >Selesaikan Proyek</button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-1 border-b overflow-x-auto" style="border-color: var(--border-color);">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                    :style="activeTab === tab.key
                        ? 'border-color: #3B82F6; color: #3B82F6;'
                        : 'border-color: transparent; color: var(--text-muted);'"
                >
                    <component :is="tab.icon" :size="15" /> {{ tab.label }}
                </button>
            </div>

            <!-- ── Tab: Ringkasan ── -->
            <div v-show="activeTab === 'overview'" class="space-y-4">
                <div class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <h3 class="text-sm font-semibold mb-2" style="color: var(--text-primary);">Sasaran (Objectives)</h3>
                    <p class="text-sm whitespace-pre-line" style="color: var(--text-secondary);">{{ project.objectives || 'Belum ada sasaran yang ditetapkan.' }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Linked tasks -->
                    <div class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="flex items-center gap-2 mb-3">
                            <CheckSquare :size="16" style="color: #3B82F6;" />
                            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Tugas Terkait</h3>
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full" style="background: var(--bg-tertiary); color: var(--text-muted);">{{ linked.tasks.length }}</span>
                        </div>
                        <div v-if="linked.tasks.length" class="space-y-1.5">
                            <Link v-for="t in linked.tasks" :key="t.id" :href="`/tasks/${t.id}`"
                                class="flex items-center gap-2 text-sm py-1.5 px-2 rounded-md hover:opacity-80" style="color: var(--text-secondary);">
                                <Circle :size="6" style="fill: currentColor;" />
                                <span class="truncate">{{ t.title }}</span>
                            </Link>
                        </div>
                        <p v-else class="text-xs" style="color: var(--text-muted);">Belum ada tugas terkait proyek ini.</p>
                    </div>

                    <!-- Linked meetings -->
                    <div class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="flex items-center gap-2 mb-3">
                            <Video :size="16" style="color: #8B5CF6;" />
                            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Meeting Terkait</h3>
                            <span class="ml-auto text-xs px-2 py-0.5 rounded-full" style="background: var(--bg-tertiary); color: var(--text-muted);">{{ linked.meetings.length }}</span>
                        </div>
                        <div v-if="linked.meetings.length" class="space-y-1.5">
                            <Link v-for="m in linked.meetings" :key="m.id" :href="`/meetings/${m.id}`"
                                class="flex items-center gap-2 text-sm py-1.5 px-2 rounded-md hover:opacity-80" style="color: var(--text-secondary);">
                                <Circle :size="6" style="fill: currentColor;" />
                                <span class="truncate">{{ m.title }}</span>
                                <span class="ml-auto text-[11px]" style="color: var(--text-muted);">{{ formatDate(m.scheduled_at) }}</span>
                            </Link>
                        </div>
                        <p v-else class="text-xs" style="color: var(--text-muted);">Belum ada meeting terkait proyek ini.</p>
                    </div>
                </div>
            </div>

            <!-- ── Tab: Milestone ── -->
            <div v-show="activeTab === 'milestones'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm" style="color: var(--text-muted);">{{ project.milestones.length }} milestone</p>
                    <button v-if="can.manageMilestone" @click="showMilestoneForm = !showMilestoneForm"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: #3B82F6;">
                        <Plus :size="14" /> Tambah Milestone
                    </button>
                </div>

                <!-- Inline add form -->
                <div v-if="showMilestoneForm" class="rounded-xl p-4 space-y-3" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <input v-model="milestoneForm.name" placeholder="Nama milestone" class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);" />
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="milestoneForm.due_date" type="date" class="px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);" />
                        <select v-model="milestoneForm.status" class="px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);">
                            <option value="pending">Menunggu</option>
                            <option value="in_progress">Berjalan</option>
                            <option value="completed">Selesai</option>
                        </select>
                    </div>
                    <button @click="addMilestone" :disabled="milestoneForm.processing" class="px-4 py-2 rounded-lg text-xs font-semibold text-white" style="background: #3B82F6;">Simpan</button>
                </div>

                <!-- Timeline list -->
                <div v-if="project.milestones.length" class="space-y-2">
                    <div v-for="m in project.milestones" :key="m.id"
                        class="flex items-center gap-3 rounded-xl p-3.5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                        <button v-if="can.manageMilestone && val(m.status) !== 'completed'" @click="completeMilestone(m)" title="Tandai selesai">
                            <Circle :size="20" :style="`color: ${milestoneCfg(m.status).color};`" />
                        </button>
                        <CheckCircle2 v-else :size="20" :style="`color: ${milestoneCfg(m.status).color};`" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">{{ m.name }}</p>
                            <p class="text-[11px]" style="color: var(--text-muted);">Tenggat: {{ formatDate(m.due_date) }}</p>
                        </div>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :style="`background: ${milestoneCfg(m.status).color}22; color: ${milestoneCfg(m.status).color};`">{{ milestoneCfg(m.status).label }}</span>
                        <button v-if="can.manageMilestone" @click="deleteMilestone(m)" class="p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <Trash2 :size="15" />
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm py-8 text-center" style="color: var(--text-muted);">Belum ada milestone.</p>
            </div>

            <!-- ── Tab: Risiko ── -->
            <div v-show="activeTab === 'risks'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm" style="color: var(--text-muted);">{{ project.risks.length }} risiko</p>
                    <button v-if="can.manageRisk" @click="showRiskForm = !showRiskForm"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: #3B82F6;">
                        <Plus :size="14" /> Tambah Risiko
                    </button>
                </div>

                <div v-if="showRiskForm" class="rounded-xl p-4 space-y-3" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <input v-model="riskForm.title" placeholder="Judul risiko" class="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);" />
                    <div class="grid grid-cols-3 gap-3">
                        <select v-model="riskForm.risk_level" class="px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);">
                            <option value="low">Rendah</option>
                            <option value="medium">Sedang</option>
                            <option value="high">Tinggi</option>
                            <option value="critical">Kritis</option>
                        </select>
                        <input v-model.number="riskForm.probability" type="number" min="1" max="5" placeholder="Prob. 1-5" class="px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);" />
                        <input v-model.number="riskForm.impact" type="number" min="1" max="5" placeholder="Dampak 1-5" class="px-3 py-2 rounded-lg text-sm border outline-none"
                            style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);" />
                    </div>
                    <textarea v-model="riskForm.mitigation" rows="2" placeholder="Mitigasi..." class="w-full px-3 py-2 rounded-lg text-sm border outline-none resize-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);" />
                    <button @click="addRisk" :disabled="riskForm.processing" class="px-4 py-2 rounded-lg text-xs font-semibold text-white" style="background: #3B82F6;">Simpan</button>
                </div>

                <div v-if="project.risks.length" class="space-y-2">
                    <div v-for="r in project.risks" :key="r.id"
                        class="rounded-xl p-4" :style="`background: var(--card-bg); border: 1px solid var(--border-color); opacity: ${r.status === 'closed' ? 0.55 : 1};`">
                        <div class="flex items-start gap-3">
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full shrink-0"
                                :style="`background: ${riskCfg(r.risk_level).bg}; color: ${riskCfg(r.risk_level).text};`">{{ riskCfg(r.risk_level).label }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">{{ r.title }}</p>
                                <p v-if="r.mitigation" class="text-xs mt-1" style="color: var(--text-muted);">Mitigasi: {{ r.mitigation }}</p>
                            </div>
                            <!-- Probability × Impact matrix cell -->
                            <div v-if="r.probability && r.impact" class="text-center shrink-0">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold"
                                    :style="`background: ${riskCfg(r.risk_level).bg}; color: ${riskCfg(r.risk_level).text};`">{{ r.probability * r.impact }}</div>
                                <p class="text-[9px] mt-0.5" style="color: var(--text-muted);">{{ r.probability }}×{{ r.impact }}</p>
                            </div>
                            <button v-if="can.manageRisk && r.status !== 'closed'" @click="closeRisk(r)"
                                class="text-[11px] px-2 py-1 rounded-md border shrink-0" style="border-color: var(--border-color); color: var(--text-secondary);">Tutup</button>
                            <span v-else-if="r.status === 'closed'" class="text-[11px] shrink-0" style="color: var(--text-muted);">Ditutup</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm py-8 text-center" style="color: var(--text-muted);">Belum ada risiko teridentifikasi.</p>
            </div>

            <!-- ── Tab: Anggota ── -->
            <div v-show="activeTab === 'members'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm" style="color: var(--text-muted);">{{ activeMembers.length }} anggota aktif</p>
                    <button v-if="can.manageMembers" @click="showMemberForm = !showMemberForm"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: #3B82F6;">
                        <Plus :size="14" /> Tambah Anggota
                    </button>
                </div>

                <div v-if="showMemberForm" class="rounded-xl p-4 flex items-center gap-3" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <select v-model="memberForm.user_id" class="flex-1 px-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">Pilih pengguna…</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <select v-model="memberForm.role" class="px-3 py-2 rounded-lg text-sm border outline-none"
                        style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="member">Anggota</option>
                        <option value="contributor">Kontributor</option>
                        <option value="viewer">Pengamat</option>
                    </select>
                    <button @click="addMember" :disabled="memberForm.processing || !memberForm.user_id" class="px-4 py-2 rounded-lg text-xs font-semibold text-white" style="background: #3B82F6;">Tambah</button>
                </div>

                <div class="space-y-2">
                    <div v-for="m in activeMembers" :key="m.id"
                        class="flex items-center gap-3 rounded-xl p-3" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="w-8 h-8 rounded-full bg-[#3B82F6] flex items-center justify-center text-white text-xs font-bold shrink-0">
                            {{ (m.user?.name ?? '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">{{ m.user?.name ?? '—' }}</p>
                            <p class="text-[11px] capitalize" style="color: var(--text-muted);">{{ m.role }}</p>
                        </div>
                        <button v-if="can.manageMembers && m.role !== 'owner'" @click="removeMember(m)" class="p-1 hover:opacity-70" style="color: var(--text-muted);">
                            <X :size="16" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Tab: Riwayat ── -->
            <div v-show="activeTab === 'history'" class="space-y-3">
                <div v-if="project.status_histories.length" class="rounded-xl p-5" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="space-y-4">
                        <div v-for="(h, i) in project.status_histories" :key="h.id" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="w-2.5 h-2.5 rounded-full mt-1" style="background: #3B82F6;" />
                                <div v-if="i < project.status_histories.length - 1" class="w-px flex-1 mt-1" style="background: var(--border-color);" />
                            </div>
                            <div class="flex-1 pb-2">
                                <p class="text-sm" style="color: var(--text-primary);">
                                    <span v-if="h.from_status">{{ transitionLabel(h.from_status) }} → </span>
                                    <span class="font-semibold">{{ transitionLabel(h.to_status) }}</span>
                                </p>
                                <p v-if="h.notes" class="text-xs mt-0.5" style="color: var(--text-secondary);">{{ h.notes }}</p>
                                <p class="text-[11px] mt-1" style="color: var(--text-muted);">
                                    {{ h.changed_by?.name ?? '—' }} · {{ formatDate(h.changed_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm py-8 text-center" style="color: var(--text-muted);">Belum ada riwayat status.</p>
            </div>

        </div>
    </AppLayout>
</template>
