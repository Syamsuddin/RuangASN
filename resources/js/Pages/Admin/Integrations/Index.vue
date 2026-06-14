<script setup lang="ts">
import { ref, computed, reactive } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTheme } from '@/composables/useTheme';
import {
    Sparkles, Database, Mic, HardDrive, Mail, Radio, Video,
    MessageCircle, Landmark, Archive, KeyRound, Plug,
    CheckCircle, Eye, EyeOff, ShieldCheck, Info, Save, Clock, Activity,
} from 'lucide-vue-next';

interface Field {
    key: string;
    label: string;
    type: 'text' | 'number' | 'select' | 'bool' | 'secret';
    secret?: boolean;
    options?: string[];
    placeholder?: string;
    help?: string;
    step?: string;
    provider?: string;
    group_label?: string;
}

interface Group {
    label: string;
    icon: string;
    description: string;
    phase4?: boolean;
    fields: Field[];
}

const props = defineProps<{
    schema: Record<string, Group>;
    values: Record<string, Record<string, any>>;
    organizationName: string;
}>();

const page = usePage();
const { isDark } = useTheme();

const flash = computed(() => (page.props as any).flash ?? {});
const successMsg = computed(() => flash.value.success as string | null);
const errors = computed(() => (page.props as any).errors ?? {});

const iconMap: Record<string, any> = {
    Sparkles, Database, Mic, HardDrive, Mail, Radio, Video,
    MessageCircle, Landmark, Archive, KeyRound, Plug,
};

const groupKeys = computed(() => Object.keys(props.schema));
const activeGroup = ref<string>(groupKeys.value[0] ?? 'ai');

const activeMeta = computed(() => props.schema[activeGroup.value]);

// Show/hide toggles per secret field key.
const revealed = reactive<Record<string, boolean>>({});
const toggleReveal = (k: string) => { revealed[k] = !revealed[k]; };

// One reactive form, rebuilt per active group on save.
const buildInitial = (group: string): Record<string, any> => {
    const out: Record<string, any> = {};
    for (const f of props.schema[group].fields) {
        const current = props.values[group]?.[f.key];
        if (f.secret) {
            out[f.key] = ''; // never prefill secrets; empty = keep existing
        } else if (f.type === 'bool') {
            out[f.key] = !!current;
        } else {
            out[f.key] = current ?? '';
        }
    }
    return out;
};

const form = useForm<{ group: string; fields: Record<string, any> }>({
    group: activeGroup.value,
    fields: buildInitial(activeGroup.value),
});

const selectGroup = (g: string) => {
    activeGroup.value = g;
    form.group = g;
    form.fields = buildInitial(g);
    form.clearErrors();
};

const isConfigured = (group: string, key: string): boolean => {
    const v = props.values[group]?.[key];
    return !!(v && typeof v === 'object' && v.configured);
};

const submit = () => {
    // Strip empty secret fields so they are not overwritten server-side.
    const payload: Record<string, any> = {};
    for (const f of activeMeta.value.fields) {
        const v = form.fields[f.key];
        if (f.secret && (v === '' || v === null || v === undefined)) continue;
        payload[f.key] = v;
    }
    form
        .transform(() => ({ group: activeGroup.value, fields: payload }))
        .patch('/admin/integrations', { preserveScroll: true });
};

// Group AI fields into sub-cards (per-provider + embedding + top-level).
const aiSubgroups = computed(() => {
    if (activeGroup.value !== 'ai') return null;
    const top: Field[] = [];
    const providers: Record<string, Field[]> = {};
    const embedding: Field[] = [];
    for (const f of activeMeta.value.fields) {
        if (f.provider) {
            (providers[f.provider] ??= []).push(f);
        } else if (f.group_label === 'Embedding') {
            embedding.push(f);
        } else {
            top.push(f);
        }
    }
    return { top, providers, embedding };
});

const fieldError = (key: string) => errors.value[`fields.${key}`] as string | undefined;
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs mb-1" style="color: var(--text-muted);">
                    <Plug :size="13" /> Administrasi / Integrasi
                </div>
                <h1 class="text-xl font-semibold" style="color: var(--text-primary);">Integrasi Eksternal</h1>
                <p class="text-sm mt-0.5" style="color: var(--text-muted);">
                    Kredensial &amp; konfigurasi layanan eksternal untuk
                    <span class="font-medium" style="color: var(--text-secondary);">{{ organizationName }}</span>.
                    Nilai rahasia disimpan terenkripsi dan tidak pernah ditampilkan kembali.
                </p>
            </div>
            <a href="/admin/integrations/monitor"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border transition"
                style="border-color: var(--border-color); color: var(--text-secondary);">
                <Activity :size="15" /> Monitor
            </a>
        </div>

        <div
            v-if="successMsg"
            class="mb-5 rounded-lg px-4 py-3 text-sm flex items-center gap-2"
            style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10B981;"
        >
            <CheckCircle :size="16" /> {{ successMsg }}
        </div>

        <div class="flex gap-6 min-h-[calc(100vh-14rem)]">
            <!-- Left nav -->
            <aside class="w-[240px] shrink-0">
                <nav
                    class="rounded-xl border overflow-hidden"
                    style="background: var(--card-bg); border-color: var(--border-color);"
                >
                    <button
                        v-for="g in groupKeys"
                        :key="g"
                        @click="selectGroup(g)"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-left transition-colors border-b last:border-0"
                        :class="activeGroup === g ? 'border-l-2 border-[#8B5CF6]' : ''"
                        :style="{
                            borderBottomColor: 'var(--border-color)',
                            color: activeGroup === g ? '#8B5CF6' : 'var(--text-secondary)',
                            background: activeGroup === g
                                ? (isDark() ? 'rgba(139,92,246,0.08)' : '#F5F3FF')
                                : 'transparent',
                            paddingLeft: activeGroup === g ? '14px' : '16px',
                        }"
                    >
                        <component :is="iconMap[schema[g].icon] ?? Plug" :size="17" class="shrink-0" />
                        <span class="truncate flex-1">{{ schema[g].label }}</span>
                        <Clock v-if="schema[g].phase4" :size="13" style="color: var(--text-muted);" />
                    </button>
                </nav>
            </aside>

            <!-- Right content -->
            <div class="flex-1 min-w-0 space-y-5">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold" style="color: var(--text-primary);">{{ activeMeta.label }}</h2>
                    </div>
                    <p class="text-sm mt-0.5 flex items-start gap-1.5" style="color: var(--text-muted);">
                        <Info :size="14" class="mt-0.5 shrink-0" /> {{ activeMeta.description }}
                    </p>
                    <div
                        v-if="activeMeta.phase4"
                        class="mt-2 inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium"
                        style="background: rgba(245,158,11,0.12); color: #F59E0B; border: 1px solid rgba(245,158,11,0.3);"
                    >
                        <Clock :size="12" /> Akan aktif pada fase integrasi
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- AI: grouped sub-cards -->
                    <template v-if="aiSubgroups">
                        <div
                            class="rounded-xl border p-5 space-y-4"
                            style="background: var(--card-bg); border-color: var(--border-color);"
                        >
                            <p class="text-sm font-semibold" style="color: var(--text-primary);">Umum</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div v-for="f in aiSubgroups.top" :key="f.key" class="space-y-1">
                                    <FieldControl :field="f" :form="form" :revealed="revealed" :configured="isConfigured(activeGroup, f.key)" :error="fieldError(f.key)" @toggle="toggleReveal" />
                                </div>
                            </div>
                        </div>

                        <div
                            v-for="(fields, prov) in aiSubgroups.providers"
                            :key="prov"
                            class="rounded-xl border p-5 space-y-4"
                            style="background: var(--card-bg); border-color: var(--border-color);"
                        >
                            <p class="text-sm font-semibold capitalize" style="color: var(--text-primary);">{{ prov }}</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div v-for="f in fields" :key="f.key" class="space-y-1" :class="f.type === 'bool' || f.type === 'secret' ? 'col-span-2' : ''">
                                    <FieldControl :field="f" :form="form" :revealed="revealed" :configured="isConfigured(activeGroup, f.key)" :error="fieldError(f.key)" @toggle="toggleReveal" />
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border p-5 space-y-4"
                            style="background: var(--card-bg); border-color: var(--border-color);"
                        >
                            <p class="text-sm font-semibold" style="color: var(--text-primary);">Embedding</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div v-for="f in aiSubgroups.embedding" :key="f.key" class="space-y-1" :class="f.type === 'secret' ? 'col-span-2' : ''">
                                    <FieldControl :field="f" :form="form" :revealed="revealed" :configured="isConfigured(activeGroup, f.key)" :error="fieldError(f.key)" @toggle="toggleReveal" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Generic group: single card -->
                    <div
                        v-else
                        class="rounded-xl border p-5 space-y-4"
                        style="background: var(--card-bg); border-color: var(--border-color);"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                v-for="f in activeMeta.fields"
                                :key="f.key"
                                class="space-y-1"
                                :class="f.type === 'bool' || f.type === 'secret' ? 'col-span-2' : ''"
                            >
                                <FieldControl :field="f" :form="form" :revealed="revealed" :configured="isConfigured(activeGroup, f.key)" :error="fieldError(f.key)" @toggle="toggleReveal" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs flex items-center gap-1.5" style="color: var(--text-muted);">
                            <ShieldCheck :size="13" /> Field rahasia bertanda kunci disimpan terenkripsi.
                        </p>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition disabled:opacity-60"
                            style="background: #8B5CF6;"
                        >
                            <Save :size="15" /> Simpan {{ activeMeta.label }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script lang="ts">
import { defineComponent, h } from 'vue';

// Inline field control component — keeps the template above readable.
const FieldControl = defineComponent({
    name: 'FieldControl',
    props: {
        field: { type: Object, required: true },
        form: { type: Object, required: true },
        revealed: { type: Object, required: true },
        configured: { type: Boolean, default: false },
        error: { type: String, default: undefined },
    },
    emits: ['toggle'],
    setup(props, { emit }) {
        return () => {
            const f: any = props.field;
            const form: any = props.form;
            const label = h('label', {
                class: 'text-xs font-medium flex items-center gap-1.5',
                style: 'color: var(--text-muted);',
            }, [
                f.label,
                f.secret ? h('span', { class: 'text-[10px]', title: 'Rahasia — terenkripsi' }, '🔒') : null,
                f.secret && props.configured
                    ? h('span', {
                        class: 'text-[10px] rounded px-1.5 py-0.5',
                        style: 'background: rgba(16,185,129,0.12); color:#10B981;',
                    }, 'Terkonfigurasi')
                    : null,
            ]);

            const baseInputStyle = 'background: var(--bg-tertiary); border-color: var(--border-color); color: var(--text-primary);';
            const inputClass = 'w-full px-3 py-2 rounded-lg text-sm border outline-none focus:border-[#8B5CF6]';

            let control: any;

            if (f.type === 'bool') {
                const on = !!form.fields[f.key];
                control = h('button', {
                    type: 'button',
                    onClick: () => { form.fields[f.key] = !on; },
                    class: 'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                    style: `background: ${on ? '#8B5CF6' : 'var(--bg-tertiary)'}; border:1px solid var(--border-color);`,
                }, h('span', {
                    class: 'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                    style: `transform: translateX(${on ? '22px' : '4px'});`,
                }));
                return h('div', { class: 'flex items-center justify-between' }, [label, control]);
            }

            if (f.type === 'select') {
                control = h('select', {
                    class: inputClass,
                    style: baseInputStyle,
                    value: form.fields[f.key],
                    onChange: (e: any) => { form.fields[f.key] = e.target.value; },
                }, (f.options ?? []).map((o: string) => h('option', { value: o }, o)));
            } else if (f.type === 'secret') {
                const show = !!props.revealed[f.key];
                control = h('div', { class: 'relative' }, [
                    h('input', {
                        type: show ? 'text' : 'password',
                        class: inputClass + ' pr-10',
                        style: baseInputStyle,
                        value: form.fields[f.key],
                        placeholder: props.configured ? '•••••• (biarkan kosong untuk mempertahankan)' : (f.placeholder ?? ''),
                        autocomplete: 'new-password',
                        onInput: (e: any) => { form.fields[f.key] = e.target.value; },
                    }),
                    h('button', {
                        type: 'button',
                        class: 'absolute right-2 top-1/2 -translate-y-1/2',
                        style: 'color: var(--text-muted);',
                        onClick: () => emit('toggle', f.key),
                    }, show ? '🙈' : '👁'),
                ]);
            } else {
                control = h('input', {
                    type: f.type === 'number' ? 'number' : 'text',
                    step: f.step,
                    class: inputClass,
                    style: baseInputStyle,
                    value: form.fields[f.key],
                    placeholder: f.placeholder ?? '',
                    onInput: (e: any) => { form.fields[f.key] = e.target.value; },
                });
            }

            return h('div', { class: 'space-y-1' }, [
                label,
                control,
                f.help ? h('p', { class: 'text-[11px]', style: 'color: var(--text-muted);' }, f.help) : null,
                props.error ? h('p', { class: 'text-[11px]', style: 'color:#EF4444;' }, props.error) : null,
            ]);
        };
    },
});

export default { components: { FieldControl } };
</script>
