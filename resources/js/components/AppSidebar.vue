<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    FolderKanban,
    Kanban,
    LayoutGrid,
    Plus,
    Search,
    Ticket,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavProjects from '@/components/NavProjects.vue';
import NavUser from '@/components/NavUser.vue';
import NewIssueDialog from '@/components/NewIssueDialog.vue';
import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue';
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
import { index as projectsIndex } from '@/routes/projects';
import type { NavItem, Project, SidebarProject } from '@/types';

const { show: showCommandPalette } = useCommandPalette();
const { t } = useI18n();
const page = usePage();
const projects = computed<SidebarProject[]>(
    () => page.props.sidebarProjects ?? [],
);

const newIssueOpen = ref(false);
const newIssueProjects = computed<Pick<Project, 'id' | 'key' | 'name'>[]>(
    () => page.props.newIssueProjects ?? [],
);
const currentProjectId = computed<number | null>(
    () => page.props.currentProjectId ?? null,
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: t('nav.allIssues'),
        href: issuesIndex(),
        icon: Ticket,
    },
    {
        title: t('nav.board'),
        href: issuesBoard(),
        icon: Kanban,
    },
    {
        title: t('nav.projects'),
        href: projectsIndex(),
        icon: FolderKanban,
    },
]);
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
                    <OrganizationSwitcher />
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="bg-primary font-medium text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground"
                        @click="newIssueOpen = true"
                    >
                        <Plus />
                        <span>{{ $t('nav.newIssue') }}</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="text-muted-foreground"
                        @click="showCommandPalette()"
                    >
                        <Search />
                        <span>{{ $t('nav.search') }}</span>
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

    <NewIssueDialog
        v-model:open="newIssueOpen"
        :projects="newIssueProjects"
        :default-project-id="currentProjectId"
    />

    <slot />
</template>
