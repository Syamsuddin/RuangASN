<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { Mail, Lock, Eye, EyeOff, Building2, Sun, Moon, Sparkles, CheckSquare, BarChart3 } from 'lucide-vue-next';

const { theme, toggle, isDark } = useTheme();
const showPassword = ref(false);
const showMfa = ref(false);
const mfaToken = ref('');
const otpCode = ref('');

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onSuccess: (page) => {
            if ((page.props as any).requires_mfa) {
                showMfa.value = true;
                mfaToken.value = (page.props as any).mfa_token;
            }
        },
    });
};

const submitMfa = () => {
    useForm({ mfa_token: mfaToken.value, otp_code: otpCode.value }).post('/login/mfa');
};

const features = [
    { icon: Sparkles,    label: 'AI Assistant',           desc: 'Sekretaris digital berbasis AI untuk briefing harian' },
    { icon: CheckSquare, label: 'Manajemen Tugas',         desc: 'Kanban, Gantt, dan pelacakan kinerja terintegrasi' },
    { icon: BarChart3,   label: 'Analitik Kinerja',        desc: 'Dashboard SKP, laporan otomatis, dan BI eksekutif' },
];
</script>

<template>
    <div class="min-h-screen flex" style="background: var(--bg-primary); font-family: var(--font-sans);">

        <!-- ── Left: Hero panel (hidden on mobile) ── -->
        <div
            class="hidden lg:flex lg:w-[58%] flex-col justify-between p-12 relative overflow-hidden"
            :style="isDark()
                ? 'background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);'
                : 'background: linear-gradient(135deg, #EFF6FF 0%, #E0E7FF 100%);'"
        >
            <!-- Decorative circles -->
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full opacity-10" style="background: #3B82F6;" />
            <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full opacity-5" style="background: #8B5CF6;" />

            <!-- Logo -->
            <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: #3B82F6;">
                    <span class="text-white font-bold text-lg">R</span>
                </div>
                <span class="text-xl font-bold" style="color: var(--text-primary);">RuangASN</span>
            </div>

            <!-- Center content -->
            <div class="relative space-y-8">
                <div class="space-y-4">
                    <h1 class="text-4xl font-bold leading-tight" style="color: var(--text-primary);">
                        Ruang Kerja Digital<br>untuk <span style="color: #3B82F6;">ASN Indonesia</span>
                    </h1>
                    <p class="text-base leading-relaxed max-w-md" style="color: var(--text-secondary);">
                        Platform kolaborasi & manajemen kinerja terpadu untuk Aparatur Sipil Negara — lebih produktif, lebih terukur.
                    </p>
                </div>

                <!-- Feature chips -->
                <div class="space-y-3">
                    <div
                        v-for="f in features"
                        :key="f.label"
                        class="flex items-start gap-4 rounded-xl p-4"
                        :style="isDark()
                            ? 'background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);'
                            : 'background: rgba(255,255,255,0.7); border: 1px solid rgba(59,130,246,0.15);'"
                    >
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background: rgba(59,130,246,0.15);">
                            <component :is="f.icon" :size="18" style="color: #3B82F6;" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ f.label }}</p>
                            <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ f.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="relative text-xs" style="color: var(--text-muted);">
                © 2026 RuangASN · Dikembangkan untuk ASN Indonesia
            </p>
        </div>

        <!-- ── Right: Form panel ── -->
        <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 relative" style="background: var(--bg-primary);">

            <!-- Theme toggle -->
            <button
                @click="toggle"
                class="absolute top-6 right-6 w-9 h-9 rounded-lg flex items-center justify-center transition-colors"
                style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-muted);"
            >
                <Sun v-if="isDark()" :size="18" />
                <Moon v-else :size="18" />
            </button>

            <div class="w-full max-w-sm space-y-8">
                <!-- Mobile logo -->
                <div class="lg:hidden flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: #3B82F6;">
                        <span class="text-white font-bold">R</span>
                    </div>
                    <span class="font-bold text-lg" style="color: var(--text-primary);">RuangASN</span>
                </div>

                <!-- ── MFA form ── -->
                <template v-if="showMfa">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Verifikasi Dua Langkah</h2>
                        <p class="text-sm" style="color: var(--text-secondary);">Masukkan kode 6-digit dari aplikasi authenticator Anda.</p>
                    </div>
                    <form @submit.prevent="submitMfa" class="space-y-5">
                        <div>
                            <input
                                v-model="otpCode"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                placeholder="000000"
                                autofocus
                                class="w-full text-center text-2xl font-bold tracking-[0.5em] px-4 py-3 rounded-lg border outline-none transition"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                            />
                        </div>
                        <button
                            type="submit"
                            class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition"
                            style="background: #3B82F6;"
                        >Verifikasi</button>
                        <button type="button" @click="showMfa = false" class="w-full text-sm text-center" style="color: var(--text-muted);">
                            ← Kembali ke login
                        </button>
                    </form>
                </template>

                <!-- ── Login form ── -->
                <template v-else>
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Masuk ke Akun Anda</h2>
                        <p class="text-sm" style="color: var(--text-secondary);">Gunakan email dinas atau NIP Anda.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Email / NIP -->
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Email / NIP</label>
                            <div class="relative">
                                <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                                <input
                                    v-model="form.login"
                                    type="text"
                                    placeholder="nama@instansi.go.id"
                                    autocomplete="username"
                                    autofocus
                                    class="w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    :class="form.errors.login ? 'border-[#EF4444]!' : ''"
                                />
                            </div>
                            <p v-if="form.errors.login" class="text-xs" style="color: #EF4444;">{{ form.errors.login }}</p>
                        </div>

                        <!-- Password -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Password</label>
                                <a href="/forgot-password" class="text-xs" style="color: #3B82F6;">Lupa password?</a>
                            </div>
                            <div class="relative">
                                <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    class="w-full pl-9 pr-10 py-2.5 rounded-lg border text-sm outline-none transition"
                                    style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    :class="form.errors.password ? 'border-[#EF4444]!' : ''"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2"
                                    style="color: var(--text-muted);"
                                >
                                    <EyeOff v-if="showPassword" :size="16" />
                                    <Eye v-else :size="16" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="text-xs" style="color: #EF4444;">{{ form.errors.password }}</p>
                        </div>

                        <!-- Remember -->
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded accent-[#3B82F6]"
                            />
                            <span class="text-sm" style="color: var(--text-secondary);">Ingat saya selama 30 hari</span>
                        </label>

                        <!-- Error banner -->
                        <div
                            v-if="form.errors.login || form.errors.password"
                            class="rounded-lg px-4 py-3 text-sm"
                            style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #EF4444;"
                        >
                            {{ form.errors.login || form.errors.password }}
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition disabled:opacity-60"
                            style="background: #3B82F6;"
                        >
                            <span v-if="form.processing">Memproses...</span>
                            <span v-else>Masuk</span>
                        </button>

                        <!-- Divider -->
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-px" style="background: var(--border-color);" />
                            <span class="text-xs" style="color: var(--text-muted);">atau</span>
                            <div class="flex-1 h-px" style="background: var(--border-color);" />
                        </div>

                        <!-- SSO -->
                        <button
                            type="button"
                            class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium border transition"
                            style="background: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                        >
                            <Building2 :size="16" style="color: var(--text-muted);" />
                            Masuk dengan SSO Instansi
                        </button>
                    </form>

                    <p class="text-center text-xs" style="color: var(--text-muted);">
                        Butuh akun? Hubungi admin OPD Anda.
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>
