<script setup lang="ts">
import { computed } from 'vue';
import {
    BookOpen, FolderOpen, Video, CheckSquare, FileText, ExternalLink,
} from 'lucide-vue-next';
import type { Citation } from '@/composables/useAiChat';

const props = defineProps<{ citations: Citation[] }>();

const iconFor = (type: string) => {
    switch (type) {
        case 'knowledge': return BookOpen;
        case 'document':  return FolderOpen;
        case 'meeting':   return Video;
        case 'task':      return CheckSquare;
        case 'report':    return FileText;
        default:          return BookOpen;
    }
};

const urlFor = (c: Citation): string | null => {
    switch (c.source_type) {
        case 'knowledge': return `/knowledge/${c.source_id}`;
        case 'document':  return `/documents/${c.source_id}`;
        case 'meeting':   return `/meetings/${c.source_id}`;
        case 'task':      return `/tasks/${c.source_id}`;
        case 'report':    return `/reports/${c.source_id}`;
        default:          return null;
    }
};

const items = computed(() => props.citations ?? []);
</script>

<template>
    <div v-if="items.length" class="mt-2 space-y-1">
        <p class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-muted);">
            [Sumber]
        </p>
        <component
            v-for="(c, idx) in items"
            :is="urlFor(c) ? 'a' : 'div'"
            :key="`${c.source_id}-${idx}`"
            :href="urlFor(c) ?? undefined"
            class="flex items-start gap-2 rounded-md px-2 py-1.5 text-xs transition-colors"
            :class="urlFor(c) ? 'hover:opacity-80 cursor-pointer' : ''"
            style="background: var(--bg-hover); color: var(--text-secondary);"
        >
            <component :is="iconFor(c.source_type)" :size="13" class="shrink-0 mt-0.5" style="color: #8B5CF6;" />
            <span class="flex-1 min-w-0">
                <span class="font-medium truncate block" style="color: var(--text-primary);">
                    [{{ idx + 1 }}] {{ c.title }}
                </span>
                <span v-if="c.excerpt" class="block truncate" style="color: var(--text-muted);">
                    {{ c.excerpt }}
                </span>
            </span>
            <ExternalLink v-if="urlFor(c)" :size="11" class="shrink-0 mt-0.5" style="color: var(--text-muted);" />
        </component>
    </div>
</template>
