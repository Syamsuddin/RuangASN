<script setup lang="ts">
import { computed } from 'vue';
import CitationList from './CitationList.vue';
import ProposedActionCard from './ProposedActionCard.vue';
import type { AiMessage } from '@/composables/useAiChat';

const props = defineProps<{
    message: AiMessage;
    loading?: boolean;
}>();

const emit = defineEmits<{
    confirm: [message: AiMessage, index: number];
    reject: [message: AiMessage, index: number];
}>();

const isUser = computed(() => props.message.role === 'user');

// Assistant content carries an "[AI Generated] " label prefix from the
// backend; strip it for the body and render it as a subtle badge instead.
const AI_PREFIX = '[AI Generated] ';
const body = computed(() => {
    if (isUser.value) return props.message.content;
    return props.message.content.startsWith(AI_PREFIX)
        ? props.message.content.slice(AI_PREFIX.length)
        : props.message.content;
});

// Drafts (minutes/report) come back as HTML; plain replies as text. Render
// HTML only when it looks like markup (the deterministic drafts use tags).
const looksLikeHtml = computed(() => /<\/?[a-z][\s\S]*>/i.test(body.value));
</script>

<template>
    <div :class="isUser ? 'flex justify-end' : 'flex justify-start'">
        <div
            class="max-w-[85%] rounded-xl px-3.5 py-2.5"
            :class="isUser ? 'rounded-tr-sm' : 'rounded-tl-sm'"
            :style="isUser
                ? 'background: #3B82F6; color: #FFFFFF;'
                : 'background: var(--bg-tertiary); color: var(--text-primary);'"
        >
            <!-- AI Generated subtle label -->
            <p
                v-if="!isUser"
                class="text-[10px] font-semibold uppercase tracking-wider mb-1"
                style="color: #8B5CF6;"
            >[AI Generated]</p>

            <div
                v-if="!isUser && looksLikeHtml"
                class="prose-rte text-sm leading-relaxed"
                v-html="body"
            />
            <p v-else class="text-sm leading-relaxed whitespace-pre-wrap">{{ body }}</p>

            <!-- Citations -->
            <CitationList v-if="!isUser" :citations="message.citations" />

            <!-- Proposed actions -->
            <template v-if="!isUser">
                <ProposedActionCard
                    v-for="(action, idx) in message.proposed_actions"
                    :key="idx"
                    :message="message"
                    :action="action"
                    :index="idx"
                    :loading="loading"
                    @confirm="(i) => emit('confirm', message, i)"
                    @reject="(i) => emit('reject', message, i)"
                />
            </template>
        </div>
    </div>
</template>
