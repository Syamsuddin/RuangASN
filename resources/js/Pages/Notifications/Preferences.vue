<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell, Mail, Smartphone, CheckSquare, CalendarDays, FileText, BarChart2, Save } from 'lucide-vue-next';

interface NotificationPreference {
    in_app: boolean;
    email: boolean;
    push: boolean;
    task_assigned: boolean;
    task_due: boolean;
    meeting_invited: boolean;
    document_approval: boolean;
    report_status: boolean;
    digest_frequency: 'realtime' | 'daily' | 'off';
}

const props = defineProps<{ preference: NotificationPreference }>();

const form = useForm<NotificationPreference>({ ...props.preference });

const save = () => form.patch('/notifications/preferences', { preserveScroll: true });

const toggle = (key: keyof NotificationPreference) => {
    if (typeof form[key] === 'boolean') {
        (form as any)[key] = !(form as any)[key];
    }
};
</script>

<template>
    <AppLayout>
        <template #breadcrumb>
            <span style="color: var(--text-muted);">
                <a href="/notifications" style="color: var(--text-muted);">Notifikasi</a>
            </span>
            <span class="mx-1.5" style="color: var(--text-muted);">/</span>
            <span style="color: var(--text-primary);">Preferensi</span>
        </template>

        <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center gap-2">
                <Bell :size="20" style="color: var(--text-primary);" />
                <h1 class="text-lg font-semibold" style="color: var(--text-primary);">Preferensi Notifikasi</h1>
            </div>

            <!-- Channels card -->
            <section class="rounded-lg border p-5 space-y-4" style="background: var(--card-bg); border-color: var(--border-color);">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Saluran Pengiriman</h2>

                <div v-for="(label, key, icon) in { in_app: 'Notifikasi dalam aplikasi', email: 'Email', push: 'Push notification' }" :key="key"
                    class="flex items-center justify-between py-2 border-b last:border-0"
                    :style="{ borderColor: 'var(--border-color)' }"
                >
                    <div class="flex items-center gap-3">
                        <Bell v-if="key === 'in_app'" :size="16" style="color: var(--text-muted);" />
                        <Mail v-else-if="key === 'email'" :size="16" style="color: var(--text-muted);" />
                        <Smartphone v-else :size="16" style="color: var(--text-muted);" />
                        <span class="text-sm" style="color: var(--text-primary);">{{ label }}</span>
                    </div>
                    <button
                        type="button"
                        @click="toggle(key as keyof typeof form)"
                        class="relative w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none"
                        :style="{ background: (form as any)[key] ? '#3B82F6' : 'var(--bg-tertiary)' }"
                        :aria-checked="(form as any)[key]"
                        role="switch"
                    >
                        <span
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"
                            :style="{ transform: (form as any)[key] ? 'translateX(20px)' : 'translateX(0)' }"
                        />
                    </button>
                </div>
            </section>

            <!-- Per-type card -->
            <section class="rounded-lg border p-5 space-y-4" style="background: var(--card-bg); border-color: var(--border-color);">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Jenis Notifikasi</h2>

                <div
                    v-for="item in [
                        { key: 'task_assigned', label: 'Tugas baru ditugaskan', icon: CheckSquare },
                        { key: 'task_due', label: 'Pengingat tenggat tugas', icon: CheckSquare },
                        { key: 'meeting_invited', label: 'Undangan meeting', icon: CalendarDays },
                        { key: 'document_approval', label: 'Permintaan persetujuan dokumen', icon: FileText },
                        { key: 'report_status', label: 'Perubahan status laporan', icon: BarChart2 },
                    ]"
                    :key="item.key"
                    class="flex items-center justify-between py-2 border-b last:border-0"
                    :style="{ borderColor: 'var(--border-color)' }"
                >
                    <div class="flex items-center gap-3">
                        <component :is="item.icon" :size="16" style="color: var(--text-muted);" />
                        <span class="text-sm" style="color: var(--text-primary);">{{ item.label }}</span>
                    </div>
                    <button
                        type="button"
                        @click="toggle(item.key as keyof typeof form)"
                        class="relative w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none"
                        :style="{ background: (form as any)[item.key] ? '#3B82F6' : 'var(--bg-tertiary)' }"
                        :aria-checked="(form as any)[item.key]"
                        role="switch"
                    >
                        <span
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"
                            :style="{ transform: (form as any)[item.key] ? 'translateX(20px)' : 'translateX(0)' }"
                        />
                    </button>
                </div>
            </section>

            <!-- Digest frequency -->
            <section class="rounded-lg border p-5 space-y-3" style="background: var(--card-bg); border-color: var(--border-color);">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Frekuensi Notifikasi Email</h2>
                <div class="flex flex-col gap-2">
                    <label
                        v-for="opt in [
                            { value: 'realtime', label: 'Segera (realtime)' },
                            { value: 'daily', label: 'Ringkasan harian' },
                            { value: 'off', label: 'Nonaktifkan email' },
                        ]"
                        :key="opt.value"
                        class="flex items-center gap-3 cursor-pointer"
                    >
                        <input
                            type="radio"
                            :value="opt.value"
                            v-model="form.digest_frequency"
                            class="w-4 h-4 accent-[#3B82F6]"
                        />
                        <span class="text-sm" style="color: var(--text-primary);">{{ opt.label }}</span>
                    </label>
                </div>
            </section>

            <!-- Save button -->
            <div class="flex justify-end">
                <button
                    type="button"
                    @click="save"
                    :disabled="form.processing"
                    class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-medium text-white transition-opacity disabled:opacity-50"
                    style="background: #3B82F6;"
                >
                    <Save :size="16" />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Preferensi' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
