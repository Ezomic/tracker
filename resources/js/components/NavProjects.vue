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

const statuses: {
    key: keyof SidebarProjectCounts;
    label: string;
    dot: string;
}[] = [
    { key: 'backlog', label: 'Backlog', dot: 'bg-muted-foreground/50' },
    { key: 'in_progress', label: 'In progress', dot: 'bg-primary' },
    { key: 'in_review', label: 'In review', dot: 'bg-sky-500' },
    { key: 'done', label: 'Done', dot: 'bg-emerald-500' },
];

function countsTitle(counts: SidebarProjectCounts): string {
    return statuses
        .map((status) => `${counts[status.key]} ${status.label.toLowerCase()}`)
        .join(' · ');
}
</script>

<template>
    <SidebarGroup v-if="projects.length" class="px-2 py-0">
        <SidebarGroupLabel>Favorites</SidebarGroupLabel>
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
                            class="ml-auto flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums group-data-[collapsible=icon]:hidden"
                        >
                            <span
                                v-for="status in statuses"
                                v-show="project.counts[status.key] > 0"
                                :key="status.key"
                                class="flex items-center gap-1"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="status.dot"
                                />
                                {{ project.counts[status.key] }}
                            </span>
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
