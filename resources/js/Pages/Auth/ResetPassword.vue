<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { Lock, Eye, EyeOff, Sun, Moon } from 'lucide-vue-next';

const props = defineProps<{ token: string; email: string }>();

const { toggle, isDark } = useTheme();
const showPassword = ref(false);
const showConfirm  = ref(false);

const form = useForm({
    token:                props.token,
    email:                props.email,
    password:             '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => {
            form.password = '';
            form.password_confirmation = '';
        },
    });
};
</script>

<template>
    <div class="min-h-screen flex" style="background: var(--bg-primary); font-family: var(--font-sans);">

        <!-- Left panel -->
        <div
            class="hidden lg:flex lg:w-[58%] flex-col justify-between p-12 relative overflow-hidden"
            :style="isDark()
                ? 'background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);'
                : 'background: linear-gradient(135deg, #EFF6FF 0%, #E0E7FF 100%);'"
        >
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full opacity-10" style="background: #3B82F6;" />
            <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full opacity-5" style="background: #8B5CF6;" />

            <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: #3B82F6;">
                    <span class="text-white font-bold text-lg">R</span>
                </div>
                <span class="text-xl font-bold" style="color: var(--text-primary);">RuangASN</span>
            </div>

            <div class="relative space-y-4">
                <h1 class="text-4xl font-bold leading-tight" style="color: var(--text-primary);">
                    Buat Password<br>
                    <span style="color: #3B82F6;">Baru yang Kuat</span>
                </h1>
                <p class="text-base leading-relaxed max-w-md" style="color: var(--text-secondary);">
                    Gunakan kombinasi huruf besar, kecil, angka, dan simbol untuk password yang lebih aman.
                </p>
            </div>

            <p class="relative text-xs" style="color: var(--text-muted);">
                © 2026 RuangASN · Dikembangkan untuk ASN Indonesia
            </p>
        </div>

        <!-- Right: Form panel -->
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

                <div class="space-y-1">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Reset Password</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Masukkan password baru untuk akun <span class="font-medium" style="color: var(--text-primary);">{{ form.email }}</span>.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Hidden fields -->
                    <input type="hidden" :value="form.token" name="token" />
                    <input type="hidden" :value="form.email" name="email" />

                    <!-- Password baru -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Password Baru</label>
                        <div class="relative">
                            <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Min. 8 karakter"
                                autocomplete="new-password"
                                autofocus
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

                    <!-- Konfirmasi password -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Konfirmasi Password</label>
                        <div class="relative">
                            <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                            <input
                                v-model="form.password_confirmation"
                                :type="showConfirm ? 'text' : 'password'"
                                placeholder="Ulangi password baru"
                                autocomplete="new-password"
                                class="w-full pl-9 pr-10 py-2.5 rounded-lg border text-sm outline-none transition"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                :class="form.errors.password_confirmation ? 'border-[#EF4444]!' : ''"
                            />
                            <button
                                type="button"
                                @click="showConfirm = !showConfirm"
                                class="absolute right-3 top-1/2 -translate-y-1/2"
                                style="color: var(--text-muted);"
                            >
                                <EyeOff v-if="showConfirm" :size="16" />
                                <Eye v-else :size="16" />
                            </button>
                        </div>
                        <p v-if="form.errors.password_confirmation" class="text-xs" style="color: #EF4444;">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <!-- Error banner -->
                    <div
                        v-if="form.errors.email"
                        class="rounded-lg px-4 py-3 text-sm"
                        style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #EF4444;"
                    >
                        {{ form.errors.email }}
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition disabled:opacity-60"
                        style="background: #3B82F6;"
                    >
                        <span v-if="form.processing">Menyimpan...</span>
                        <span v-else>Reset Password</span>
                    </button>

                    <a
                        href="/login"
                        class="block text-center text-sm transition"
                        style="color: var(--text-muted);"
                    >
                        ← Kembali ke login
                    </a>
                </form>
            </div>
        </div>
    </div>
</template>
