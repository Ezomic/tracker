<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import { board, browse, favorite } from '@/routes/projects';
import type { ProjectListItem } from '@/types';

defineProps<{
    projects: ProjectListItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: browse() }],
    },
});

function toggleFavorite(project: ProjectListItem) {
    router.patch(
        favorite(project.key).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-col gap-4 p-4">
        <Heading
            variant="small"
            title="Projects"
            description="Browse every project and star the ones you want in the sidebar"
        />

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-for="project in projects"
                :key="project.id"
                class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="shrink-0 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent"
                    :aria-label="project.isFavorite ? 'Unfavorite' : 'Favorite'"
                    @click="toggleFavorite(project)"
                >
                    <Star
                        class="size-4"
                        :class="
                            project.isFavorite
                                ? 'fill-amber-400 text-amber-400'
                                : ''
                        "
                    />
                </button>
                <span
                    class="size-3 shrink-0 rounded-full"
                    :style="{ backgroundColor: project.color }"
                />
                <Link
                    :href="board(project.key)"
                    class="flex min-w-0 flex-1 items-center gap-3 hover:underline"
                >
                    <span class="w-24 shrink-0 font-mono text-sm">
                        {{ project.key }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm">
                        {{ project.name }}
                    </span>
                </Link>
                <div class="flex w-24 justify-end">
                    <ProjectLinks :links="project.links" />
                </div>
                <span
                    class="w-20 text-right text-xs text-muted-foreground tabular-nums"
                >
                    {{ project.openCount }} open
                </span>
            </div>
        </div>
    </div>
</template>
