<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Layers, PanelRightClose, PanelRightOpen } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EpicPanel from '@/components/EpicPanel.vue';
import IssueViewToggle from '@/components/IssueViewToggle.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import PriorityBars from '@/components/PriorityBars.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { useEpicPanelCollapsed } from '@/composables/useEpicPanelCollapsed';
import { getInitials } from '@/composables/useInitials';
import type { EpicFocus } from '@/lib/epics';
import {
    epicColor,
    isEpic,
    matchesEpicFocus,
    panelEpics,
    summarizeEpics,
} from '@/lib/epics';
import {
    board,
    reorder,
    show,
    updateState,
    updateStatus,
} from '@/routes/issues';
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
    drop: (issue: Issue, after?: string | null) => void;
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
            drop: (issue: Issue, after: string | null = null) =>
                router.patch(
                    updateState.url({ issue: issue.identifier }),
                    { workflow_state_id: state.id, after },
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
        drop: (issue: Issue, after: string | null = null) =>
            router.patch(
                updateStatus.url({ issue: issue.identifier }),
                { status: column.status, after },
                RELOAD,
            ),
    }));
});

const epics = computed(() => summarizeEpics(props.issues, columns.value));
const showAllEpics = ref(false);
const epicFocus = ref<EpicFocus>(null);
const shownEpics = computed(() =>
    panelEpics(epics.value, showAllEpics.value, epicFocus.value),
);
const epicHover = ref<EpicFocus>(null);
const epicSheetOpen = ref(false);
const epicPanelCollapsed = useEpicPanelCollapsed(usePage().props.auth.user.id);

// Epics live in the panel, so the columns only hold the work itself.
const cards = computed(() =>
    props.issues.filter(
        (issue) => !isEpic(issue) && matchesEpicFocus(issue, epicFocus.value),
    ),
);

// A filter on an epic that left the board (archived, reassigned) would leave
// no row to clear it with.
watch(epics, (all) => {
    const focus = epicFocus.value;

    if (typeof focus === 'number' && !all.some((e) => e.id === focus)) {
        epicFocus.value = null;
    }
});

function toggleEpicFocus(focus: number | 'none') {
    epicFocus.value = epicFocus.value === focus ? null : focus;
    epicSheetOpen.value = false;
}

function hoverClass(issue: Issue): string {
    if (epicHover.value === null) {
        return '';
    }

    if (!matchesEpicFocus(issue, epicHover.value)) {
        return 'opacity-30';
    }

    return epicHover.value === 'none' ? '' : 'outline-2 outline-offset-1';
}

function hoverStyle(issue: Issue): Record<string, string> {
    return typeof epicHover.value === 'number' && issue.parent
        ? { outlineColor: epicColor(issue.parent.id) }
        : {};
}

const issuesByColumn = computed(() => {
    const grouped = new Map<string, Issue[]>();

    for (const column of columns.value) {
        grouped.set(column.key, cards.value.filter(column.match));
    }

    return grouped;
});

// Cards with nothing to show would otherwise render an empty footer row.
function hasMeta(issue: Issue): boolean {
    return issue.assignee !== null || issue.archivedAt !== null;
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
    dropAfter.value = null;
}

// The card the pointer is currently over, so a drop lands where it looks like
// it will rather than always at the top of the column.
const dropAfter = ref<string | null>(null);

function onCardDragOver(event: DragEvent, issue: Issue) {
    if (draggingId.value === null || draggingId.value === issue.identifier) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    // Past the midpoint means "after this card", before it means "before".
    const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const below = event.clientY > box.top + box.height / 2;

    dropAfter.value = below ? issue.identifier : previousIn(issue);
}

function previousIn(issue: Issue): string | null {
    const column = columns.value.find((c) => c.match(issue));
    const cards = column ? (issuesByColumn.value.get(column.key) ?? []) : [];
    const index = cards.findIndex((c) => c.identifier === issue.identifier);

    return index > 0 ? cards[index - 1].identifier : null;
}

function onDrop(event: DragEvent, column: BoardColumn) {
    dragOverKey.value = null;
    const identifier = event.dataTransfer?.getData('text/plain');
    const issue = props.issues.find((i) => i.identifier === identifier);
    const after = dropAfter.value;
    dropAfter.value = null;

    if (!issue) {
        return;
    }

    // Same lane: only the position changed.
    if (column.match(issue)) {
        router.patch(
            reorder.url({ issue: issue.identifier }),
            { after },
            RELOAD,
        );

        return;
    }

    column.drop(issue, after);
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
            <Button
                variant="outline"
                size="sm"
                class="lg:hidden"
                @click="epicSheetOpen = true"
            >
                <Layers class="size-4" />
                {{ $t('board.epics') }}
            </Button>
        </div>

        <Sheet v-model:open="epicSheetOpen">
            <SheetContent side="right" class="w-80 gap-0 p-0">
                <SheetHeader class="border-b">
                    <SheetTitle>{{ $t('board.epics') }}</SheetTitle>
                    <SheetDescription class="sr-only">
                        {{ $t('board.epicsDescription') }}
                    </SheetDescription>
                </SheetHeader>
                <EpicPanel
                    v-model:show-all="showAllEpics"
                    class="min-h-0 flex-1"
                    :epics="shownEpics"
                    :grouped="!project"
                    :focus="epicFocus"
                    @toggle="toggleEpicFocus"
                />
            </SheetContent>
        </Sheet>

        <div class="flex min-h-0 flex-1 gap-3">
            <div class="flex min-h-0 min-w-0 flex-1 gap-3 overflow-x-auto pb-1">
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
                            column.color
                                ? { backgroundColor: column.color }
                                : {}
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
                        :class="
                            dragOverKey === column.key ? 'bg-accent/40' : ''
                        "
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
                                hoverClass(issue),
                            ]"
                            :style="hoverStyle(issue)"
                            @dragstart="onDragStart($event, issue)"
                            @dragend="onDragEnd"
                            @dragover="onCardDragOver($event, issue)"
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
                                            source: issue.originatingReport
                                                .label,
                                        })
                                    "
                                >
                                    {{ issue.originatingReport.label }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="h-5 px-1.5 text-[10px] font-normal"
                                    :class="{
                                        'ml-auto': !issue.originatingReport,
                                    }"
                                >
                                    {{ $t(`issueType.${issue.type}`) }}
                                </Badge>
                            </div>

                            <span
                                v-if="issue.parent"
                                class="inline-flex max-w-full items-center gap-1.5 self-start rounded-full border px-2 py-0.5 text-[11px] font-medium text-foreground/80"
                                :style="{
                                    backgroundColor: `${epicColor(issue.parent.id)}1f`,
                                    borderColor: `${epicColor(issue.parent.id)}59`,
                                }"
                                :title="`${issue.parent.identifier} · ${issue.parent.title}`"
                            >
                                <span
                                    class="size-1.5 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor: epicColor(
                                            issue.parent.id,
                                        ),
                                    }"
                                />
                                <span class="truncate">{{
                                    issue.parent.title
                                }}</span>
                            </span>

                            <span
                                class="line-clamp-2 font-medium tracking-tight"
                            >
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

            <aside
                v-if="!epicPanelCollapsed"
                class="sticky top-4 hidden max-h-[calc(100svh-10rem)] w-72 shrink-0 flex-col overflow-hidden rounded-xl border border-sidebar-border/70 lg:flex dark:border-sidebar-border"
            >
                <h2
                    class="flex shrink-0 items-center gap-2 border-b px-3 py-2.5 text-xs font-medium"
                >
                    <Layers class="size-3.5 text-muted-foreground" />
                    <span class="tracking-tight">{{ $t('board.epics') }}</span>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="ml-auto size-6"
                        :aria-label="$t('board.hideEpics')"
                        :title="$t('board.hideEpics')"
                        @click="epicPanelCollapsed = true"
                    >
                        <PanelRightClose class="size-3.5" />
                    </Button>
                </h2>
                <EpicPanel
                    v-model:show-all="showAllEpics"
                    class="min-h-0 flex-1"
                    :epics="shownEpics"
                    :grouped="!project"
                    :focus="epicFocus"
                    @toggle="toggleEpicFocus"
                    @hover="epicHover = $event"
                />
            </aside>
            <button
                v-else
                type="button"
                class="hidden w-9 shrink-0 flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 py-3 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent/50 hover:text-foreground lg:flex dark:border-sidebar-border"
                :aria-label="$t('board.showEpics')"
                :title="$t('board.showEpics')"
                @click="epicPanelCollapsed = false"
            >
                <PanelRightOpen class="size-3.5" />
                <span class="[writing-mode:vertical-rl]">{{
                    $t('board.epics')
                }}</span>
            </button>
        </div>
    </div>
</template>
