<script setup lang="ts">
import { Bold, Code, Italic, Link2, List, ListOrdered } from '@lucide/vue';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { computed, nextTick, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        name?: string;
        placeholder?: string;
        rows?: number;
    }>(),
    { rows: 6, placeholder: '', name: undefined },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const mode = ref<'write' | 'preview'>('write');
const textarea = ref<HTMLTextAreaElement | null>(null);

const rendered = computed(() =>
    DOMPurify.sanitize(
        marked.parse(props.modelValue ?? '', { async: false }) as string,
    ),
);

function grow() {
    const el = textarea.value;

    if (!el) {
        return;
    }

    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

function onInput(event: Event) {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
    grow();
}

/** Wrap the current selection with markers (e.g. **bold**), keeping it selected. */
function wrap(before: string, after = before) {
    const el = textarea.value;

    if (!el) {
        return;
    }

    const { selectionStart: start, selectionEnd: end } = el;
    const value = props.modelValue ?? '';
    const next =
        value.slice(0, start) +
        before +
        value.slice(start, end) +
        after +
        value.slice(end);

    emit('update:modelValue', next);

    nextTick(() => {
        el.focus();
        el.setSelectionRange(start + before.length, end + before.length);
        grow();
    });
}

/** Prefix each line in the selection (bulleted or numbered). */
function prefixLines(ordered: boolean) {
    const el = textarea.value;

    if (!el) {
        return;
    }

    const value = props.modelValue ?? '';
    const lineStart = value.lastIndexOf('\n', el.selectionStart - 1) + 1;
    const block = value.slice(lineStart, el.selectionEnd);
    const replaced = block
        .split('\n')
        .map((line, i) => (ordered ? `${i + 1}. ${line}` : `- ${line}`))
        .join('\n');
    const next =
        value.slice(0, lineStart) + replaced + value.slice(el.selectionEnd);

    emit('update:modelValue', next);
    nextTick(() => {
        el.focus();
        grow();
    });
}

const tools = [
    { icon: Bold, label: 'Bold', run: () => wrap('**') },
    { icon: Italic, label: 'Italic', run: () => wrap('_') },
    { icon: Code, label: 'Code', run: () => wrap('`') },
    { icon: List, label: 'Bulleted list', run: () => prefixLines(false) },
    { icon: ListOrdered, label: 'Numbered list', run: () => prefixLines(true) },
    { icon: Link2, label: 'Link', run: () => wrap('[', '](url)') },
];
</script>

<template>
    <div
        class="rounded-md border border-input focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
    >
        <div
            class="flex items-center justify-between border-b border-input px-1.5 py-1"
        >
            <div v-show="mode === 'write'" class="flex items-center gap-0.5">
                <button
                    v-for="tool in tools"
                    :key="tool.label"
                    type="button"
                    :title="tool.label"
                    class="inline-flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground"
                    @mousedown.prevent="tool.run"
                >
                    <component :is="tool.icon" class="size-4" />
                    <span class="sr-only">{{ tool.label }}</span>
                </button>
            </div>
            <div v-show="mode === 'preview'" />

            <div class="flex items-center gap-1 text-xs">
                <button
                    type="button"
                    class="rounded px-2 py-1"
                    :class="
                        mode === 'write'
                            ? 'bg-muted font-medium text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="mode = 'write'"
                >
                    Write
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1"
                    :class="
                        mode === 'preview'
                            ? 'bg-muted font-medium text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="mode = 'preview'"
                >
                    Preview
                </button>
            </div>
        </div>

        <textarea
            v-show="mode === 'write'"
            ref="textarea"
            :name="name"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            class="block w-full resize-none bg-transparent px-3 py-2 text-sm outline-none"
            @input="onInput"
            @vue:mounted="grow"
        />
        <div
            v-show="mode === 'preview'"
            class="markdown-preview min-h-24 px-3 py-2 text-sm"
        >
            <div v-if="rendered" v-html="rendered" />
            <p v-else class="text-muted-foreground">Nothing to preview.</p>
        </div>
    </div>
</template>

<style scoped>
.markdown-preview :deep(h1),
.markdown-preview :deep(h2),
.markdown-preview :deep(h3) {
    font-weight: 600;
    line-height: 1.3;
    margin: 0.75em 0 0.35em;
}
.markdown-preview :deep(h1) {
    font-size: 1.35em;
}
.markdown-preview :deep(h2) {
    font-size: 1.2em;
}
.markdown-preview :deep(h3) {
    font-size: 1.05em;
}
.markdown-preview :deep(p) {
    margin: 0.5em 0;
}
.markdown-preview :deep(ul),
.markdown-preview :deep(ol) {
    margin: 0.5em 0;
    padding-left: 1.4em;
}
.markdown-preview :deep(ul) {
    list-style: disc;
}
.markdown-preview :deep(ol) {
    list-style: decimal;
}
.markdown-preview :deep(a) {
    color: var(--color-primary, currentColor);
    text-decoration: underline;
}
.markdown-preview :deep(code) {
    border-radius: 0.25rem;
    background: var(--color-muted);
    padding: 0.1em 0.35em;
    font-family: ui-monospace, monospace;
    font-size: 0.9em;
}
.markdown-preview :deep(pre) {
    overflow-x: auto;
    border-radius: 0.375rem;
    background: var(--color-muted);
    padding: 0.75em;
    margin: 0.5em 0;
}
.markdown-preview :deep(pre code) {
    background: transparent;
    padding: 0;
}
.markdown-preview :deep(blockquote) {
    border-left: 3px solid var(--color-border);
    padding-left: 0.75em;
    color: var(--color-muted-foreground);
    margin: 0.5em 0;
}
.markdown-preview :deep(:first-child) {
    margin-top: 0;
}
.markdown-preview :deep(:last-child) {
    margin-bottom: 0;
}
</style>
