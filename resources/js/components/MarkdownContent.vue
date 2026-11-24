<script setup lang="ts">
import { computed } from 'vue';
import { renderMarkdown } from '@/lib/markdown';

const props = defineProps<{ source: string }>();

const html = computed(() => renderMarkdown(props.source));
</script>

<template>
    <div class="markdown-content" v-html="html" />
</template>

<style scoped>
.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3) {
    font-weight: 600;
    line-height: 1.3;
    margin: 0.75em 0 0.35em;
}
.markdown-content :deep(h1) {
    font-size: 1.35em;
}
.markdown-content :deep(h2) {
    font-size: 1.2em;
}
.markdown-content :deep(h3) {
    font-size: 1.05em;
}
.markdown-content :deep(p) {
    margin: 0.5em 0;
}
.markdown-content :deep(ul),
.markdown-content :deep(ol) {
    margin: 0.5em 0;
    padding-left: 1.4em;
}
.markdown-content :deep(ul) {
    list-style: disc;
}
.markdown-content :deep(ol) {
    list-style: decimal;
}
.markdown-content :deep(a) {
    color: var(--color-primary, currentColor);
    text-decoration: underline;
}
.markdown-content :deep(code) {
    border-radius: 0.25rem;
    background: var(--color-muted);
    padding: 0.1em 0.35em;
    font-family: ui-monospace, monospace;
    font-size: 0.9em;
}
.markdown-content :deep(pre) {
    overflow-x: auto;
    border-radius: 0.375rem;
    background: var(--color-muted);
    padding: 0.75em;
    margin: 0.5em 0;
}
.markdown-content :deep(pre code) {
    background: transparent;
    padding: 0;
}
.markdown-content :deep(blockquote) {
    border-left: 3px solid var(--color-border);
    padding-left: 0.75em;
    color: var(--color-muted-foreground);
    margin: 0.5em 0;
}
.markdown-content :deep(:first-child) {
    margin-top: 0;
}
.markdown-content :deep(:last-child) {
    margin-bottom: 0;
}
</style>
