<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import PriorityBadge from '@/components/PriorityBadge.vue';
import { Badge } from '@/components/ui/badge';
import { board, show, updateStatus } from '@/routes/issues';
import type { Issue } from '@/types';

const props = defineProps<{
    issues: Issue[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Board', href: board() }],
    },
});

const columns: { status: Issue['status']; label: string }[] = [
    { status: 'backlog', label: 'Backlog' },
    { status: 'in_progress', label: 'In Progress' },
    { status: 'in_review', label: 'In Review' },
    { status: 'done', label: 'Done' },
];

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

function onDragStart(event: DragEvent, issue: Issue) {
    event.dataTransfer?.setData('text/plain', issue.identifier);
}

function onDrop(event: DragEvent, status: Issue['status']) {
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

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading
            title="Board"
            description="Drag an issue between columns to change its status"
        />

        <div
            class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                v-for="column in columns"
                :key="column.status"
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                @dragover.prevent
                @drop="onDrop($event, column.status)"
            >
                <h2
                    class="flex items-center justify-between text-sm font-medium text-muted-foreground"
                >
                    {{ column.label }}
                    <span>{{ issuesByStatus.get(column.status)?.length }}</span>
                </h2>

                <Link
                    v-for="issue in issuesByStatus.get(column.status)"
                    :key="issue.identifier"
                    :href="show({ issue: issue.identifier })"
                    draggable="true"
                    class="flex cursor-grab flex-col gap-1 rounded-lg border border-sidebar-border/70 bg-background p-3 text-sm hover:bg-accent active:cursor-grabbing dark:border-sidebar-border"
                    @dragstart="onDragStart($event, issue)"
                >
                    <span class="font-mono text-xs text-muted-foreground">{{
                        issue.identifier
                    }}</span>
                    <span
                        >{{ issue.title }}
                        <span
                            v-if="issue.childrenCount > 0"
                            class="text-xs text-muted-foreground"
                            >({{ issue.childrenCount }})</span
                        ></span
                    >
                    <div class="flex flex-wrap gap-1">
                        <Badge variant="outline" class="w-fit">{{
                            issue.type
                        }}</Badge>
                        <PriorityBadge :priority="issue.priority" />
                        <LabelBadge
                            v-for="label in issue.labels"
                            :key="label.id"
                            :name="label.name"
                            :color="label.color"
                        />
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
