<script setup lang="ts">
import { BookMarked, BookText, Globe } from '@lucide/vue';
import { computed } from 'vue';
import type { ProjectLinks } from '@/types';

const props = defineProps<{
    links: ProjectLinks;
}>();

const items = computed(() =>
    [
        { href: props.links.docs, icon: BookText, label: 'Docs' },
        { href: props.links.readme, icon: BookMarked, label: 'README' },
        { href: props.links.production, icon: Globe, label: 'Production' },
    ].filter(
        (
            item,
        ): item is { href: string; icon: typeof BookText; label: string } =>
            item.href !== null,
    ),
);
</script>

<template>
    <div v-if="items.length" class="flex items-center gap-1">
        <a
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            target="_blank"
            rel="noopener noreferrer"
            :title="item.label"
            :aria-label="item.label"
            class="flex size-7 items-center justify-center rounded-md border text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
            <component :is="item.icon" class="size-4" />
        </a>
    </div>
</template>
