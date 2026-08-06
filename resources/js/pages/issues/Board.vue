<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock, CornerDownRight, GitBranch } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import IssueViewToggle from '@/components/IssueViewToggle.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import PriorityBars from '@/components/PriorityBars.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { getInitials } from '@/composables/useInitials';
import { formatDuration } from '@/lib/duration';
import { board, show, updateState, updateStatus } from '@/routes/issues';
import type { Issue, ProjectLinks as ProjectLinksType } from '@/types';

interface WorkflowStateColumn {
    id: number;
    name: string;
    color: string;
    category: string;
}

const props = defineProps<{
    issues: Issue[];
    project?: { key: string; name: string; links: ProjectLinksType } | null;
    workflowStates: WorkflowStateColumn[];
    showArchived: boolean;
}>();

function toggleArchived(value: boolean) {
    router.reload({
        data: { archived: value ? 1 : 0 },
        only: ['issues', 'showArchived'],
    });
}

const { t } = useI18n();

const heading = computed(() =>
    props.project
        ? `${props.project.key} · ${t('board.title')}`
        : t('board.title'),
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Board', href: board() }],
    },
});

interface BoardColumn {
    key: string;
    label: string;
    color: string | null;
    colorClass: string;
    match: (issue: Issue) => boolean;
    drop: (issue: Issue) => void;
}

const RELOAD = { preserveScroll: true, preserveState: true, only: ['issues'] };

const LEGACY_COLUMNS: { status: Issue['status']; dot: string }[] = [
    { status: 'backlog', dot: 'bg-muted-foreground/50' },
    { status: 'in_progress', dot: 'bg-primary' },
    { status: 'in_review', dot: 'bg-sky-500' },
    { status: 'done', dot: 'bg-emerald-500' },
];

// Lanes come from the project's type; without one we fall back to the four
// legacy statuses so an untyped project still has a working board.
const columns = computed<BoardColumn[]>(() => {
    if (props.workflowStates.length) {
        return props.workflowStates.map((state) => ({
            key: `state-${state.id}`,
            label: state.name,
            color: state.color,
            colorClass: '',
            match: (issue: Issue) => issue.workflowStateId === state.id,
            drop: (issue: Issue) =>
                router.patch(
                    updateState.url({ issue: issue.identifier }),
                    { workflow_state_id: state.id },
                    RELOAD,
                ),
        }));
    }

    return LEGACY_COLUMNS.map((column) => ({
        key: column.status,
        label: t(`status.${column.status}`),
        color: null,
        colorClass: column.dot,
        match: (issue: Issue) => issue.status === column.status,
        drop: (issue: Issue) =>
            router.patch(
                updateStatus.url({ issue: issue.identifier }),
                { status: column.status },
                RELOAD,
            ),
    }));
});

const issuesByColumn = computed(() => {
    const grouped = new Map<string, Issue[]>();

    for (const column of columns.value) {
        grouped.set(column.key, props.issues.filter(column.match));
    }

    return grouped;
});

// Cards with nothing to show would otherwise render an empty footer row.
function hasMeta(issue: Issue): boolean {
    return (
        issue.assignee !== null ||
        issue.archivedAt !== null ||
        issue.loggedMinutes > 0 ||
        issue.estimateMinutes !== null ||
        issue.childrenCount > 0
    );
}

const draggingId = ref<string | null>(null);
const dragOverKey = ref<string | null>(null);

function onDragStart(event: DragEvent, issue: Issue) {
    draggingId.value = issue.identifier;
    event.dataTransfer?.setData('text/plain', issue.identifier);
}

function onDragEnd() {
    draggingId.value = null;
    dragOverKey.value = null;
}

function onDrop(event: DragEvent, column: BoardColumn) {
    dragOverKey.value = null;
    const identifier = event.dataTransfer?.getData('text/plain');
    const issue = props.issues.find((i) => i.identifier === identifier);

    if (!issue || column.match(issue)) {
        return;
    }

    column.drop(issue);
}
</script>

<template>
    <Head :title="$t('board.title')" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-lg font-medium tracking-tight">{{ heading }}</h1>
            <IssueViewToggle active="board" :project-key="project?.key" />
            <ProjectLinks v-if="project" :links="project.links" />
            <Label
                class="ml-auto flex items-center gap-2 text-sm font-normal text-muted-foreground"
            >
                <Switch
                    :model-value="showArchived"
                    @update:model-value="toggleArchived"
                />
                {{ $t('board.showArchived') }}
            </Label>
        </div>

        <div class="flex min-h-0 flex-1 gap-3 overflow-x-auto pb-1">
            <div
                v-for="column in columns"
                :key="column.key"
                class="flex min-h-0 w-72 shrink-0 flex-col overflow-hidden rounded-xl border transition-colors"
                :class="
                    dragOverKey === column.key
                        ? 'border-primary/50 ring-2 ring-primary/30'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
                @dragover.prevent="dragOverKey = column.key"
                @dragleave="dragOverKey = null"
                @drop="onDrop($event, column)"
            >
                <div
                    class="h-1 w-full shrink-0"
                    :class="column.colorClass"
                    :style="
                        column.color ? { backgroundColor: column.color } : {}
                    "
                />

                <h2
                    class="flex shrink-0 items-center gap-2 px-3 py-2.5 text-xs font-medium"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="column.colorClass"
                        :style="
                            column.color
                                ? { backgroundColor: column.color }
                                : {}
                        "
                    />
                    <span class="tracking-tight">{{ column.label }}</span>
                    <span
                        class="ml-auto inline-flex min-w-5 justify-center rounded-full bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground tabular-nums"
                    >
                        {{ issuesByColumn.get(column.key)?.length }}
                    </span>
                </h2>

                <div
                    class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto bg-muted/30 p-2 transition-colors"
                    :class="dragOverKey === column.key ? 'bg-accent/40' : ''"
                >
                    <Link
                        v-for="issue in issuesByColumn.get(column.key)"
                        :key="issue.identifier"
                        :href="show({ issue: issue.identifier })"
                        draggable="true"
                        class="group flex cursor-grab flex-col gap-2 overflow-hidden rounded-lg border bg-card p-3 text-sm shadow-xs transition-all hover:-translate-y-px hover:shadow-md active:cursor-grabbing"
                        :class="[
                            draggingId === issue.identifier
                                ? 'border-primary opacity-60'
                                : 'border-sidebar-border/70 dark:border-sidebar-border',
                            issue.archivedAt ? 'opacity-60' : '',
                        ]"
                        @dragstart="onDragStart($event, issue)"
                        @dragend="onDragEnd"
                    >
                        <div class="flex items-center gap-2">
                            <PriorityBars :priority="issue.priority" />
                            <span
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ issue.identifier }}
                            </span>
                            <Badge
                                v-if="issue.originatingReport"
                                variant="secondary"
                                class="ml-auto h-5 px-1.5 text-[10px] font-normal"
                                :title="
                                    $t('issue.filedBy', {
                                        source: issue.originatingReport.label,
                                    })
                                "
                            >
                                {{ issue.originatingReport.label }}
                            </Badge>
                            <Badge
                                variant="outline"
                                class="h-5 px-1.5 text-[10px] font-normal"
                                :class="{ 'ml-auto': !issue.originatingReport }"
                            >
                                {{ $t(`issueType.${issue.type}`) }}
                            </Badge>
                        </div>

                        <span
                            v-if="issue.parent"
                            class="flex items-center gap-1 font-mono text-[11px] text-muted-foreground"
                            :title="issue.parent.title"
                        >
                            <CornerDownRight class="size-3 shrink-0" />
                            {{ issue.parent.identifier }}
                        </span>

                        <span class="line-clamp-2 font-medium tracking-tight">
                            {{ issue.title }}
                        </span>

                        <div
                            v-if="issue.labels.length"
                            class="flex flex-wrap items-center gap-1"
                        >
                            <LabelBadge
                                v-for="label in issue.labels"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                        </div>

                        <p
                            v-if="issue.archivedAt && issue.archiveReason"
                            class="line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ issue.archiveReason }}
                        </p>

                        <div
                            v-if="hasMeta(issue)"
                            class="flex items-center gap-2 pt-0.5"
                        >
                            <Avatar
                                v-if="issue.assignee"
                                class="size-6 shrink-0"
                                :title="issue.assignee.name"
                            >
                                <AvatarFallback class="text-[10px]">
                                    {{ getInitials(issue.assignee.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <Badge
                                v-if="issue.archivedAt"
                                variant="secondary"
                                class="h-5 px-1.5 text-[10px] font-normal"
                            >
                                {{ $t('issue.archived') }}
                            </Badge>

                            <div
                                class="ml-auto flex items-center gap-2.5 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="
                                        issue.loggedMinutes > 0 ||
                                        issue.estimateMinutes
                                    "
                                    class="flex items-center gap-1 tabular-nums"
                                    :title="$t('issue.estimate')"
                                >
                                    <Clock class="size-3.5" />
                                    {{ formatDuration(issue.loggedMinutes) }}
                                    <template v-if="issue.estimateMinutes">
                                        <span class="text-muted-foreground/50">
                                            /
                                        </span>
                                        {{
                                            formatDuration(
                                                issue.estimateMinutes,
                                            )
                                        }}
                                    </template>
                                </span>
                                <span
                                    v-if="issue.childrenCount > 0"
                                    class="flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 font-medium text-primary tabular-nums"
                                    :title="
                                        $t('issue.subDone', {
                                            done: issue.childrenDoneCount,
                                            total: issue.childrenCount,
                                        })
                                    "
                                >
                                    <GitBranch class="size-3.5" />
                                    {{ issue.childrenDoneCount }}/{{
                                        issue.childrenCount
                                    }}
                                </span>
                            </div>
                        </div>
                    </Link>

                    <p
                        v-if="issuesByColumn.get(column.key)?.length === 0"
                        class="px-2 py-6 text-center text-xs text-muted-foreground/60"
                    >
                        {{ $t('board.emptyColumn') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
