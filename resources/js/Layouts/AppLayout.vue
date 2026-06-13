<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { useNotifications } from '@/composables/useNotifications';
import CommandPalette from '@/components/CommandPalette.vue';
import {
    LayoutDashboard, BriefcaseBusiness, CheckSquare, Calendar,
    Users, Video, FolderOpen, FileText, BarChart3, MessageSquare,
    Sparkles, Settings, HelpCircle, Bell, Search, Sun, Moon,
    ChevronLeft, ChevronRight, LogOut, User, Building2, Circle,
    Shield, UserCog, Network, UsersRound, GitBranch, ClipboardList,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth?.user as any);
const permissions = computed(() => (page.props.auth as any)?.permissions ?? []);
const hasPermission = (perm: string) => permissions.value.includes(perm);
const isAdmin = computed(() => permissions.value.some((p: string) => p.startsWith('admin.')));
const { theme, toggle, isDark } = useTheme();
const collapsed = ref(false);

const { notifications, unreadCount, markRead, markAllRead, startEchoListener } = useNotifications();

const bellOpen      = ref(false);
const profileOpen   = ref(false);
const paletteOpen   = ref(false);

const openPalette = () => { paletteOpen.value = true; };
const closePalette = () => { paletteOpen.value = false; };

const handleGlobalKey = (e: KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        paletteOpen.value = !paletteOpen.value;
    }
};

const toggleBell = () => {
    bellOpen.value = !bellOpen.value;
    if (bellOpen.value) profileOpen.value = false;
};
const toggleProfile = () => {
    profileOpen.value = !profileOpen.value;
    if (profileOpen.value) bellOpen.value = false;
};
const closeDropdowns = (e: MouseEvent) => {
    const target = e.target as HTMLElement;
    if (!target.closest('[data-bell-dropdown]'))    bellOpen.value    = false;
    if (!target.closest('[data-profile-dropdown]')) profileOpen.value = false;
};
const closeBell = (e: MouseEvent) => {
    const target = e.target as HTMLElement;
    if (!target.closest('[data-bell-dropdown]')) {
        bellOpen.value = false;
    }
};

const logout = () => {
    router.post('/logout');
};

const relativeTime = (iso: string): string => {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'baru saja';
    if (mins < 60) return `${mins} menit lalu`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} jam lalu`;
    return `${Math.floor(hours / 24)} hari lalu`;
};

const handleNotifClick = (notif: any) => {
    if (!notif.read_at) markRead(notif.id);
    bellOpen.value = false;
    if (notif.data?.url) {
        window.location.href = notif.data.url as string;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdowns);
    document.addEventListener('keydown', handleGlobalKey);
    if (user.value?.id) {
        startEchoListener(user.value.id);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdowns);
    document.removeEventListener('keydown', handleGlobalKey);
});

const navGroups = [
    {
        label: 'OVERVIEW',
        items: [
            { name: 'Dashboard',    href: '/dashboard',    icon: LayoutDashboard },
            { name: 'Kalender',     href: '/calendar',     icon: Calendar },
            { name: 'Struktur Org', href: '/organization', icon: Building2 },
        ],
    },
    {
        label: 'WORKSPACE SAYA',
        items: [
            { name: 'Personal Workspace', href: '/workspace',   icon: BriefcaseBusiness },
            { name: 'Tasks Saya',         href: '/tasks',        icon: CheckSquare, badgeKey: 'overdueCount' },
            { name: 'Laporan',            href: '/reports',      icon: FileText },
            { name: 'Kinerja / SKP',      href: '/performance',  icon: BarChart3 },
        ],
    },
    {
        label: 'TIM & KOLABORASI',
        items: [
            { name: 'Tim Saya', href: '/teams',    icon: Users },
            { name: 'Meeting',  href: '/meetings', icon: Video },
            { name: 'Chat',     href: '/chat',     icon: MessageSquare },
        ],
    },
    {
        label: 'SUMBER DAYA',
        items: [
            { name: 'Dokumen',       href: '/documents', icon: FolderOpen },
            { name: 'Knowledge',     href: '/knowledge', icon: FileText },
        ],
    },
];

const adminNavItems = computed(() => [
    { name: 'Pengguna',            href: '/admin/users',          icon: UserCog,       perm: 'admin.users.view' },
    { name: 'Struktur Organisasi', href: '/admin/organizations',  icon: Network,       perm: 'admin.organizations.view' },
    { name: 'Tim',                 href: '/admin/teams',          icon: UsersRound,    perm: 'admin.units.manage' },
    { name: 'Delegasi',            href: '/admin/delegations',    icon: GitBranch,     perm: 'organization.delegation.view' },
    { name: 'Audit Log',           href: '/admin/audit',          icon: ClipboardList, perm: 'audit.view.own' },
].filter(item => hasPermission(item.perm)));

const overdueCount = computed(() => (page.props as any).taskStats?.overdue ?? 0);

const isActive = (href: string) => page.url.startsWith(href);

const initials = computed(() => {
    const name = user.value?.name ?? '';
    return name.split(' ').slice(0, 2).map((w: string) => w[0]).join('').toUpperCase();
});
</script>

<template>
    <div class="flex h-screen overflow-hidden" style="background: var(--bg-primary);">

        <!-- ── Sidebar ── -->
        <aside
            :class="collapsed ? 'w-[68px]' : 'w-[260px]'"
            class="flex flex-col shrink-0 transition-all duration-200 overflow-hidden border-r"
            style="background: var(--sidebar-bg); border-color: var(--border-color);"
        >
            <!-- Logo -->
            <div
                class="flex items-center gap-3 px-4 py-[18px] border-b shrink-0"
                style="border-color: var(--border-color);"
            >
                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center shrink-0" style="background:#3B82F6;">
                    <span class="text-white text-sm font-bold">R</span>
                </div>
                <span v-if="!collapsed" class="font-semibold text-base truncate" style="color: var(--text-primary);">RuangASN</span>
                <button
                    v-if="!collapsed"
                    @click="collapsed = true"
                    class="ml-auto rounded-md p-1 transition-colors hover:opacity-70"
                    style="color: var(--text-muted);"
                >
                    <ChevronLeft :size="16" />
                </button>
            </div>

            <!-- Expand button when collapsed -->
            <div v-if="collapsed" class="flex justify-center py-3 border-b shrink-0" style="border-color: var(--border-color);">
                <button @click="collapsed = false" class="rounded-md p-1.5 transition-colors hover:opacity-70" style="color: var(--text-muted);">
                    <ChevronRight :size="16" />
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3 px-2">
                <template v-for="group in navGroups" :key="group.label">
                    <p
                        v-if="!collapsed"
                        class="text-[10px] font-semibold tracking-widest px-3 pt-4 pb-1.5"
                        style="color: var(--text-muted);"
                    >{{ group.label }}</p>

                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-md px-3 py-2 mb-0.5 text-sm font-medium transition-colors"
                        :class="isActive(item.href)
                            ? 'border-l-2 border-[#3B82F6] bg-[#1E3A5F]'
                            : ''"
                        :style="isActive(item.href)
                            ? 'color:#3B82F6; padding-left:10px;'
                            : 'color: var(--text-secondary);'"
                        active-class=""
                    >
                        <component :is="item.icon" :size="18" class="shrink-0" />
                        <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                        <span
                            v-if="!collapsed && item.badgeKey && overdueCount > 0"
                            class="ml-auto text-[10px] font-semibold rounded-full px-1.5 py-0.5 bg-[#EF4444] text-white"
                        >{{ overdueCount }}</span>
                    </Link>
                </template>

                <!-- Admin group (only shown to users with admin permissions) -->
                <template v-if="isAdmin && adminNavItems.length > 0">
                    <p
                        v-if="!collapsed"
                        class="text-[10px] font-semibold tracking-widest px-3 pt-4 pb-1.5 flex items-center gap-1.5"
                        style="color: var(--text-muted);"
                    >
                        <Shield :size="10" />
                        ADMINISTRASI
                    </p>
                    <Link
                        v-for="item in adminNavItems"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-md px-3 py-2 mb-0.5 text-sm font-medium transition-colors"
                        :class="isActive(item.href) ? 'border-l-2 border-[#8B5CF6] bg-[#1E1A3F]' : ''"
                        :style="isActive(item.href) ? 'color:#8B5CF6; padding-left:10px;' : 'color: var(--text-secondary);'"
                        active-class=""
                    >
                        <component :is="item.icon" :size="18" class="shrink-0" />
                        <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                    </Link>
                </template>
            </nav>

            <!-- AI Assistant button -->
            <div class="px-2 py-2 border-t shrink-0" style="border-color: var(--border-color);">
                <Link
                    href="/ai"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    style="background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(139,92,246,0.15)); color: #8B5CF6; border: 1px solid rgba(139,92,246,0.25);"
                >
                    <Sparkles :size="18" class="shrink-0" />
                    <span v-if="!collapsed">AI Assistant</span>
                </Link>
            </div>

            <!-- User profile -->
            <div class="px-3 py-3 border-t shrink-0" style="border-color: var(--border-color);">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-[#3B82F6] flex items-center justify-center text-white text-xs font-bold shrink-0">
                        {{ initials }}
                    </div>
                    <div v-if="!collapsed" class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ user?.name }}</p>
                        <p class="text-xs truncate" style="color: var(--text-muted);">{{ user?.jabatan ?? user?.nip }}</p>
                    </div>
                    <Link
                        v-if="!collapsed"
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-md p-1 transition-colors hover:opacity-70"
                        style="color: var(--text-muted);"
                    >
                        <LogOut :size="15" />
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ── Main ── -->
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

            <!-- Topbar -->
            <header
                class="flex items-center gap-4 h-16 px-6 border-b shrink-0"
                style="background: var(--topbar-bg); border-color: var(--border-color);"
            >
                <!-- Breadcrumb -->
                <div class="flex items-center gap-1.5 text-sm min-w-0" style="color: var(--text-muted);">
                    <slot name="breadcrumb">
                        <span style="color: var(--text-primary);" class="font-medium">Dashboard</span>
                    </slot>
                </div>

                <div class="flex-1" />

                <!-- Search -->
                <button
                    @click="openPalette"
                    class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-full text-sm transition-colors"
                    style="background: var(--bg-tertiary); color: var(--text-muted); border: 1px solid var(--border-color);"
                >
                    <Search :size="14" />
                    <span>Cari...</span>
                    <kbd class="text-[10px] px-1.5 py-0.5 rounded font-mono" style="background: var(--border-color);">⌘K</kbd>
                </button>

                <!-- Theme toggle -->
                <button
                    @click="toggle"
                    class="w-8 h-8 rounded-md flex items-center justify-center transition-colors"
                    style="color: var(--text-muted);"
                    :title="isDark() ? 'Switch to light mode' : 'Switch to dark mode'"
                >
                    <Sun v-if="isDark()" :size="18" />
                    <Moon v-else :size="18" />
                </button>

                <!-- Notifications -->
                <div class="relative" data-bell-dropdown>
                    <button
                        @click.stop="toggleBell"
                        class="relative w-8 h-8 rounded-md flex items-center justify-center transition-colors"
                        style="color: var(--text-muted);"
                        aria-label="Notifikasi"
                    >
                        <Bell :size="18" />
                        <span
                            v-if="unreadCount > 0"
                            class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#EF4444]"
                        />
                    </button>

                    <!-- Notification dropdown -->
                    <div
                        v-if="bellOpen"
                        class="absolute right-0 top-10 w-[360px] rounded-lg border shadow-xl z-50 overflow-hidden"
                        style="background: var(--card-bg); border-color: var(--border-color); box-shadow: var(--shadow);"
                        data-bell-dropdown
                    >
                        <!-- Header -->
                        <div
                            class="flex items-center justify-between px-4 py-3 border-b"
                            style="border-color: var(--border-color);"
                        >
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">Notifikasi</span>
                            <button
                                v-if="unreadCount > 0"
                                @click="markAllRead"
                                class="text-xs transition-colors"
                                style="color: var(--color-primary);"
                            >Tandai semua dibaca</button>
                        </div>

                        <!-- Notification list -->
                        <div class="max-h-[380px] overflow-y-auto">
                            <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">
                                Tidak ada notifikasi
                            </div>
                            <button
                                v-for="notif in notifications"
                                :key="notif.id"
                                @click="handleNotifClick(notif)"
                                class="w-full flex items-start gap-3 px-4 py-3 text-left border-b transition-colors hover:opacity-80"
                                :style="{
                                    borderColor: 'var(--border-color)',
                                    background: notif.read_at ? 'transparent' : 'rgba(59,130,246,0.06)',
                                }"
                            >
                                <span
                                    v-if="!notif.read_at"
                                    class="mt-1.5 w-2 h-2 rounded-full bg-[#3B82F6] shrink-0"
                                />
                                <span v-else class="mt-1.5 w-2 h-2 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                        {{ notif.title }}
                                    </p>
                                    <p
                                        class="text-xs mt-0.5 overflow-hidden"
                                        style="color: var(--text-secondary); display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"
                                    >{{ notif.body }}</p>
                                    <p class="text-[10px] mt-1" style="color: var(--text-muted);">
                                        {{ relativeTime(notif.created_at) }}
                                    </p>
                                </div>
                            </button>
                        </div>

                        <!-- Footer -->
                        <div class="px-4 py-2.5 border-t text-center" style="border-color: var(--border-color);">
                            <Link
                                href="/notifications"
                                class="text-xs font-medium transition-colors"
                                style="color: var(--color-primary);"
                                @click="bellOpen = false"
                            >Lihat Semua &rarr;</Link>
                        </div>
                    </div>
                </div>

                <!-- Profile dropdown -->
                <div class="relative" data-profile-dropdown>
                    <button
                        @click.stop="toggleProfile"
                        class="w-8 h-8 rounded-full bg-[#3B82F6] flex items-center justify-center text-white text-xs font-bold cursor-pointer focus:outline-none"
                        aria-label="Profil"
                        data-profile-dropdown
                    >
                        {{ initials }}
                    </button>

                    <div
                        v-if="profileOpen"
                        class="absolute right-0 top-10 w-56 rounded-lg border shadow-xl z-50 overflow-hidden"
                        style="background: var(--card-bg); border-color: var(--border-color); box-shadow: var(--shadow);"
                        data-profile-dropdown
                    >
                        <!-- Header -->
                        <div class="px-4 py-3 border-b" style="border-color: var(--border-color);">
                            <p class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ user?.name }}</p>
                            <p class="text-xs truncate mt-0.5" style="color: var(--text-muted);">{{ user?.email }}</p>
                        </div>

                        <!-- Items -->
                        <div class="py-1">
                            <Link
                                href="/settings"
                                class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition hover:opacity-80"
                                style="color: var(--text-secondary);"
                                @click="profileOpen = false"
                            >
                                <Settings :size="15" />
                                Pengaturan
                            </Link>
                            <Link
                                href="/notifications"
                                class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition hover:opacity-80"
                                style="color: var(--text-secondary);"
                                @click="profileOpen = false"
                            >
                                <Bell :size="15" />
                                Notifikasi
                            </Link>
                        </div>

                        <!-- Divider -->
                        <div class="border-t" style="border-color: var(--border-color);" />

                        <!-- Logout -->
                        <div class="py-1">
                            <button
                                @click="logout"
                                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm transition hover:opacity-80 text-left"
                                style="color: #EF4444;"
                            >
                                <LogOut :size="15" />
                                Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6" style="background: var(--bg-primary);">
                <slot />
            </main>
        </div>
    </div>

    <!-- Command Palette -->
    <CommandPalette :open="paletteOpen" @close="closePalette" />
</template>
