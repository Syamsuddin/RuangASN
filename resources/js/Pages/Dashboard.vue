<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps<{
    auth: { user: { name: string; nip?: string; organization?: { name: string } } };
    taskStats: { total: number; in_progress: number; due_today: number; overdue: number };
    recentTasks: Array<{ id: string; title: string; status: string; priority: string; due_date?: string }>;
}>();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 10) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

const statusColor = (status: string) => ({
    draft: 'bg-gray-100 text-gray-700',
    open: 'bg-blue-100 text-blue-700',
    assigned: 'bg-purple-100 text-purple-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    waiting_review: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
    closed: 'bg-gray-100 text-gray-500',
}[status] ?? 'bg-gray-100 text-gray-600');
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Greeting -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ greeting }}, {{ auth.user.name }}
                </h1>
                <p class="text-gray-500 text-sm mt-1">{{ auth.user.organization?.name }}</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <p class="text-sm text-gray-500 mb-1">Total Tugas</p>
                    <p class="text-3xl font-bold text-gray-900">{{ taskStats.total }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <p class="text-sm text-gray-500 mb-1">Sedang Dikerjakan</p>
                    <p class="text-3xl font-bold text-blue-600">{{ taskStats.in_progress }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <p class="text-sm text-gray-500 mb-1">Due Hari Ini</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ taskStats.due_today }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <p class="text-sm text-gray-500 mb-1">Terlambat</p>
                    <p class="text-3xl font-bold text-red-600">{{ taskStats.overdue }}</p>
                </div>
            </div>

            <!-- Recent Tasks -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-900">Tugas Terbaru</h2>
                    <a href="/tasks" class="text-sm text-blue-600 hover:underline">Lihat Semua</a>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="task in recentTasks" :key="task.id" class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ task.title }}</p>
                            <p v-if="task.due_date" class="text-xs text-gray-400 mt-0.5">Tenggat: {{ task.due_date }}</p>
                        </div>
                        <span :class="statusColor(task.status)" class="px-2.5 py-0.5 rounded-full text-xs font-medium capitalize">
                            {{ task.status.replace('_', ' ') }}
                        </span>
                    </div>
                    <div v-if="!recentTasks?.length" class="px-6 py-8 text-center text-gray-400 text-sm">
                        Belum ada tugas. Buat tugas pertama Anda!
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
