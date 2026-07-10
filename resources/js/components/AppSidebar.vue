<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Kanban, LayoutGrid, Plus, Search, Ticket } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavProjects from '@/components/NavProjects.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { dashboard } from '@/routes';
import { board as issuesBoard, index as issuesIndex } from '@/routes/issues';
import type { NavItem, SidebarProject } from '@/types';

const { show: showCommandPalette } = useCommandPalette();
const page = usePage();
const projects = computed<SidebarProject[]>(() => page.props.projects ?? []);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'All issues',
        href: issuesIndex(),
        icon: Ticket,
    },
    {
        title: 'Board',
        href: issuesBoard(),
        icon: Kanban,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        class="bg-primary font-medium text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground"
                    >
                        <Link :href="issuesIndex()">
                            <Plus />
                            <span>New issue</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="text-muted-foreground"
                        @click="showCommandPalette()"
                    >
                        <Search />
                        <span>Search</span>
                        <kbd
                            class="ml-auto text-xs tracking-widest text-muted-foreground group-data-[collapsible=icon]:hidden"
                        >
                            ⌘K
                        </kbd>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavProjects :projects="projects" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
