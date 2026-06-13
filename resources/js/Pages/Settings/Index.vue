<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, usePage, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTheme } from '@/composables/useTheme';
import {
    User, Lock, Shield, Palette, Bell, Monitor, LogOut,
    Eye, EyeOff, Sun, Moon, Upload, CheckCircle, Copy, BrainCircuit,
} from 'lucide-vue-next';

interface UserData {
    id: string;
    nip: string;
    name: string;
    email: string;
    phone: string | null;
    bio: string | null;
    avatar_path: string | null;
    timezone: string | null;
    locale: string | null;
    mfa_enabled: boolean;
    organization?: { id: string; name: string; short_name: string; type: string };
    roles?: string[];
}

interface Session {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

const props = defineProps<{
    user: UserData;
    sessions: Session[];
    mfa_enabled: boolean;
    notificationPreference: Record<string, unknown>;
}>();

const page = usePage();
const { toggle, isDark, theme } = useTheme();

// Flash messages from Inertia
const flash = computed(() => (page.props as any).flash ?? {});
const mfaSetup     = computed(() => flash.value.mfa_setup as { secret: string; qr_url: string } | null);
const backupCodes  = computed(() => flash.value.backup_codes as string[] | null);
const successMsg   = computed(() => flash.value.success as string | null);

type Section = 'profil' | 'password' | 'keamanan' | 'tampilan' | 'notifikasi' | 'ai' | 'sesi';

const activeSection = ref<Section>('profil');

const navItems: { id: Section; label: string; icon: any }[] = [
    { id: 'profil',      label: 'Profil',        icon: User },
    { id: 'password',    label: 'Password',       icon: Lock },
    { id: 'keamanan',    label: 'Keamanan (MFA)', icon: Shield },
    { id: 'tampilan',    label: 'Tampilan',       icon: Palette },
    { id: 'notifikasi',  label: 'Notifikasi',     icon: Bell },
    { id: 'ai',          label: 'AI & Memori',    icon: BrainCircuit },
    { id: 'sesi',        label: 'Sesi Aktif',     icon: Monitor },
];

// ── Profile form ──
const profileForm = useForm({
    name:     props.user.name ?? '',
    phone:    props.user.phone ?? '',
    bio:      props.user.bio ?? '',
    timezone: props.user.timezone ?? 'Asia/Jakarta',
    locale:   props.user.locale ?? 'id',
});
const saveProfile = () => profileForm.patch('/settings/profile', { preserveScroll: true });

// ── Avatar ──
const avatarPreview = ref<string | null>(null);
const avatarFile    = ref<File | null>(null);
const avatarInput   = ref<HTMLInputElement | null>(null);

const onAvatarChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (! file) return;
    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const uploadAvatar = () => {
    if (! avatarFile.value) return;
    const fd = useForm({ avatar: avatarFile.value });
    fd.post('/settings/avatar', { preserveScroll: true });
};

// ── Password form ──
const showCurrent  = ref(false);
const showNew      = ref(false);
const showConfirm  = ref(false);

const pwForm = useForm({
    current_password: '',
    password:         '',
    password_confirmation: '',
});
const savePassword = () => pwForm.patch('/settings/password', {
    preserveScroll: true,
    onSuccess: () => pwForm.reset(),
});

// ── MFA ──
const otpInput = ref('');
const mfaDisablePassword = ref('');
const mfaDisableError    = ref('');

const startMfaSetup = () => {
    router.post('/settings/mfa/setup', {}, { preserveScroll: true });
};

const enableMfa = () => {
    router.post('/settings/mfa/enable', { otp_code: otpInput.value }, { preserveScroll: true });
};

const disableMfa = () => {
    router.post('/settings/mfa/disable', { password: mfaDisablePassword.value }, { preserveScroll: true });
};

const copiedCodes = ref(false);
const copyBackupCodes = () => {
    if (backupCodes.value) {
        navigator.clipboard.writeText(backupCodes.value.join('\n'));
        copiedCodes.value = true;
        setTimeout(() => { copiedCodes.value = false; }, 2000);
    }
};

// ── Sessions ──
const revokeSession = (tokenId: number) => {
    router.delete(`/settings/sessions/${tokenId}`, { preserveScroll: true });
};

const relativeTime = (iso: string | null) => {
    if (! iso) return 'Belum pernah digunakan';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'baru saja';
    if (mins < 60) return `${mins} menit lalu`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} jam lalu`;
    return `${Math.floor(hours / 24)} hari lalu`;
};

const avatarInitials = computed(() => {
    return (props.user.name ?? '')
        .split(' ').slice(0, 2).map((w: string) => w[0]).join('').toUpperCase();
});

const pwStrength = computed(() => {
    const p = pwForm.password;
    if (p.length < 8) return { label: 'Terlalu pendek', color: '#EF4444', width: '20%' };
    let score = 0;
    if (/[a-z]/.test(p)) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^a-zA-Z0-9]/.test(p)) score++;
    const levels = [
        { label: 'Lemah',    color: '#EF4444', width: '25%' },
        { label: 'Sedang',   color: '#F59E0B', width: '50%' },
        { label: 'Kuat',     color: '#10B981', width: '75%' },
        { label: 'Sangat Kuat', color: '#10B981', width: '100%' },
    ];
    return levels[Math.min(score - 1, 3)];
});
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-primary);" class="font-medium">Pengaturan</span>
        </template>

        <div class="flex gap-6 min-h-[calc(100vh-10rem)]">

            <!-- Left nav -->
            <aside class="w-[240px] shrink-0">
                <nav
                    class="rounded-xl border overflow-hidden"
                    style="background: var(--card-bg); border-color: var(--border-color);"
                >
                    <button
                        v-for="item in navItems"
                        :key="item.id"
                        @click="activeSection = item.id"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-left transition-colors border-b last:border-0"
                        :class="activeSection === item.id ? 'border-l-2 border-[#3B82F6]' : ''"
                        :style="{
                            borderBottomColor: 'var(--border-color)',
                            color: activeSection === item.id ? '#3B82F6' : 'var(--text-secondary)',
                            background: activeSection === item.id
                                ? (isDark() ? 'rgba(59,130,246,0.08)' : '#EFF6FF')
                                : 'transparent',
                            paddingLeft: activeSection === item.id ? '14px' : '16px',
                        }"
                    >
                        <component :is="item.icon" :size="17" />
                        {{ item.label }}
                    </button>
                </nav>
            </aside>

            <!-- Content -->
            <div class="flex-1 min-w-0 space-y-5">

                <!-- Global success flash -->
                <div
                    v-if="successMsg"
                    class="rounded-lg px-4 py-3 text-sm flex items-center gap-2"
                    style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10B981;"
                >
                    <CheckCircle :size="16" />
                    {{ successMsg }}
                </div>

                <!-- ── PROFIL ── -->
                <section v-if="activeSection === 'profil'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Profil Saya</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Informasi pribadi yang ditampilkan kepada rekan kerja.</p>
                    </div>

                    <!-- Avatar -->
                    <div
                        class="rounded-xl border p-5 flex items-center gap-5"
                        style="background: var(--card-bg); border-color: var(--border-color);"
                    >
                        <div class="w-20 h-20 rounded-full overflow-hidden shrink-0 flex items-center justify-center bg-[#3B82F6] text-white text-2xl font-bold">
                            <img v-if="avatarPreview" :src="avatarPreview" alt="avatar" class="w-full h-full object-cover" />
                            <img v-else-if="user.avatar_path" :src="`/storage/${user.avatar_path}`" alt="avatar" class="w-full h-full object-cover" />
                            <span v-else>{{ avatarInitials }}</span>
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">Foto Profil</p>
                            <p class="text-xs" style="color: var(--text-muted);">JPG, PNG, GIF. Maks 2 MB.</p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="avatarInput?.click()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
                                    style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color);"
                                >
                                    <Upload :size="13" />
                                    Pilih Foto
                                </button>
                                <button
                                    v-if="avatarFile"
                                    type="button"
                                    @click="uploadAvatar"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition"
                                    style="background: #3B82F6;"
                                >
                                    Simpan
                                </button>
                            </div>
                            <input
                                ref="avatarInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onAvatarChange"
                            />
                        </div>
                    </div>

                    <!-- Profile form -->
                    <form
                        @submit.prevent="saveProfile"
                        class="rounded-xl border p-5 space-y-4"
                        style="background: var(--card-bg); border-color: var(--border-color);"
                    >
                        <!-- Read-only fields -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-medium" style="color: var(--text-muted);">NIP</label>
                                <input :value="user.nip" readonly class="w-full px-3 py-2 rounded-lg text-sm border" style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-muted);" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium" style="color: var(--text-muted);">Email</label>
                                <input :value="user.email" readonly class="w-full px-3 py-2 rounded-lg text-sm border" style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-muted);" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium" style="color: var(--text-muted);">Instansi</label>
                                <input :value="user.organization?.name ?? '-'" readonly class="w-full px-3 py-2 rounded-lg text-sm border" style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-muted);" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium" style="color: var(--text-muted);">Peran</label>
                                <input :value="(user.roles ?? []).join(', ') || '-'" readonly class="w-full px-3 py-2 rounded-lg text-sm border" style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-muted);" />
                            </div>
                        </div>

                        <div class="border-t my-2" style="border-color: var(--border-color);" />

                        <!-- Editable fields -->
                        <div class="space-y-1">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Nama Lengkap <span style="color: #EF4444;">*</span></label>
                            <input
                                v-model="profileForm.name"
                                type="text"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none transition"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                :class="profileForm.errors.name ? 'border-[#EF4444]!' : ''"
                            />
                            <p v-if="profileForm.errors.name" class="text-xs" style="color: #EF4444;">{{ profileForm.errors.name }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Nomor HP</label>
                            <input
                                v-model="profileForm.phone"
                                type="tel"
                                placeholder="+62 812 3456 7890"
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none transition"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Bio Singkat</label>
                            <textarea
                                v-model="profileForm.bio"
                                rows="3"
                                placeholder="Tuliskan sedikit tentang Anda..."
                                class="w-full px-3 py-2 rounded-lg text-sm border outline-none transition resize-none"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Zona Waktu</label>
                                <select
                                    v-model="profileForm.timezone"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option value="Asia/Jakarta">WIB (Jakarta)</option>
                                    <option value="Asia/Makassar">WITA (Makassar)</option>
                                    <option value="Asia/Jayapura">WIT (Jayapura)</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Bahasa</label>
                                <select
                                    v-model="profileForm.locale"
                                    class="w-full px-3 py-2 rounded-lg text-sm border outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                >
                                    <option value="id">Bahasa Indonesia</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="profileForm.processing"
                                class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition disabled:opacity-60"
                                style="background: #3B82F6;"
                            >
                                {{ profileForm.processing ? 'Menyimpan...' : 'Simpan Profil' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- ── PASSWORD ── -->
                <section v-if="activeSection === 'password'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Ubah Password</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Pastikan password baru Anda cukup kuat dan tidak mudah ditebak.</p>
                    </div>

                    <form
                        @submit.prevent="savePassword"
                        class="rounded-xl border p-5 space-y-4 max-w-md"
                        style="background: var(--card-bg); border-color: var(--border-color);"
                    >
                        <!-- Current password -->
                        <div class="space-y-1">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Password Saat Ini</label>
                            <div class="relative">
                                <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                                <input
                                    v-model="pwForm.current_password"
                                    :type="showCurrent ? 'text' : 'password'"
                                    class="w-full pl-9 pr-10 py-2.5 rounded-lg border text-sm outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    :class="pwForm.errors.current_password ? 'border-[#EF4444]!' : ''"
                                />
                                <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);">
                                    <EyeOff v-if="showCurrent" :size="16" /> <Eye v-else :size="16" />
                                </button>
                            </div>
                            <p v-if="pwForm.errors.current_password" class="text-xs" style="color: #EF4444;">{{ pwForm.errors.current_password }}</p>
                        </div>

                        <!-- New password -->
                        <div class="space-y-1">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Password Baru</label>
                            <div class="relative">
                                <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                                <input
                                    v-model="pwForm.password"
                                    :type="showNew ? 'text' : 'password'"
                                    placeholder="Min. 8 karakter"
                                    class="w-full pl-9 pr-10 py-2.5 rounded-lg border text-sm outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    :class="pwForm.errors.password ? 'border-[#EF4444]!' : ''"
                                />
                                <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);">
                                    <EyeOff v-if="showNew" :size="16" /> <Eye v-else :size="16" />
                                </button>
                            </div>
                            <!-- Strength bar -->
                            <div v-if="pwForm.password" class="space-y-1 mt-1.5">
                                <div class="h-1 rounded-full w-full" style="background: var(--bg-tertiary);">
                                    <div
                                        class="h-1 rounded-full transition-all"
                                        :style="{ width: pwStrength.width, background: pwStrength.color }"
                                    />
                                </div>
                                <p class="text-xs" :style="{ color: pwStrength.color }">{{ pwStrength.label }}</p>
                            </div>
                            <p v-if="pwForm.errors.password" class="text-xs" style="color: #EF4444;">{{ pwForm.errors.password }}</p>
                        </div>

                        <!-- Confirm -->
                        <div class="space-y-1">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Konfirmasi Password Baru</label>
                            <div class="relative">
                                <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                                <input
                                    v-model="pwForm.password_confirmation"
                                    :type="showConfirm ? 'text' : 'password'"
                                    class="w-full pl-9 pr-10 py-2.5 rounded-lg border text-sm outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                />
                                <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);">
                                    <EyeOff v-if="showConfirm" :size="16" /> <Eye v-else :size="16" />
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="pwForm.processing"
                                class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition disabled:opacity-60"
                                style="background: #3B82F6;"
                            >
                                {{ pwForm.processing ? 'Menyimpan...' : 'Ubah Password' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- ── KEAMANAN / MFA ── -->
                <section v-if="activeSection === 'keamanan'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Keamanan (MFA)</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Autentikasi dua faktor menambah lapisan keamanan ekstra pada akun Anda.</p>
                    </div>

                    <div class="rounded-xl border p-5 space-y-5" style="background: var(--card-bg); border-color: var(--border-color);">

                        <!-- Status badge -->
                        <div class="flex items-center gap-3">
                            <Shield :size="20" :style="{ color: mfa_enabled ? '#10B981' : '#94A3B8' }" />
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">
                                    Status MFA:
                                    <span :style="{ color: mfa_enabled ? '#10B981' : '#EF4444' }">
                                        {{ mfa_enabled ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                                    {{ mfa_enabled ? 'Akun Anda dilindungi autentikasi dua faktor.' : 'Aktifkan MFA untuk meningkatkan keamanan akun.' }}
                                </p>
                            </div>
                        </div>

                        <!-- MFA not enabled: setup flow -->
                        <template v-if="!mfa_enabled">
                            <!-- Step 1: Start setup -->
                            <div v-if="!mfaSetup && !backupCodes">
                                <button
                                    @click="startMfaSetup"
                                    class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition"
                                    style="background: #3B82F6;"
                                >
                                    Aktifkan MFA
                                </button>
                            </div>

                            <!-- Step 2: Show secret + OTP input -->
                            <div v-if="mfaSetup" class="space-y-4 border-t pt-4" style="border-color: var(--border-color);">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    1. Scan QR atau masukkan kunci rahasia berikut ke aplikasi authenticator Anda (Google Authenticator, Authy, dll):
                                </p>
                                <div
                                    class="rounded-lg p-3 font-mono text-sm break-all"
                                    style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color);"
                                >
                                    {{ mfaSetup.secret }}
                                </div>
                                <div
                                    class="rounded-lg p-3 text-xs break-all"
                                    style="background: var(--bg-tertiary); color: var(--text-muted); border: 1px solid var(--border-color);"
                                >
                                    <span class="font-semibold">URL Manual:</span> {{ mfaSetup.qr_url }}
                                </div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    2. Masukkan kode 6-digit dari aplikasi untuk mengonfirmasi:
                                </p>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model="otpInput"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="6"
                                        placeholder="000000"
                                        class="w-32 text-center text-lg font-bold tracking-widest px-3 py-2 rounded-lg border outline-none"
                                        style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    />
                                    <button
                                        @click="enableMfa"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition"
                                        style="background: #10B981;"
                                    >
                                        Aktifkan
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- MFA enabled: disable form -->
                        <template v-if="mfa_enabled && !backupCodes">
                            <div class="border-t pt-4 space-y-3" style="border-color: var(--border-color);">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Nonaktifkan MFA</p>
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <Lock :size="15" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                                        <input
                                            v-model="mfaDisablePassword"
                                            type="password"
                                            placeholder="Password Anda"
                                            class="pl-9 pr-3 py-2 rounded-lg text-sm border outline-none"
                                            style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                        />
                                    </div>
                                    <button
                                        @click="disableMfa"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition"
                                        style="background: #EF4444;"
                                    >
                                        Nonaktifkan MFA
                                    </button>
                                </div>
                                <p v-if="mfaDisableError" class="text-xs" style="color: #EF4444;">{{ mfaDisableError }}</p>
                            </div>
                        </template>

                        <!-- Backup codes shown once -->
                        <div v-if="backupCodes" class="border-t pt-4 space-y-3" style="border-color: var(--border-color);">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold" style="color: #10B981;">MFA Berhasil Diaktifkan!</p>
                                <button
                                    @click="copyBackupCodes"
                                    class="flex items-center gap-1 text-xs transition"
                                    style="color: var(--text-muted);"
                                >
                                    <Copy :size="13" />
                                    {{ copiedCodes ? 'Tersalin!' : 'Salin' }}
                                </button>
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Simpan 10 kode cadangan berikut di tempat yang aman. Kode ini hanya ditampilkan sekali.
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="code in backupCodes"
                                    :key="code"
                                    class="px-3 py-1.5 rounded-md font-mono text-sm text-center"
                                    style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color);"
                                >
                                    {{ code }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── TAMPILAN ── -->
                <section v-if="activeSection === 'tampilan'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Tampilan</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Sesuaikan tema tampilan sesuai preferensi Anda.</p>
                    </div>

                    <div class="rounded-xl border p-5 space-y-5" style="background: var(--card-bg); border-color: var(--border-color);">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Mode Tampilan</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                                    Saat ini: <span class="font-semibold">{{ isDark() ? 'Dark Mode' : 'Light Mode' }}</span>
                                </p>
                            </div>
                            <button
                                @click="toggle"
                                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border transition"
                                style="background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);"
                            >
                                <Sun v-if="isDark()" :size="16" />
                                <Moon v-else :size="16" />
                                {{ isDark() ? 'Beralih ke Light' : 'Beralih ke Dark' }}
                            </button>
                        </div>

                        <!-- Preview cards -->
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Dark preview -->
                            <button
                                @click="theme = 'dark'"
                                class="rounded-xl p-3 text-left border-2 transition"
                                :style="{
                                    background: '#0F172A',
                                    borderColor: theme === 'dark' ? '#3B82F6' : '#334155',
                                }"
                            >
                                <div class="w-full h-2 rounded mb-2" style="background: #1E293B;" />
                                <div class="w-3/4 h-1.5 rounded mb-1.5" style="background: #334155;" />
                                <div class="w-1/2 h-1.5 rounded" style="background: #334155;" />
                                <p class="text-xs mt-2 font-medium" style="color: #94A3B8;">Dark Mode</p>
                            </button>
                            <!-- Light preview -->
                            <button
                                @click="theme = 'light'"
                                class="rounded-xl p-3 text-left border-2 transition"
                                :style="{
                                    background: '#F8FAFC',
                                    borderColor: theme === 'light' ? '#3B82F6' : '#E2E8F0',
                                }"
                            >
                                <div class="w-full h-2 rounded mb-2" style="background: #FFFFFF;" />
                                <div class="w-3/4 h-1.5 rounded mb-1.5" style="background: #E2E8F0;" />
                                <div class="w-1/2 h-1.5 rounded" style="background: #E2E8F0;" />
                                <p class="text-xs mt-2 font-medium" style="color: #475569;">Light Mode</p>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ── NOTIFIKASI ── -->
                <section v-if="activeSection === 'notifikasi'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Preferensi Notifikasi</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Kelola preferensi notifikasi Anda secara lengkap di halaman khusus.</p>
                    </div>

                    <div class="rounded-xl border p-5" style="background: var(--card-bg); border-color: var(--border-color);">
                        <Link
                            href="/notifications/preferences"
                            class="flex items-center gap-3 text-sm font-medium transition"
                            style="color: #3B82F6;"
                        >
                            <Bell :size="18" />
                            Buka Halaman Preferensi Notifikasi
                        </Link>
                    </div>
                </section>

                <!-- ── AI & MEMORI ── -->
                <section v-if="activeSection === 'ai'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">AI &amp; Memori</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Kelola catatan yang diingat asisten AI tentang Anda.</p>
                    </div>

                    <div class="rounded-xl border p-5" style="background: var(--card-bg); border-color: var(--border-color);">
                        <Link
                            href="/ai/memories"
                            class="flex items-center gap-3 text-sm font-medium transition"
                            style="color: #8B5CF6;"
                        >
                            <BrainCircuit :size="18" />
                            Kelola Memori AI
                        </Link>
                    </div>
                </section>

                <!-- ── SESI AKTIF ── -->
                <section v-if="activeSection === 'sesi'" class="space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">Sesi Aktif</h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-muted);">Token API/mobile aktif yang terhubung ke akun Anda.</p>
                    </div>

                    <div
                        class="rounded-xl border overflow-hidden"
                        style="background: var(--card-bg); border-color: var(--border-color);"
                    >
                        <div v-if="sessions.length === 0" class="px-5 py-10 text-center text-sm" style="color: var(--text-muted);">
                            Tidak ada sesi aktif.
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Nama</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Terakhir Digunakan</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold" style="color: var(--text-muted);">Dibuat</th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="session in sessions"
                                    :key="session.id"
                                    class="border-b last:border-0"
                                    style="border-color: var(--border-color);"
                                >
                                    <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ session.name }}</td>
                                    <td class="px-4 py-3" style="color: var(--text-secondary);">{{ relativeTime(session.last_used_at) }}</td>
                                    <td class="px-4 py-3" style="color: var(--text-secondary);">{{ relativeTime(session.created_at) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            @click="revokeSession(session.id)"
                                            class="flex items-center gap-1 ml-auto text-xs font-medium transition hover:opacity-70"
                                            style="color: #EF4444;"
                                        >
                                            <LogOut :size="13" />
                                            Cabut
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>
