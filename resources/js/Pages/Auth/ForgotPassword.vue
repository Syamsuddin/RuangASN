<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { Mail, Sun, Moon, ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const { toggle, isDark } = useTheme();
const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const form = useForm({ email: '' });

const submit = () => {
    form.post('/forgot-password', { preserveScroll: true });
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
                    Lupa Password?<br>
                    <span style="color: #3B82F6;">Kami Siap Membantu</span>
                </h1>
                <p class="text-base leading-relaxed max-w-md" style="color: var(--text-secondary);">
                    Masukkan alamat email dinas Anda dan kami akan mengirimkan tautan untuk mereset password Anda.
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
                        Masukkan email dinas untuk menerima tautan reset password.
                    </p>
                </div>

                <!-- Status / success message -->
                <div
                    v-if="status"
                    class="rounded-lg px-4 py-3 text-sm"
                    style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10B981;"
                >
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Email Dinas</label>
                        <div class="relative">
                            <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="nama@instansi.go.id"
                                autocomplete="email"
                                autofocus
                                class="w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm outline-none transition"
                                style="background: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);"
                                :class="form.errors.email ? 'border-[#EF4444]!' : ''"
                            />
                        </div>
                        <p v-if="form.errors.email" class="text-xs" style="color: #EF4444;">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition disabled:opacity-60"
                        style="background: #3B82F6;"
                    >
                        <span v-if="form.processing">Mengirim...</span>
                        <span v-else>Kirim Tautan Reset</span>
                    </button>

                    <a
                        href="/login"
                        class="flex items-center justify-center gap-1.5 text-sm transition"
                        style="color: var(--text-muted);"
                    >
                        <ArrowLeft :size="14" />
                        Kembali ke login
                    </a>
                </form>
            </div>
        </div>
    </div>
</template>
