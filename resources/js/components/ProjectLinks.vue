<script setup lang="ts">
import { BookText, FolderGit2, Globe } from '@lucide/vue';
import { computed } from 'vue';
import type { ProjectLinks } from '@/types';

const MAX_REPOS = 4;

const props = defineProps<{
    links: ProjectLinks;
}>();

const hasAny = computed(
    () =>
        props.links.production !== null ||
        props.links.docs !== null ||
        props.links.repos.length > 0,
);

const visibleRepos = computed(() => props.links.repos.slice(0, MAX_REPOS));
const extraRepos = computed(() => props.links.repos.slice(MAX_REPOS));
const extraTitle = computed(() =>
    extraRepos.value.map((repo) => repo.name).join(', '),
);

const linkClass =
    'flex size-7 items-center justify-center rounded-md border text-muted-foreground transition-colors hover:bg-accent hover:text-foreground';
</script>

<template>
    <div v-if="hasAny" class="flex shrink-0 items-center gap-1">
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
            v-for="repo in visibleRepos"
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
        <span
            v-if="extraRepos.length"
            :title="extraTitle"
            class="flex h-7 items-center rounded-md px-1.5 text-xs font-medium text-muted-foreground tabular-nums"
        >
            +{{ extraRepos.length }}
        </span>
    </div>
</template>
