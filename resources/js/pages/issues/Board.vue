<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import IssueViewToggle from '@/components/IssueViewToggle.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import { Badge } from '@/components/ui/badge';
import { board, show, updateStatus } from '@/routes/issues';
import type { Issue, ProjectLinks as ProjectLinksType } from '@/types';

const props = defineProps<{
    issues: Issue[];
    project?: { key: string; name: string; links: ProjectLinksType } | null;
}>();

const heading = computed(() =>
    props.project ? `${props.project.key} · Board` : 'Board',
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Board', href: board() }],
    },
});

const columns: { status: Issue['status']; label: string; dot: string }[] = [
    { status: 'backlog', label: 'Backlog', dot: 'bg-muted-foreground/50' },
    { status: 'in_progress', label: 'In progress', dot: 'bg-primary' },
    { status: 'in_review', label: 'In review', dot: 'bg-sky-500' },
    { status: 'done', label: 'Done', dot: 'bg-emerald-500' },
];

const priorityDot: Record<Issue['priority'], string> = {
    none: 'border border-muted-foreground/40',
    low: 'bg-sky-400',
    medium: 'bg-amber-400',
    high: 'bg-orange-500',
    urgent: 'bg-red-500',
};

const issuesByStatus = computed(() => {
    const grouped = new Map<Issue['status'], Issue[]>();

    for (const column of columns) {
        grouped.set(
            column.status,
            props.issues.filter((issue) => issue.status === column.status),
        );
    }

    return grouped;
});

const draggingId = ref<string | null>(null);
const dragOverStatus = ref<Issue['status'] | null>(null);

function onDragStart(event: DragEvent, issue: Issue) {
    draggingId.value = issue.identifier;
    event.dataTransfer?.setData('text/plain', issue.identifier);
}

function onDragEnd() {
    draggingId.value = null;
    dragOverStatus.value = null;
}

function onDrop(event: DragEvent, status: Issue['status']) {
    dragOverStatus.value = null;
    const identifier = event.dataTransfer?.getData('text/plain');
    const issue = props.issues.find((i) => i.identifier === identifier);

    if (!issue || issue.status === status) {
        return;
    }

    router.patch(
        updateStatus.url({ issue: issue.identifier }),
        { status },
        { preserveScroll: true, preserveState: true, only: ['issues'] },
    );
}
</script>

<template>
    <Head title="Board" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-lg font-medium">{{ heading }}</h1>
            <IssueViewToggle active="board" :project-key="project?.key" />
            <ProjectLinks v-if="project" :links="project.links" />
        </div>

        <div
            class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                v-for="column in columns"
                :key="column.status"
                class="flex flex-col gap-2 rounded-xl border p-2 transition-colors"
                :class="
                    dragOverStatus === column.status
                        ? 'border-primary/50 bg-accent/40'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
                @dragover.prevent="dragOverStatus = column.status"
                @drop="onDrop($event, column.status)"
            >
                <h2
                    class="flex items-center gap-2 px-1.5 py-1 text-xs font-medium text-muted-foreground"
                >
                    <span class="size-2 rounded-full" :class="column.dot" />
                    {{ column.label }}
                    <span class="text-muted-foreground/70">
                        {{ issuesByStatus.get(column.status)?.length }}
                    </span>
                </h2>

                <Link
                    v-for="issue in issuesByStatus.get(column.status)"
                    :key="issue.identifier"
                    :href="show({ issue: issue.identifier })"
                    draggable="true"
                    class="flex cursor-grab flex-col gap-2 rounded-lg border bg-card p-3 text-sm shadow-xs transition-colors hover:bg-accent active:cursor-grabbing"
                    :class="
                        draggingId === issue.identifier
                            ? 'border-primary opacity-60'
                            : 'border-sidebar-border/70 dark:border-sidebar-border'
                    "
                    @dragstart="onDragStart($event, issue)"
                    @dragend="onDragEnd"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="priorityDot[issue.priority]"
                        />
                        <span class="font-mono text-xs text-muted-foreground">
                            {{ issue.identifier }}
                        </span>
                        <span
                            v-if="issue.childrenCount > 0"
                            class="ml-auto text-xs text-muted-foreground"
                        >
                            {{ issue.childrenCount }} sub
                        </span>
                    </div>
                    <span class="line-clamp-2">{{ issue.title }}</span>
                    <div class="flex flex-wrap items-center gap-1">
                        <LabelBadge
                            v-for="label in issue.labels"
                            :key="label.id"
                            :name="label.name"
                            :color="label.color"
                        />
                        <Badge variant="outline" class="w-fit font-normal">
                            {{ issue.type }}
                        </Badge>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
