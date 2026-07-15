<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { board } from '@/routes/projects';
import type { SidebarProject } from '@/types';

defineProps<{
    projects: SidebarProject[];
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();
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
                        <span class="truncate">{{ project.key }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge
                    :title="`${project.openCount} open · ${project.totalCount} total`"
                    class="text-muted-foreground tabular-nums"
                >
                    {{ project.openCount }}/{{ project.totalCount }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
