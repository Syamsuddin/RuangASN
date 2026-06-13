<script setup lang="ts">
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import {
    Bold, Italic, Strikethrough, Heading1, Heading2, Heading3,
    List, ListOrdered, Quote, Code, Link2, Undo2, Redo2,
} from 'lucide-vue-next';

interface Props {
    modelValue: string;
    placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Mulai menulis artikel...',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Placeholder.configure({ placeholder: props.placeholder }),
        Link.configure({ openOnClick: false, HTMLAttributes: { rel: 'noopener noreferrer' } }),
    ],
    onUpdate: ({ editor: e }) => {
        emit('update:modelValue', e.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const setLink = () => {
    if (!editor.value) return;
    const url = window.prompt('URL:', editor.value.getAttributes('link').href ?? '');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
};

type ToolbarBtn = {
    label: string;
    icon: typeof Bold;
    action: () => void;
    isActive: () => boolean;
};

const toolbarButtons = (): ToolbarBtn[] => [
    {
        label: 'Bold',
        icon: Bold,
        action: () => editor.value?.chain().focus().toggleBold().run(),
        isActive: () => editor.value?.isActive('bold') ?? false,
    },
    {
        label: 'Italic',
        icon: Italic,
        action: () => editor.value?.chain().focus().toggleItalic().run(),
        isActive: () => editor.value?.isActive('italic') ?? false,
    },
    {
        label: 'Strikethrough',
        icon: Strikethrough,
        action: () => editor.value?.chain().focus().toggleStrike().run(),
        isActive: () => editor.value?.isActive('strike') ?? false,
    },
    {
        label: 'Heading 1',
        icon: Heading1,
        action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run(),
        isActive: () => editor.value?.isActive('heading', { level: 1 }) ?? false,
    },
    {
        label: 'Heading 2',
        icon: Heading2,
        action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        isActive: () => editor.value?.isActive('heading', { level: 2 }) ?? false,
    },
    {
        label: 'Heading 3',
        icon: Heading3,
        action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
        isActive: () => editor.value?.isActive('heading', { level: 3 }) ?? false,
    },
    {
        label: 'Bullet List',
        icon: List,
        action: () => editor.value?.chain().focus().toggleBulletList().run(),
        isActive: () => editor.value?.isActive('bulletList') ?? false,
    },
    {
        label: 'Ordered List',
        icon: ListOrdered,
        action: () => editor.value?.chain().focus().toggleOrderedList().run(),
        isActive: () => editor.value?.isActive('orderedList') ?? false,
    },
    {
        label: 'Blockquote',
        icon: Quote,
        action: () => editor.value?.chain().focus().toggleBlockquote().run(),
        isActive: () => editor.value?.isActive('blockquote') ?? false,
    },
    {
        label: 'Code Block',
        icon: Code,
        action: () => editor.value?.chain().focus().toggleCodeBlock().run(),
        isActive: () => editor.value?.isActive('codeBlock') ?? false,
    },
    {
        label: 'Link',
        icon: Link2,
        action: setLink,
        isActive: () => editor.value?.isActive('link') ?? false,
    },
];
</script>

<template>
    <div class="rounded-lg overflow-hidden" style="border: 1px solid var(--border-color);">
        <!-- Toolbar -->
        <div
            class="flex items-center gap-0.5 flex-wrap p-2 border-b"
            style="background: var(--bg-tertiary); border-color: var(--border-color);"
        >
            <button
                v-for="btn in toolbarButtons()"
                :key="btn.label"
                type="button"
                :title="btn.label"
                @click="btn.action()"
                class="p-1.5 rounded-md transition-colors"
                :style="btn.isActive()
                    ? 'background: #3B82F6; color: white;'
                    : 'background: transparent; color: var(--text-secondary);'"
            >
                <component :is="btn.icon" :size="15" />
            </button>

            <div class="w-px h-5 mx-1" style="background: var(--border-color);" />

            <!-- Undo -->
            <button
                type="button"
                title="Undo"
                @click="editor?.chain().focus().undo().run()"
                :disabled="!editor?.can().undo()"
                class="p-1.5 rounded-md transition-colors"
                style="background: transparent; color: var(--text-secondary);"
            >
                <Undo2 :size="15" />
            </button>

            <!-- Redo -->
            <button
                type="button"
                title="Redo"
                @click="editor?.chain().focus().redo().run()"
                :disabled="!editor?.can().redo()"
                class="p-1.5 rounded-md transition-colors"
                style="background: transparent; color: var(--text-secondary);"
            >
                <Redo2 :size="15" />
            </button>
        </div>

        <!-- Editor area -->
        <EditorContent
            :editor="editor"
            class="prose-rte min-h-[300px] p-4 outline-none"
            style="background: var(--input-bg); color: var(--text-primary);"
        />
    </div>
</template>

<style>
/* TipTap placeholder */
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: var(--text-muted);
    pointer-events: none;
    height: 0;
}

/* Focus outline off on ProseMirror */
.tiptap { outline: none; }
</style>
