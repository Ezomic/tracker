<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { SidebarMenuSubButton } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { board } from '@/routes/projects';
import type { SidebarProject, SidebarProjectCounts } from '@/types';

defineProps<{
    project: SidebarProject;
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();
const { t } = useI18n();

const statuses: {
    key: keyof SidebarProjectCounts;
    dot: string;
}[] = [
    { key: 'backlog', dot: 'bg-muted-foreground/50' },
    { key: 'in_progress', dot: 'bg-primary' },
    { key: 'in_review', dot: 'bg-sky-500' },
    { key: 'done', dot: 'bg-emerald-500' },
];

function countsTitle(counts: SidebarProjectCounts): string {
    return statuses
        .map(
            (status) =>
                `${counts[status.key]} ${t(`status.${status.key}`).toLowerCase()}`,
        )
        .join(' · ');
}
</script>

<template>
    <SidebarMenuSubButton
        as-child
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
                class="ml-auto flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums"
            >
                <span
                    v-for="status in statuses"
                    v-show="project.counts[status.key] > 0"
                    :key="status.key"
                    class="flex items-center gap-1"
                >
                    <span class="size-1.5 rounded-full" :class="status.dot" />
                    {{ project.counts[status.key] }}
                </span>
            </span>
        </Link>
    </SidebarMenuSubButton>
</template>
