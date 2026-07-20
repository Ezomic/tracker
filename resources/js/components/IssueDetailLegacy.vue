<script setup lang="ts">
import { Form, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    Clock,
    GitBranch,
    GitCommit,
    GitPullRequest,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import AutoTextarea from '@/components/AutoTextarea.vue';
import InputError from '@/components/InputError.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { getInitials } from '@/composables/useInitials';
import { formatDuration } from '@/lib/duration';
import {
    archive,
    confirmTime as confirmTimeRoute,
    show,
    unarchive,
} from '@/routes/issues';
import {
    destroy as destroyComment,
    store as storeComment,
} from '@/routes/issues/comments';
import {
    destroy as destroyTime,
    store as storeTime,
} from '@/routes/issues/time';
import type {
    EpicOption,
    Issue,
    IssueComment,
    IssueLabel,
    IssueUser,
    TimeEntry,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    issue: Issue;
    timeline: TimelineItem[];
    epics: EpicOption[];
    labels: IssueLabel[];
    members: IssueUser[];
    canLogTime: boolean;
    canManageTime: boolean;
    canModerateComments: boolean;
    canArchive: boolean;
    currentUserId: number;
}>();

const { t } = useI18n();

function describeActivity(
    item: Extract<TimelineItem, { kind: 'activity' }>,
): string {
    const data = item.data ?? {};

    switch (item.type) {
        case 'created':
            return t('activity.created');
        case 'status_changed':
            return t('activity.statusTo', {
                status: t(`status.${data.to}`),
            });
        case 'assigned':
            return data.to
                ? t('activity.assignedTo', { name: data.to })
                : t('activity.unassigned');
        case 'archived':
            return data.reason
                ? t('activity.archivedReason', { reason: data.reason })
                : t('activity.archived');
        case 'unarchived':
            return t('activity.unarchived');
        case 'time_logged':
            return t('activity.timeLogged', {
                duration: formatDuration(Number(data.minutes)),
            });
        case 'time_confirmed':
            return t('activity.timeConfirmed', {
                duration: formatDuration(Number(data.minutes)),
            });
        case 'priority_changed':
            return t('activity.priorityTo', {
                priority: t(`priority.${data.to}`),
            });
        case 'type_changed':
            return t('activity.typeTo', {
                type: t(`issueType.${data.to}`),
            });
        case 'estimate_changed':
            return data.to
                ? t('activity.estimateTo', {
                      duration: formatDuration(Number(data.to)),
                  })
                : t('activity.estimateCleared');
        case 'renamed':
            return t('activity.renamed', { title: data.to });
        case 'description_edited':
            return t('activity.descriptionEdited');
        case 'parent_changed':
            return data.to
                ? t('activity.parentSet', { parent: data.to })
                : t('activity.parentCleared');
        case 'label_added':
            return t('activity.labelAdded', { name: data.name });
        case 'label_removed':
            return t('activity.labelRemoved', { name: data.name });
        case 'pr_opened':
            return t('activity.prOpened');
        case 'pr_merged':
            return t('activity.prMerged');
        default:
            return item.type.replace(/_/g, ' ');
    }
}

const archiveOpen = ref(false);
const archiveReason = ref('');
const timeOpen = ref(false);

function archiveIssue() {
    router.post(
        archive({ issue: props.issue.identifier }).url,
        { reason: archiveReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                archiveOpen.value = false;
                archiveReason.value = '';
            },
        },
    );
}

function unarchiveIssue() {
    router.post(
        unarchive({ issue: props.issue.identifier }).url,
        {},
        { preserveScroll: true },
    );
}

const estimateDefault = props.issue.estimateMinutes
    ? formatDuration(props.issue.estimateMinutes)
    : '';

const duration = ref('');
const spentOn = ref(new Date().toISOString().slice(0, 10));
const note = ref('');
const timeError = ref<string | null>(null);

const confirmMinutes = ref(String(props.issue.loggedMinutes));
const billrClientName = ref('');
const confirmError = ref<string | null>(null);

watch(
    () => props.issue.loggedMinutes,
    (minutes) => {
        confirmMinutes.value = String(minutes);
    },
);

const commentBody = ref('');
const commentError = ref<string | null>(null);

const progressPercent = computed(() => {
    if (!props.issue.estimateMinutes || props.issue.estimateMinutes <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(
            (props.issue.loggedMinutes / props.issue.estimateMinutes) * 100,
        ),
    );
});

const overEstimate = computed(
    () =>
        props.issue.estimateMinutes != null &&
        props.issue.loggedMinutes > props.issue.estimateMinutes,
);

function logTime() {
    timeError.value = null;

    router.post(
        storeTime({ issue: props.issue.identifier }).url,
        { duration: duration.value, spent_on: spentOn.value, note: note.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                duration.value = '';
                note.value = '';
            },
            onError: (errors) => {
                timeError.value = errors.duration ?? errors.spent_on ?? null;
            },
        },
    );
}

function postComment() {
    if (commentBody.value.trim() === '') {
        return;
    }

    commentError.value = null;

    router.post(
        storeComment({ issue: props.issue.identifier }).url,
        { body: commentBody.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                commentBody.value = '';
            },
            onError: (errors) => {
                commentError.value = errors.body ?? null;
            },
        },
    );
}

function confirmTime() {
    confirmError.value = null;

    router.post(
        confirmTimeRoute({ issue: props.issue.identifier }).url,
        {
            minutes: confirmMinutes.value,
            billr_client_name: billrClientName.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                billrClientName.value = '';
            },
            onError: (errors) => {
                confirmError.value = errors.minutes ?? null;
            },
        },
    );
}

function removeEntry(entry: TimeEntry) {
    router.delete(
        destroyTime({ issue: props.issue.identifier, timeEntry: entry.id }).url,
        { preserveScroll: true },
    );
}

function removeComment(comment: IssueComment) {
    router.delete(
        destroyComment({ issue: props.issue.identifier, comment: comment.id })
            .url,
        { preserveScroll: true },
    );
}

const canRemove = (entry: TimeEntry) =>
    props.canManageTime || entry.user?.id === props.currentUserId;

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const canRemoveComment = (comment: IssueComment) =>
    props.canModerateComments || comment.user?.id === props.currentUserId;

function formatTimestamp(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const doneChildrenCount = computed(
    () =>
        props.issue.children.filter((child) => child.status === 'done').length,
);

// A raw <textarea> ignores :default-value (Vue doesn't map it to the
// defaultValue DOM property), so bind the initial value via v-model to keep
// the field populated — otherwise saving would submit a blank description.
const description = ref(props.issue.description ?? '');

// The Select needs a non-empty value, so "unassigned" stands in for null and
// the hidden input submits an empty value the request normalises back to null.
const assigneeId = ref(
    props.issue.assignee ? String(props.issue.assignee.id) : 'unassigned',
);
const submittedAssignee = computed(() =>
    assigneeId.value === 'unassigned' ? '' : assigneeId.value,
);

const statusDot: Record<Issue['status'], string> = {
    backlog: 'bg-muted-foreground/50',
    in_progress: 'bg-primary',
    in_review: 'bg-sky-500',
    done: 'bg-emerald-500',
};
</script>

<template>
    <Form
        v-bind="IssueController.update.form({ issue: issue.identifier })"
        class="flex h-full flex-1 flex-col p-4"
        v-slot="{ errors, processing }"
    >
        <div class="mb-4 flex items-center gap-2 text-sm">
            <span class="text-muted-foreground">{{ issue.project.key }}</span>
            <span class="text-muted-foreground/50">/</span>
            <span class="font-mono text-muted-foreground">
                {{ issue.identifier }}
            </span>
            <span
                class="ml-1 inline-flex items-center gap-1.5 rounded-full border border-border px-2 py-0.5 text-xs"
            >
                <span
                    class="size-2 rounded-full"
                    :class="statusDot[issue.status]"
                />
                {{ $t(`status.${issue.status}`) }}
            </span>
            <span
                v-if="issue.archivedAt"
                class="inline-flex items-center gap-1.5 rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground"
                :title="issue.archiveReason ?? undefined"
            >
                <Archive class="size-3" />
                {{ $t('issue.archived') }}
            </span>

            <div class="ml-auto flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="timeOpen = true"
                >
                    <Clock class="size-4" />
                    {{ $t('time.title') }}
                    <span
                        v-if="issue.loggedMinutes > 0"
                        class="text-muted-foreground"
                    >
                        · {{ formatDuration(issue.loggedMinutes) }}
                    </span>
                </Button>

                <template v-if="canArchive">
                    <Button
                        v-if="issue.archivedAt"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="unarchiveIssue"
                    >
                        <ArchiveRestore class="size-4" />
                        {{ $t('issue.unarchive') }}
                    </Button>
                    <Button
                        v-else
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="archiveOpen = true"
                    >
                        <Archive class="size-4" />
                        {{ $t('issue.archive') }}
                    </Button>
                </template>
            </div>
        </div>

        <p
            v-if="issue.archivedAt && issue.archiveReason"
            class="mb-4 text-sm text-muted-foreground"
        >
            <span class="font-medium text-foreground">{{
                $t('issue.archivedBanner')
            }}</span>
            {{ issue.archiveReason }}
        </p>

        <div class="grid flex-1 gap-8 lg:grid-cols-[minmax(0,1fr)_264px]">
            <div class="flex min-w-0 flex-col gap-5">
                <div class="grid gap-1.5">
                    <Input
                        name="title"
                        :default-value="issue.title"
                        required
                        class="h-auto border-0 bg-transparent px-0 py-0 text-xl font-medium shadow-none focus-visible:ring-0 dark:bg-transparent"
                    />
                    <p
                        v-if="issue.parent"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('issue.partOf') }}
                        <Link
                            :href="show({ issue: issue.parent.identifier })"
                            class="text-foreground hover:underline"
                        >
                            {{ issue.parent.identifier }} —
                            {{ issue.parent.title }}
                        </Link>
                    </p>
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="description"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('common.description') }}
                    </Label>
                    <MarkdownEditor
                        v-model="description"
                        name="description"
                        :rows="3"
                        :placeholder="$t('newIssue.descriptionPlaceholder')"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div
                    v-if="issue.children.length > 0"
                    class="grid gap-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">
                            {{ $t('issue.subIssues') }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{
                                $t('issue.subDone', {
                                    done: doneChildrenCount,
                                    total: issue.children.length,
                                })
                            }}
                        </span>
                    </div>
                    <Link
                        v-for="child in issue.children"
                        :key="child.identifier"
                        :href="show({ issue: child.identifier })"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-accent"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="statusDot[child.status]"
                        />
                        <span class="font-mono text-xs text-muted-foreground">
                            {{ child.identifier }}
                        </span>
                        <span class="truncate">{{ child.title }}</span>
                    </Link>
                </div>

                <div>
                    <Button type="submit" :disabled="processing">
                        {{ $t('issue.saveChanges') }}
                    </Button>
                </div>
            </div>

            <aside
                class="flex flex-col gap-4 lg:border-l lg:border-sidebar-border/70 lg:pl-6 dark:lg:border-sidebar-border"
            >
                <div class="grid gap-1.5">
                    <Label for="assignee" class="text-xs text-muted-foreground">
                        {{ $t('issue.assignee') }}
                    </Label>
                    <input
                        type="hidden"
                        name="assignee_id"
                        :value="submittedAssignee"
                    />
                    <Select v-model="assigneeId">
                        <SelectTrigger id="assignee" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="unassigned">
                                {{ $t('issue.unassigned') }}
                            </SelectItem>
                            <SelectItem
                                v-for="person in members"
                                :key="person.id"
                                :value="String(person.id)"
                            >
                                {{ person.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.assignee_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground">{{
                        $t('issue.owner')
                    }}</Label>
                    <p class="text-sm">
                        {{ issue.owner?.name ?? $t('issue.unknown') }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="type" class="text-xs text-muted-foreground">
                        {{ $t('issue.type') }}
                    </Label>
                    <Select name="type" :default-value="issue.type">
                        <SelectTrigger id="type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="feature">{{
                                $t('issueType.feature')
                            }}</SelectItem>
                            <SelectItem value="fix">{{
                                $t('issueType.fix')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="priority" class="text-xs text-muted-foreground">
                        {{ $t('issue.priority') }}
                    </Label>
                    <Select name="priority" :default-value="issue.priority">
                        <SelectTrigger id="priority" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">{{
                                $t('priority.none')
                            }}</SelectItem>
                            <SelectItem value="low">{{
                                $t('priority.low')
                            }}</SelectItem>
                            <SelectItem value="medium">{{
                                $t('priority.medium')
                            }}</SelectItem>
                            <SelectItem value="high">{{
                                $t('priority.high')
                            }}</SelectItem>
                            <SelectItem value="urgent">{{
                                $t('priority.urgent')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.priority" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="estimate" class="text-xs text-muted-foreground">
                        {{ $t('issue.estimate') }}
                    </Label>
                    <Input
                        id="estimate"
                        name="estimate"
                        :default-value="estimateDefault"
                        :placeholder="$t('issue.estimatePlaceholder')"
                    />
                    <InputError :message="errors.estimate" />
                </div>

                <label
                    class="flex items-center gap-2 text-sm"
                    for="invoiceable"
                >
                    <input type="hidden" name="invoiceable" value="0" />
                    <Checkbox
                        id="invoiceable"
                        name="invoiceable"
                        value="1"
                        :default-value="issue.invoiceable"
                    />
                    {{ $t('issue.invoiceable') }}
                </label>
                <InputError :message="errors.invoiceable" />

                <div v-if="issue.children.length === 0" class="grid gap-1.5">
                    <Label
                        for="parent_id"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('issue.epic') }}
                    </Label>
                    <Select
                        name="parent_id"
                        :default-value="
                            issue.parent ? String(issue.parent.id) : undefined
                        "
                    >
                        <SelectTrigger id="parent_id" class="w-full">
                            <SelectValue :placeholder="$t('newIssue.noEpic')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="epic in epics"
                                :key="epic.id"
                                :value="String(epic.id)"
                            >
                                {{ epic.identifier }} — {{ epic.title }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.parent_id" />
                </div>

                <div v-if="labels.length > 0" class="grid gap-2">
                    <Label class="text-xs text-muted-foreground">{{
                        $t('issue.labels')
                    }}</Label>
                    <label
                        v-for="label in labels"
                        :key="label.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            name="labels[]"
                            :value="label.id"
                            :default-value="
                                issue.labels.some((l) => l.id === label.id)
                            "
                        />
                        <LabelBadge :name="label.name" :color="label.color" />
                    </label>
                    <InputError :message="errors.labels" />
                </div>

                <div class="grid gap-2">
                    <Label class="text-xs text-muted-foreground">
                        {{ $t('issue.development') }}
                    </Label>

                    <a
                        v-if="issue.branchUrl"
                        :href="issue.branchUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <GitBranch class="size-4 shrink-0" />
                        <span class="truncate font-mono text-xs">
                            {{ issue.branchName }}
                        </span>
                    </a>
                    <code
                        v-else
                        class="truncate rounded-md bg-muted px-2 py-1.5 font-mono text-xs text-muted-foreground"
                    >
                        {{ issue.branchName }}
                    </code>

                    <a
                        v-if="issue.commitsUrl"
                        :href="issue.commitsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <GitCommit class="size-4 shrink-0" />
                        {{ $t('issue.commits') }}
                    </a>

                    <a
                        v-if="issue.githubPrUrl"
                        :href="issue.githubPrUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <GitPullRequest class="size-4 shrink-0" />
                        {{ $t('issue.pullRequest') }}
                    </a>
                </div>
            </aside>
        </div>
    </Form>

    <Sheet v-model:open="timeOpen">
        <SheetContent
            class="flex w-full flex-col gap-5 overflow-y-auto sm:max-w-md"
        >
            <SheetHeader class="gap-1">
                <SheetTitle
                    >{{ $t('time.title') }} · {{ issue.identifier }}</SheetTitle
                >
                <SheetDescription>
                    <span
                        :class="
                            overEstimate ? 'font-medium text-destructive' : ''
                        "
                    >
                        {{ formatDuration(issue.loggedMinutes) }}
                    </span>
                    <template v-if="issue.estimateMinutes">
                        {{ $t('time.of') }}
                        {{ formatDuration(issue.estimateMinutes) }}
                    </template>
                    {{ $t('time.loggedSuffix') }}
                </SheetDescription>
            </SheetHeader>

            <div
                v-if="issue.estimateMinutes"
                class="h-1.5 overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full rounded-full transition-all"
                    :class="overEstimate ? 'bg-destructive' : 'bg-primary'"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>

            <div
                v-if="issue.timeEntries.length > 0"
                class="flex flex-col divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
            >
                <div
                    v-for="entry in issue.timeEntries"
                    :key="entry.id"
                    class="flex items-center gap-3 py-2 text-sm"
                >
                    <span class="w-16 shrink-0 font-medium">
                        {{ formatDuration(entry.minutes) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p v-if="entry.note" class="truncate">
                            {{ entry.note }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ entry.user?.name ?? $t('issue.unknown') }} ·
                            {{ formatDate(entry.spentOn) }}
                        </p>
                    </div>
                    <Button
                        v-if="canRemove(entry)"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                        @click="removeEntry(entry)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('time.empty') }}
            </p>

            <div
                v-if="canLogTime"
                class="flex flex-col gap-3 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
            >
                <div class="grid gap-1.5">
                    <Label
                        for="log-duration"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('time.duration') }}
                    </Label>
                    <Input
                        id="log-duration"
                        v-model="duration"
                        :placeholder="$t('time.durationPlaceholder')"
                        @keydown.enter.prevent="logTime"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="log-date" class="text-xs text-muted-foreground">
                        {{ $t('time.date') }}
                    </Label>
                    <Input id="log-date" v-model="spentOn" type="date" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="log-note" class="text-xs text-muted-foreground">
                        {{ $t('time.noteOptional') }}
                    </Label>
                    <Input
                        id="log-note"
                        v-model="note"
                        :placeholder="$t('time.notePlaceholder')"
                        @keydown.enter.prevent="logTime"
                    />
                </div>
                <InputError v-if="timeError" :message="timeError" />
                <Button type="button" @click="logTime">{{
                    $t('time.logTime')
                }}</Button>
            </div>

            <div
                v-if="canManageTime"
                class="flex flex-col gap-3 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
            >
                <div class="grid gap-1.5">
                    <Label
                        for="confirm-minutes"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('time.confirmMinutes') }}
                    </Label>
                    <Input
                        id="confirm-minutes"
                        v-model="confirmMinutes"
                        type="number"
                        min="0"
                    />
                </div>
                <div
                    v-if="issue.invoiceable && !issue.project.billrLinked"
                    class="grid gap-1.5"
                >
                    <Label
                        for="confirm-client"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('time.billrClientName') }}
                    </Label>
                    <Input
                        id="confirm-client"
                        v-model="billrClientName"
                        :placeholder="$t('time.billrClientNamePlaceholder')"
                    />
                </div>
                <InputError v-if="confirmError" :message="confirmError" />
                <Button type="button" variant="outline" @click="confirmTime">
                    {{
                        issue.invoiceable
                            ? $t('time.confirmAndBill')
                            : $t('time.confirmTime')
                    }}
                </Button>
                <p
                    v-if="issue.confirmedMinutes != null"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        $t('time.confirmedSummary', {
                            duration: formatDuration(issue.confirmedMinutes),
                            date: formatDate(issue.confirmedAt!),
                        })
                    }}
                </p>
            </div>
        </SheetContent>
    </Sheet>

    <section class="p-4 pt-0">
        <div class="flex flex-col gap-4 lg:max-w-2xl">
            <h2 class="text-sm font-medium">{{ $t('activity.title') }}</h2>

            <div v-if="timeline.length > 0" class="flex flex-col gap-4">
                <template
                    v-for="item in timeline"
                    :key="`${item.kind}-${item.id}`"
                >
                    <div v-if="item.kind === 'comment'" class="flex gap-3">
                        <Avatar class="size-8 shrink-0">
                            <AvatarFallback class="text-xs">
                                {{ getInitials(item.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">
                                    {{ item.user?.name ?? $t('issue.unknown') }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatTimestamp(item.createdAt) }}
                                </span>
                                <Button
                                    v-if="canRemoveComment(item)"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="ml-auto size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                    @click="removeComment(item)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                            <p class="mt-0.5 text-sm whitespace-pre-wrap">
                                {{ item.body }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else-if="item.kind === 'commit'"
                        class="flex items-center gap-2 pl-1 text-xs text-muted-foreground"
                    >
                        <GitCommit class="size-3.5 shrink-0" />
                        <span class="min-w-0 truncate">
                            <a
                                v-if="item.url"
                                :href="item.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-mono text-foreground hover:underline"
                            >
                                {{ item.shortSha }}
                            </a>
                            <span v-else class="font-mono text-foreground">
                                {{ item.shortSha }}
                            </span>
                            {{ item.message.split('\n')[0] }}
                            <span v-if="item.authorName">
                                — {{ item.authorName }}
                            </span>
                        </span>
                        <span class="shrink-0">
                            · {{ formatTimestamp(item.createdAt) }}
                        </span>
                    </div>

                    <div
                        v-else
                        class="flex items-center gap-2 pl-1 text-xs text-muted-foreground"
                    >
                        <span
                            class="size-1.5 shrink-0 rounded-full bg-muted-foreground/40"
                        />
                        <span>
                            <span class="font-medium text-foreground">
                                {{ item.user?.name ?? $t('activity.someone') }}
                            </span>
                            {{ describeActivity(item) }}
                        </span>
                        <span class="shrink-0">
                            · {{ formatTimestamp(item.createdAt) }}
                        </span>
                    </div>
                </template>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('activity.empty') }}
            </p>

            <div class="flex flex-col gap-2 pt-2">
                <AutoTextarea
                    v-model="commentBody"
                    rows="2"
                    :placeholder="$t('comments.placeholder')"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
                <InputError v-if="commentError" :message="commentError" />
                <div>
                    <Button
                        type="button"
                        size="sm"
                        :disabled="commentBody.trim() === ''"
                        @click="postComment"
                    >
                        {{ $t('comments.add') }}
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <Dialog v-model:open="archiveOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    $t('issue.archiveTitle', { identifier: issue.identifier })
                }}</DialogTitle>
                <DialogDescription>
                    {{ $t('issue.archiveDialogBody') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="archive-reason">
                    {{ $t('issue.archiveReason') }}
                    <span class="text-muted-foreground">{{
                        $t('issue.optional')
                    }}</span>
                </Label>
                <AutoTextarea
                    id="archive-reason"
                    v-model="archiveReason"
                    rows="2"
                    :placeholder="$t('issue.archiveReasonExample')"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{
                        $t('common.cancel')
                    }}</Button>
                </DialogClose>
                <Button type="button" @click="archiveIssue">{{
                    $t('issue.archive')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
