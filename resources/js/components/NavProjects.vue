<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Kanban, Ticket } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { SidebarProject } from '@/types';

defineProps<{
    projects: SidebarProject[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup v-if="projects.length" class="px-2 py-0">
        <SidebarGroupLabel>Projects</SidebarGroupLabel>
        <SidebarMenu>
            <Collapsible
                v-for="project in projects"
                :key="project.id"
                as-child
                :default-open="isCurrentOrParentUrl(`/${project.key}/tickets`) || isCurrentOrParentUrl(`/${project.key}/board`)"
                class="group/collapsible"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton :tooltip="project.name">
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :style="{ backgroundColor: project.color }"
                            />
                            <span class="truncate">{{ project.key }}</span>
                            <ChevronRight
                                class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(`/${project.key}/tickets`)"
                                >
                                    <Link :href="`/${project.key}/tickets`">
                                        <Ticket />
                                        <span>Tickets</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(`/${project.key}/board`)"
                                >
                                    <Link :href="`/${project.key}/board`">
                                        <Kanban />
                                        <span>Board</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
