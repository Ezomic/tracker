<script setup lang="ts">
import { ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import NavProjectLink from '@/components/NavProjectLink.vue';
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
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { visibleCategories } from '@/lib/categoryTree';
import { projectCount, sidebarSections } from '@/lib/sidebarProjects';
import type { SidebarCategories } from '@/types';

const props = defineProps<{
    categories: SidebarCategories;
}>();

const STORAGE_KEY = 'sidebar:expandedCategories';
const SHOW_ALL_KEY = 'sidebar:showAllProjects';

// Reserved expand-state key for the synthetic "Uncategorized" section. It is not
// a real category (real ids start at 1), just a collapsible view of projects
// with no category.
const UNCATEGORIZED_KEY = 0;

function loadExpanded(): Record<number, boolean> {
    if (typeof localStorage === 'undefined') {
        return {};
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as Record<number, boolean>) : {};
    } catch {
        return {};
    }
}

const expanded = ref<Record<number, boolean>>(loadExpanded());

watch(
    expanded,
    (value) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        } catch {
            // Ignore storage failures (private mode, quota); state is transient.
        }
    },
    { deep: true },
);

function loadShowAll(): boolean {
    try {
        return localStorage.getItem(SHOW_ALL_KEY) === 'true';
    } catch {
        return false;
    }
}

const showAll = ref(loadShowAll());

function toggleShowAll(): void {
    showAll.value = !showAll.value;

    try {
        localStorage.setItem(SHOW_ALL_KEY, String(showAll.value));
    } catch {
        // Storage can be blocked; the choice then lasts until reload.
    }
}

function setOpen(id: number, open: boolean): void {
    expanded.value = { ...expanded.value, [id]: open };
}

function indent(depth: number): Record<string, string> {
    return { paddingLeft: `${0.5 + depth * 0.75}rem` };
}

// The server always puts the project being viewed at the top of Recent, so it
// stays visible without expanding its category.
const sections = computed(() =>
    sidebarSections(props.categories, showAll.value),
);

const total = computed(() => projectCount(props.categories));

// Hide any category whose ancestor chain isn't fully expanded, so collapsing a
// parent hides its whole subtree (the tree is rendered as a flat list).
const visibleTree = computed(() =>
    visibleCategories(sections.value.tree, expanded.value),
);
</script>

<template>
    <SidebarGroup
        v-if="sections.recent.length"
        class="px-2 py-0 group-data-[collapsible=icon]:hidden"
    >
        <SidebarGroupLabel>{{ $t('nav.recent') }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuSub class="mx-0 translate-x-0 border-l-0 px-0">
                <SidebarMenuSubItem
                    v-for="project in sections.recent"
                    :key="project.id"
                >
                    <NavProjectLink :project="project" />
                </SidebarMenuSubItem>
            </SidebarMenuSub>
        </SidebarMenu>
    </SidebarGroup>

    <SidebarGroup class="px-2 py-0 group-data-[collapsible=icon]:hidden">
        <SidebarGroupLabel>{{ $t('nav.projects') }}</SidebarGroupLabel>
        <SidebarMenu>
            <Collapsible
                v-for="category in visibleTree"
                :key="category.id"
                as-child
                :open="expanded[category.id] ?? false"
                @update:open="(open: boolean) => setOpen(category.id, open)"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :style="indent(category.depth)"
                            :tooltip="category.name"
                        >
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                :class="{
                                    'rotate-90': expanded[category.id],
                                }"
                            />
                            <span class="truncate">{{ category.name }}</span>
                            <span
                                v-if="category.projects.length"
                                class="ml-auto text-xs text-muted-foreground tabular-nums"
                            >
                                {{ category.projects.length }}
                            </span>
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub v-if="category.projects.length">
                            <SidebarMenuSubItem
                                v-for="project in category.projects"
                                :key="project.id"
                            >
                                <NavProjectLink :project="project" />
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>

            <Collapsible
                v-if="sections.uncategorized.length"
                as-child
                :open="expanded[UNCATEGORIZED_KEY] ?? false"
                @update:open="
                    (open: boolean) => setOpen(UNCATEGORIZED_KEY, open)
                "
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :style="indent(0)"
                            :tooltip="$t('nav.uncategorized')"
                        >
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                :class="{
                                    'rotate-90': expanded[UNCATEGORIZED_KEY],
                                }"
                            />
                            <span class="truncate">
                                {{ $t('nav.uncategorized') }}
                            </span>
                            <span
                                class="ml-auto text-xs text-muted-foreground tabular-nums"
                            >
                                {{ sections.uncategorized.length }}
                            </span>
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="project in sections.uncategorized"
                                :key="project.id"
                            >
                                <NavProjectLink :project="project" />
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>

            <SidebarMenuItem v-if="showAll || sections.hidden > 0">
                <SidebarMenuButton
                    class="text-xs text-muted-foreground"
                    @click="toggleShowAll()"
                >
                    {{
                        showAll
                            ? $t('nav.showActiveProjects')
                            : $t('nav.showAllProjects', { count: total })
                    }}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
