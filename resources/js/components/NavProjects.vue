<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { board } from '@/routes/projects';
import type { SidebarProject, SidebarProjectCounts } from '@/types';

defineProps<{
    projects: SidebarProject[];
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();

function countsLabel(counts: SidebarProjectCounts): string {
    return `${counts.backlog}/${counts.in_progress}/${counts.in_review}/${counts.done}`;
}

function countsTitle(counts: SidebarProjectCounts): string {
    return (
        `${counts.backlog} backlog · ${counts.in_progress} in progress · ` +
        `${counts.in_review} in review · ${counts.done} done`
    );
}
</script>

<template>
    <SidebarGroup v-if="projects.length" class="px-2 py-0">
        <SidebarGroupLabel>Projects</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="project in projects" :key="project.id">
                <SidebarMenuButton
                    as-child
                    :tooltip="project.name"
                    :is-active="
                        isCurrentOrParentUrl(`/${project.key}/board`) ||
                        isCurrentOrParentUrl(`/${project.key}/tickets`)
                    "
                >
                    <Link :href="board(project.key)">
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :style="{ backgroundColor: project.color }"
                        />
                        <span class="truncate">{{ project.name }}</span>
                        <span
                            :title="countsTitle(project.counts)"
                            class="ml-auto font-mono text-xs text-muted-foreground tabular-nums group-data-[collapsible=icon]:hidden"
                        >
                            {{ countsLabel(project.counts) }}
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
