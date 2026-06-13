<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.auth?.user;
const sidebarOpen = ref(true);

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: '🏠' },
    { name: 'Tugas', href: '/tasks', icon: '✅' },
    { name: 'Rapat', href: '/meetings', icon: '📅' },
    { name: 'Kalender', href: '/calendar', icon: '🗓️' },
    { name: 'Dokumen', href: '/documents', icon: '📄' },
    { name: 'Laporan', href: '/reports', icon: '📊' },
];
</script>

<template>
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside
            :class="sidebarOpen ? 'w-64' : 'w-16'"
            class="flex flex-col bg-blue-900 text-white transition-all duration-300 flex-shrink-0"
        >
            <div class="flex items-center gap-3 px-4 py-5 border-b border-blue-800">
                <div class="w-8 h-8 bg-blue-400 rounded-lg flex items-center justify-center font-bold text-sm">R</div>
                <span v-if="sidebarOpen" class="font-semibold text-lg">RuangASN</span>
            </div>
            <nav class="flex-1 py-4">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 px-4 py-2.5 text-blue-100 hover:bg-blue-800 hover:text-white transition-colors"
                >
                    <span class="text-lg">{{ item.icon }}</span>
                    <span v-if="sidebarOpen" class="text-sm font-medium">{{ item.name }}</span>
                </Link>
            </nav>
            <div class="p-4 border-t border-blue-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">
                        {{ user?.name?.charAt(0) ?? 'U' }}
                    </div>
                    <div v-if="sidebarOpen" class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ user?.name }}</p>
                        <p class="text-xs text-blue-300 truncate">{{ user?.nip }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Topbar -->
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
                    ☰
                </button>
                <div class="flex items-center gap-4">
                    <Link href="/notifications" class="text-gray-500 hover:text-gray-700 relative">
                        🔔
                    </Link>
                    <Link href="/profile" class="text-sm text-gray-700 font-medium">{{ user?.name }}</Link>
                    <Link href="/logout" method="post" as="button" class="text-sm text-red-600 hover:text-red-700">
                        Keluar
                    </Link>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
