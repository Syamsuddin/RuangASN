<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    tasks: {
        data: Array<{
            id: string; title: string; status: string; priority: string;
            due_date?: string; assignee?: { name: string };
        }>;
        links: Array<{ url?: string; label: string; active: boolean }>;
        meta: { total: number; current_page: number };
    };
    filters: { status?: string; priority?: string };
}>();

const statusFilter = ref(props.filters.status ?? '');
const priorityFilter = ref(props.filters.priority ?? '');

const applyFilter = () => {
    router.get('/tasks', { status: statusFilter.value, priority: priorityFilter.value }, { preserveState: true });
};

const priorityClass = (p: string) => ({
    critical: 'bg-red-100 text-red-700 border-red-200',
    high: 'bg-orange-100 text-orange-700 border-orange-200',
    medium: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    low: 'bg-gray-100 text-gray-600 border-gray-200',
}[p] ?? '');

const statusClass = (s: string) => ({
    draft: 'text-gray-500',
    open: 'text-blue-600',
    in_progress: 'text-yellow-600',
    completed: 'text-green-600',
    cancelled: 'text-red-500',
}[s] ?? 'text-gray-600');
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900">Manajemen Tugas</h1>
                <Link href="/tasks/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    + Buat Tugas
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex gap-3 bg-white p-4 rounded-xl border border-gray-200">
                <select v-model="statusFilter" @change="applyFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="open">Terbuka</option>
                    <option value="in_progress">Dikerjakan</option>
                    <option value="waiting_review">Menunggu Review</option>
                    <option value="completed">Selesai</option>
                </select>
                <select v-model="priorityFilter" @change="applyFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Semua Prioritas</option>
                    <option value="critical">Kritis</option>
                    <option value="high">Tinggi</option>
                    <option value="medium">Sedang</option>
                    <option value="low">Rendah</option>
                </select>
                <span class="ml-auto text-sm text-gray-500 self-center">{{ tasks.meta?.total ?? 0 }} tugas</span>
            </div>

            <!-- Task List -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-50">
                <Link
                    v-for="task in tasks.data"
                    :key="task.id"
                    :href="`/tasks/${task.id}`"
                    class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition-colors"
                >
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ task.title }}</p>
                        <div class="flex items-center gap-3 mt-1">
                            <span :class="statusClass(task.status)" class="text-xs capitalize font-medium">
                                {{ task.status.replace('_', ' ') }}
                            </span>
                            <span v-if="task.assignee" class="text-xs text-gray-400">
                                → {{ task.assignee.name }}
                            </span>
                            <span v-if="task.due_date" class="text-xs text-gray-400">
                                📅 {{ task.due_date }}
                            </span>
                        </div>
                    </div>
                    <span v-if="task.priority" :class="priorityClass(task.priority)" class="px-2.5 py-0.5 rounded-full text-xs font-medium border capitalize">
                        {{ task.priority }}
                    </span>
                </Link>
                <div v-if="!tasks.data.length" class="px-6 py-12 text-center text-gray-400 text-sm">
                    Belum ada tugas.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
