<script setup lang="ts">
import { BookText, FolderGit2, Globe } from '@lucide/vue';
import { computed } from 'vue';
import type { ProjectLinks } from '@/types';

const props = defineProps<{
    links: ProjectLinks;
}>();

const hasAny = computed(
    () =>
        props.links.production !== null ||
        props.links.docs !== null ||
        props.links.repos.length > 0,
);

const linkClass =
    'flex size-7 items-center justify-center rounded-md border text-muted-foreground transition-colors hover:bg-accent hover:text-foreground';
</script>

<template>
    <div v-if="hasAny" class="flex flex-wrap items-center gap-1">
        <a
            v-if="links.production"
            :href="links.production"
            target="_blank"
            rel="noopener noreferrer"
            title="Production"
            aria-label="Production"
            :class="linkClass"
        >
            <Globe class="size-4" />
        </a>
        <a
            v-if="links.docs"
            :href="links.docs"
            target="_blank"
            rel="noopener noreferrer"
            title="Docs"
            aria-label="Docs"
            :class="linkClass"
        >
            <BookText class="size-4" />
        </a>
        <a
            v-for="repo in links.repos"
            :key="repo.url"
            :href="repo.url"
            target="_blank"
            rel="noopener noreferrer"
            :title="repo.name"
            :aria-label="`Repository ${repo.name}`"
            :class="linkClass"
        >
            <FolderGit2 class="size-4" />
        </a>
    </div>
</template>
