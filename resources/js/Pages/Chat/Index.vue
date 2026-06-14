<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Search, Plus, PenSquare, Send, Paperclip, Smile, Users, Hash,
    MessageSquare, Megaphone, Info, X, Pencil, Trash2, Check,
} from 'lucide-vue-next';
import { useChat, type ChatChannel, type ChatMessage } from '@/composables/useChat';

interface ActivePayload { channel: ChatChannel; messages: ChatMessage[] }

const props = defineProps<{
    dms: ChatChannel[];
    groups: ChatChannel[];
    activeChannel: ActivePayload | null;
    users: { id: string; name: string }[];
    channelTypes: string[];
}>();

const {
    channel, messages, members, sending, error,
    setActive, send, edit, remove, react, markRead, unsubscribe,
} = useChat();

const search = ref('');
const draft = ref('');
const showDetail = ref(false);
const showNewDm = ref(false);
const showNewChannel = ref(false);
const editingId = ref<string | null>(null);
const editDraft = ref('');
const messagesEl = ref<HTMLElement | null>(null);

const channelTypeOf = (c: ChatChannel) =>
    typeof c.channel_type === 'string' ? c.channel_type : c.channel_type.value;

const filteredDms = computed(() =>
    props.dms.filter(c => (c.name ?? '').toLowerCase().includes(search.value.toLowerCase())));
const filteredGroups = computed(() =>
    props.groups.filter(c => (c.name ?? '').toLowerCase().includes(search.value.toLowerCase())));

const activeId = computed(() => channel.value?.id ?? null);

const scrollToBottom = async () => {
    await nextTick();
    if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
};

watch(messages, scrollToBottom, { deep: true });

const openChannel = (c: ChatChannel) => {
    if (c.id === activeId.value) return;
    router.get('/chat', { channel: c.id }, {
        preserveState: false, preserveScroll: true, only: ['activeChannel', 'dms', 'groups'],
    });
};

const channelIcon = (c: ChatChannel) => {
    const t = channelTypeOf(c);
    if (t === 'announcement') return Megaphone;
    if (t === 'dm') return MessageSquare;
    return Hash;
};

const displayName = (c: ChatChannel) =>
    c.name ?? c.counterpart?.name ?? 'Direct Message';

const submit = async () => {
    const mentions = props.users
        .filter(u => draft.value.includes(`@${u.name}`))
        .map(u => u.id);
    await send(draft.value, { mentions });
    draft.value = '';
};

const startEdit = (m: ChatMessage) => {
    editingId.value = m.id;
    editDraft.value = m.content;
};
const saveEdit = async (m: ChatMessage) => {
    await edit(m, editDraft.value);
    editingId.value = null;
};

const reactionEntries = (m: ChatMessage) =>
    Object.entries(m.reactions ?? {});

const newDmTarget = ref('');
const startDm = () => {
    if (!newDmTarget.value) return;
    router.post('/chat/dm', { user_id: newDmTarget.value }, {
        onFinish: () => { showNewDm.value = false; newDmTarget.value = ''; },
    });
};

const newChannel = ref({ name: '', channel_type: 'group', member_ids: [] as string[] });
const createChannel = () => {
    if (!newChannel.value.name) return;
    router.post('/chat/channels', newChannel.value, {
        onFinish: () => {
            showNewChannel.value = false;
            newChannel.value = { name: '', channel_type: 'group', member_ids: [] };
        },
    });
};

onMounted(() => {
    setActive(props.activeChannel);
    if (props.activeChannel) {
        markRead();
        scrollToBottom();
    }
});

onBeforeUnmount(() => unsubscribe());
</script>

<template>
    <AppLayout>
        <div
            class="flex h-[calc(100vh-7rem)] rounded-xl overflow-hidden border"
            style="border-color: var(--border-color); background: var(--card-bg);"
        >
            <!-- ── Left: channel list (240px) ── -->
            <aside
                class="w-60 shrink-0 flex flex-col border-r"
                style="border-color: var(--border-color); background: var(--sidebar-bg);"
            >
                <div class="p-3 space-y-2">
                    <div class="relative">
                        <Search :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari percakapan…"
                            class="w-full pl-8 pr-2 py-1.5 text-sm rounded-lg outline-none"
                            style="background: var(--input-bg); color: var(--text-primary);"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="flex-1 flex items-center justify-center gap-1 py-1.5 text-xs font-medium rounded-lg text-white"
                            style="background: #3B82F6;"
                            @click="showNewDm = true"
                        >
                            <PenSquare :size="14" /> Pesan Baru
                        </button>
                        <button
                            class="flex items-center justify-center px-2 py-1.5 rounded-lg"
                            style="background: var(--input-bg); color: var(--text-secondary);"
                            title="Buat Channel"
                            @click="showNewChannel = true"
                        >
                            <Plus :size="16" />
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-2 pb-3">
                    <!-- DMs -->
                    <p class="px-2 mt-2 mb-1 text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-muted);">
                        Pesan Langsung
                    </p>
                    <button
                        v-for="c in filteredDms"
                        :key="c.id"
                        class="w-full flex items-center gap-2 px-2 py-2 rounded-lg text-left transition-colors"
                        :style="c.id === activeId ? 'background: var(--bg-tertiary);' : ''"
                        @click="openChannel(c)"
                    >
                        <component :is="channelIcon(c)" :size="16" style="color: var(--text-secondary);" />
                        <span class="flex-1 truncate text-sm" style="color: var(--text-primary);">{{ displayName(c) }}</span>
                        <span
                            v-if="c.unread_count > 0"
                            class="text-[10px] font-bold text-white rounded-full px-1.5 py-0.5"
                            style="background: #3B82F6;"
                        >{{ c.unread_count }}</span>
                    </button>
                    <p v-if="!filteredDms.length" class="px-2 py-1 text-xs" style="color: var(--text-muted);">Belum ada DM</p>

                    <!-- Channels -->
                    <p class="px-2 mt-4 mb-1 text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-muted);">
                        Channel
                    </p>
                    <button
                        v-for="c in filteredGroups"
                        :key="c.id"
                        class="w-full flex items-center gap-2 px-2 py-2 rounded-lg text-left transition-colors"
                        :style="c.id === activeId ? 'background: var(--bg-tertiary);' : ''"
                        @click="openChannel(c)"
                    >
                        <component :is="channelIcon(c)" :size="16" style="color: var(--text-secondary);" />
                        <div class="flex-1 min-w-0">
                            <span class="block truncate text-sm" style="color: var(--text-primary);">{{ displayName(c) }}</span>
                            <span v-if="c.last_message" class="block truncate text-xs" style="color: var(--text-muted);">
                                {{ c.last_message.content }}
                            </span>
                        </div>
                        <span
                            v-if="c.unread_count > 0"
                            class="text-[10px] font-bold text-white rounded-full px-1.5 py-0.5"
                            style="background: #3B82F6;"
                        >{{ c.unread_count }}</span>
                    </button>
                    <p v-if="!filteredGroups.length" class="px-2 py-1 text-xs" style="color: var(--text-muted);">Belum ada channel</p>
                </div>
            </aside>

            <!-- ── Center: conversation ── -->
            <section class="flex-1 flex flex-col min-w-0">
                <template v-if="channel">
                    <!-- Header -->
                    <header
                        class="flex items-center gap-3 px-4 py-3 border-b"
                        style="border-color: var(--border-color);"
                    >
                        <component :is="channelIcon(channel)" :size="18" style="color: var(--text-secondary);" />
                        <div class="flex-1 min-w-0">
                            <h2 class="font-semibold truncate" style="color: var(--text-primary);">{{ displayName(channel) }}</h2>
                            <p class="text-xs" style="color: var(--text-muted);">{{ channel.member_count }} anggota</p>
                        </div>
                        <button
                            class="p-1.5 rounded-lg"
                            style="color: var(--text-secondary);"
                            title="Info channel"
                            @click="showDetail = !showDetail"
                        >
                            <Info :size="18" />
                        </button>
                    </header>

                    <!-- Messages -->
                    <div ref="messagesEl" class="flex-1 overflow-y-auto p-4 space-y-4">
                        <p v-if="!messages.length" class="text-center text-sm py-8" style="color: var(--text-muted);">
                            Belum ada pesan. Mulai percakapan.
                        </p>

                        <div
                            v-for="m in messages"
                            :key="m.id"
                            :class="m.is_mine ? 'flex justify-end' : 'flex justify-start'"
                        >
                            <div class="max-w-[75%] group">
                                <p
                                    v-if="!m.is_mine"
                                    class="text-xs font-medium mb-0.5 px-1"
                                    style="color: var(--text-secondary);"
                                >{{ m.sender?.name }}</p>

                                <div
                                    class="rounded-xl px-3.5 py-2.5 relative"
                                    :class="m.is_mine ? 'rounded-tr-sm' : 'rounded-tl-sm'"
                                    :style="m.is_mine
                                        ? 'background: #3B82F6; color: #FFFFFF;'
                                        : 'background: var(--bg-tertiary); color: var(--text-primary);'"
                                >
                                    <!-- Edit mode -->
                                    <div v-if="editingId === m.id" class="flex items-center gap-2">
                                        <input
                                            v-model="editDraft"
                                            class="flex-1 px-2 py-1 text-sm rounded outline-none text-slate-900"
                                            @keyup.enter="saveEdit(m)"
                                        />
                                        <button @click="saveEdit(m)"><Check :size="16" /></button>
                                        <button @click="editingId = null"><X :size="16" /></button>
                                    </div>
                                    <template v-else>
                                        <p class="text-sm leading-relaxed whitespace-pre-wrap break-words">{{ m.content }}</p>
                                        <span v-if="m.edited_at" class="text-[10px] opacity-70">(disunting)</span>
                                    </template>

                                    <!-- Hover actions -->
                                    <div
                                        v-if="m.is_mine && editingId !== m.id"
                                        class="absolute -top-2 right-1 hidden group-hover:flex gap-1 rounded-lg px-1 py-0.5"
                                        style="background: var(--card-bg); border: 1px solid var(--border-color);"
                                    >
                                        <button style="color: var(--text-secondary);" @click="startEdit(m)"><Pencil :size="12" /></button>
                                        <button style="color: #EF4444;" @click="remove(m)"><Trash2 :size="12" /></button>
                                    </div>
                                </div>

                                <!-- Reactions -->
                                <div class="flex items-center gap-1 mt-1 px-1">
                                    <button
                                        v-for="[emoji, ids] in reactionEntries(m)"
                                        :key="emoji"
                                        class="text-xs rounded-full px-1.5 py-0.5"
                                        style="background: var(--bg-tertiary); color: var(--text-primary);"
                                        @click="react(m, emoji)"
                                    >{{ emoji }} {{ ids.length }}</button>
                                    <button
                                        class="opacity-0 group-hover:opacity-100 transition-opacity text-xs"
                                        style="color: var(--text-muted);"
                                        @click="react(m, '👍')"
                                    ><Smile :size="14" /></button>
                                    <span v-if="m.reply_count" class="text-[11px] ml-1" style="color: #3B82F6;">
                                        {{ m.reply_count }} balasan
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="error" class="px-4 py-1 text-xs" style="color: #EF4444;">{{ error }}</p>

                    <!-- Input -->
                    <footer class="p-3 border-t" style="border-color: var(--border-color);">
                        <div
                            class="flex items-end gap-2 rounded-xl px-3 py-2"
                            style="background: var(--input-bg);"
                        >
                            <button style="color: var(--text-muted);" title="Lampirkan"><Paperclip :size="18" /></button>
                            <textarea
                                v-model="draft"
                                rows="1"
                                placeholder="Tulis pesan… (gunakan @ untuk menyebut)"
                                class="flex-1 bg-transparent resize-none outline-none text-sm py-1"
                                style="color: var(--text-primary);"
                                @keydown.enter.exact.prevent="submit"
                            />
                            <button style="color: var(--text-muted);" title="Emoji"><Smile :size="18" /></button>
                            <button
                                class="p-1.5 rounded-lg text-white disabled:opacity-50"
                                style="background: #3B82F6;"
                                :disabled="sending || !draft.trim()"
                                @click="submit"
                            ><Send :size="16" /></button>
                        </div>
                    </footer>
                </template>

                <!-- Empty state -->
                <div v-else class="flex-1 flex flex-col items-center justify-center gap-3">
                    <div class="rounded-full p-4" style="background: var(--bg-tertiary);">
                        <MessageSquare :size="32" style="color: var(--text-muted);" />
                    </div>
                    <p class="text-sm" style="color: var(--text-muted);">Pilih percakapan atau mulai yang baru.</p>
                </div>
            </section>

            <!-- ── Right: detail panel (toggle) ── -->
            <aside
                v-if="showDetail && channel"
                class="w-72 shrink-0 border-l flex flex-col"
                style="border-color: var(--border-color); background: var(--sidebar-bg);"
            >
                <div class="flex items-center justify-between p-3 border-b" style="border-color: var(--border-color);">
                    <h3 class="font-semibold text-sm" style="color: var(--text-primary);">Info Channel</h3>
                    <button style="color: var(--text-secondary);" @click="showDetail = false"><X :size="16" /></button>
                </div>
                <div class="p-4 space-y-4 overflow-y-auto">
                    <div>
                        <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-muted);">Nama</p>
                        <p class="text-sm" style="color: var(--text-primary);">{{ displayName(channel) }}</p>
                    </div>
                    <div v-if="channel.description">
                        <p class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-muted);">Deskripsi</p>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ channel.description }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider mb-2 flex items-center gap-1" style="color: var(--text-muted);">
                            <Users :size="12" /> Anggota ({{ members.length }})
                        </p>
                        <ul class="space-y-1">
                            <li
                                v-for="mem in members"
                                :key="mem.id"
                                class="flex items-center gap-2 text-sm"
                                style="color: var(--text-primary);"
                            >
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-semibold text-white" style="background: #3B82F6;">
                                    {{ (mem.name ?? '?').charAt(0).toUpperCase() }}
                                </span>
                                <span class="flex-1 truncate">{{ mem.name }}</span>
                                <span class="text-[10px]" style="color: var(--text-muted);">{{ mem.role }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>

        <!-- New DM modal -->
        <div v-if="showNewDm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showNewDm = false">
            <div class="w-96 rounded-xl p-5 space-y-4" style="background: var(--card-bg);">
                <h3 class="font-semibold" style="color: var(--text-primary);">Pesan Baru</h3>
                <select v-model="newDmTarget" class="w-full px-3 py-2 rounded-lg text-sm outline-none" style="background: var(--input-bg); color: var(--text-primary);">
                    <option value="">Pilih pengguna…</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <div class="flex justify-end gap-2">
                    <button class="px-3 py-1.5 text-sm rounded-lg" style="color: var(--text-secondary);" @click="showNewDm = false">Batal</button>
                    <button class="px-3 py-1.5 text-sm rounded-lg text-white" style="background: #3B82F6;" @click="startDm">Mulai</button>
                </div>
            </div>
        </div>

        <!-- New Channel modal -->
        <div v-if="showNewChannel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showNewChannel = false">
            <div class="w-96 rounded-xl p-5 space-y-3" style="background: var(--card-bg);">
                <h3 class="font-semibold" style="color: var(--text-primary);">Buat Channel</h3>
                <input v-model="newChannel.name" placeholder="Nama channel" class="w-full px-3 py-2 rounded-lg text-sm outline-none" style="background: var(--input-bg); color: var(--text-primary);" />
                <select v-model="newChannel.channel_type" class="w-full px-3 py-2 rounded-lg text-sm outline-none" style="background: var(--input-bg); color: var(--text-primary);">
                    <option value="group">Grup</option>
                    <option value="team_channel">Channel Tim</option>
                    <option value="announcement">Pengumuman</option>
                </select>
                <div>
                    <p class="text-xs mb-1" style="color: var(--text-muted);">Anggota</p>
                    <div class="max-h-32 overflow-y-auto space-y-1">
                        <label v-for="u in users" :key="u.id" class="flex items-center gap-2 text-sm" style="color: var(--text-primary);">
                            <input type="checkbox" :value="u.id" v-model="newChannel.member_ids" />
                            {{ u.name }}
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button class="px-3 py-1.5 text-sm rounded-lg" style="color: var(--text-secondary);" @click="showNewChannel = false">Batal</button>
                    <button class="px-3 py-1.5 text-sm rounded-lg text-white" style="background: #3B82F6;" @click="createChannel">Buat</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
