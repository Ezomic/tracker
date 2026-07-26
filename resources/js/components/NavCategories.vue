<script setup lang="ts">
import { ChevronRight } from '@lucide/vue';
import { ref, watch } from 'vue';
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
import type { SidebarCategory, SidebarProject } from '@/types';

const props = defineProps<{
    tree: SidebarCategory[];
    uncategorized: SidebarProject[];
    currentProjectId: number | null;
}>();

const STORAGE_KEY = 'sidebar:expandedCategories';

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

function setOpen(id: number, open: boolean): void {
    expanded.value = { ...expanded.value, [id]: open };
}

function indent(depth: number): Record<string, string> {
    return { paddingLeft: `${0.5 + depth * 0.75}rem` };
}

// Auto-expand the category holding the current project, plus its ancestors, so
// the active project is always visible on navigation.
watch(
    () => props.currentProjectId,
    (id) => {
        if (id === null) {
            return;
        }

        // Current project sits directly in the uncategorized bucket.
        if (props.uncategorized.some((project) => project.id === id)) {
            expanded.value = { ...expanded.value, [UNCATEGORIZED_KEY]: true };

            return;
        }

        const owning = props.tree.find((category) =>
            category.projects.some((project) => project.id === id),
        );

        if (!owning) {
            return;
        }

        const parentOf = new Map(
            props.tree.map((category) => [category.id, category.parentId]),
        );

        const next = { ...expanded.value };
        let cursor: number | null = owning.id;

        while (cursor !== null) {
            next[cursor] = true;
            cursor = parentOf.get(cursor) ?? null;
        }

        expanded.value = next;
    },
    { immediate: true },
);
</script>

<template>
    <SidebarGroup class="px-2 py-0 group-data-[collapsible=icon]:hidden">
        <SidebarGroupLabel>{{ $t('nav.projects') }}</SidebarGroupLabel>
        <SidebarMenu>
            <Collapsible
                v-for="category in tree"
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
                        <SidebarMenuSub>
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
                v-if="uncategorized.length"
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
                                {{ uncategorized.length }}
                            </span>
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="project in uncategorized"
                                :key="project.id"
                            >
                                <NavProjectLink :project="project" />
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
