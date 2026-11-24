<script setup lang="ts">
import { Bold, Code, Italic, Link2, List, ListOrdered } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import MarkdownContent from '@/components/MarkdownContent.vue';

const { t } = useI18n();

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

const hasContent = computed(() => (props.modelValue ?? '').trim() !== '');

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

const tools = computed(() => [
    { icon: Bold, label: t('markdown.bold'), run: () => wrap('**') },
    { icon: Italic, label: t('markdown.italic'), run: () => wrap('_') },
    { icon: Code, label: t('markdown.code'), run: () => wrap('`') },
    {
        icon: List,
        label: t('markdown.bulletedList'),
        run: () => prefixLines(false),
    },
    {
        icon: ListOrdered,
        label: t('markdown.numberedList'),
        run: () => prefixLines(true),
    },
    { icon: Link2, label: t('markdown.link'), run: () => wrap('[', '](url)') },
]);
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
                    {{ $t('markdown.write') }}
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
                    {{ $t('markdown.preview') }}
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
        <div v-show="mode === 'preview'" class="min-h-24 px-3 py-2 text-sm">
            <MarkdownContent v-if="hasContent" :source="modelValue ?? ''" />
            <p v-else class="text-muted-foreground">
                {{ $t('markdown.nothingToPreview') }}
            </p>
        </div>
    </div>
</template>
