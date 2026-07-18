<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { GitBranch, GitCommit, GitPullRequest, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import AutoTextarea from '@/components/AutoTextarea.vue';
import InputError from '@/components/InputError.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDuration } from '@/lib/duration';
import { index, show } from '@/routes/issues';
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
} from '@/types';

const props = defineProps<{
    issue: Issue;
    epics: EpicOption[];
    labels: IssueLabel[];
    members: IssueUser[];
    canLogTime: boolean;
    canManageTime: boolean;
    canModerateComments: boolean;
    currentUserId: number;
}>();

const estimateDefault = props.issue.estimateMinutes
    ? formatDuration(props.issue.estimateMinutes)
    : '';

const duration = ref('');
const spentOn = ref(new Date().toISOString().slice(0, 10));
const note = ref('');
const timeError = ref<string | null>(null);

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

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function formatTimestamp(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Issues', href: index() }],
    },
});

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

const statusMeta: Record<Issue['status'], { label: string; dot: string }> = {
    backlog: { label: 'Backlog', dot: 'bg-muted-foreground/50' },
    in_progress: { label: 'In progress', dot: 'bg-primary' },
    in_review: { label: 'In review', dot: 'bg-sky-500' },
    done: { label: 'Done', dot: 'bg-emerald-500' },
};
</script>

<template>
    <Head :title="issue.identifier" />

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
                    :class="statusMeta[issue.status].dot"
                />
                {{ statusMeta[issue.status].label }}
            </span>
            <span
                v-if="issue.archivedAt"
                class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground"
            >
                Archived
            </span>
        </div>

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
                        Part of
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
                        Description
                    </Label>
                    <AutoTextarea
                        id="description"
                        v-model="description"
                        name="description"
                        rows="3"
                        placeholder="Add a description…"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div
                    v-if="issue.children.length > 0"
                    class="grid gap-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">
                            Sub-issues
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ doneChildrenCount }} of
                            {{ issue.children.length }} done
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
                            :class="statusMeta[child.status].dot"
                        />
                        <span class="font-mono text-xs text-muted-foreground">
                            {{ child.identifier }}
                        </span>
                        <span class="truncate">{{ child.title }}</span>
                    </Link>
                </div>

                <div>
                    <Button type="submit" :disabled="processing">
                        Save changes
                    </Button>
                </div>
            </div>

            <aside
                class="flex flex-col gap-4 lg:border-l lg:border-sidebar-border/70 lg:pl-6 dark:lg:border-sidebar-border"
            >
                <div class="grid gap-1.5">
                    <Label for="assignee" class="text-xs text-muted-foreground">
                        Assignee
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
                                Unassigned
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
                    <Label class="text-xs text-muted-foreground">Owner</Label>
                    <p class="text-sm">
                        {{ issue.owner?.name ?? 'Unknown' }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="type" class="text-xs text-muted-foreground">
                        Type
                    </Label>
                    <Select name="type" :default-value="issue.type">
                        <SelectTrigger id="type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="feature">Feature</SelectItem>
                            <SelectItem value="fix">Fix</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="priority" class="text-xs text-muted-foreground">
                        Priority
                    </Label>
                    <Select name="priority" :default-value="issue.priority">
                        <SelectTrigger id="priority" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No priority</SelectItem>
                            <SelectItem value="low">Low</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="urgent">Urgent</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.priority" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="estimate" class="text-xs text-muted-foreground">
                        Estimate
                    </Label>
                    <Input
                        id="estimate"
                        name="estimate"
                        :default-value="estimateDefault"
                        placeholder="e.g. 4h 30m"
                    />
                    <InputError :message="errors.estimate" />
                </div>

                <div v-if="issue.children.length === 0" class="grid gap-1.5">
                    <Label
                        for="parent_id"
                        class="text-xs text-muted-foreground"
                    >
                        Epic
                    </Label>
                    <Select
                        name="parent_id"
                        :default-value="
                            issue.parent ? String(issue.parent.id) : undefined
                        "
                    >
                        <SelectTrigger id="parent_id" class="w-full">
                            <SelectValue placeholder="No epic" />
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
                    <Label class="text-xs text-muted-foreground">Labels</Label>
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
                        Development
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
                        Commits
                    </a>

                    <a
                        v-if="issue.githubPrUrl"
                        :href="issue.githubPrUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <GitPullRequest class="size-4 shrink-0" />
                        Pull request
                    </a>
                </div>
            </aside>
        </div>
    </Form>

    <section class="p-4 pt-0">
        <div
            class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 lg:max-w-2xl dark:border-sidebar-border"
        >
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-medium">Time</h2>
                <p class="text-sm text-muted-foreground">
                    <span
                        class="font-medium"
                        :class="
                            overEstimate
                                ? 'text-destructive'
                                : 'text-foreground'
                        "
                    >
                        {{ formatDuration(issue.loggedMinutes) }}
                    </span>
                    <template v-if="issue.estimateMinutes">
                        of {{ formatDuration(issue.estimateMinutes) }}
                    </template>
                    logged
                </p>
            </div>

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
                            {{ entry.user?.name ?? 'Unknown' }} ·
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
                No time logged yet.
            </p>

            <div
                v-if="canLogTime"
                class="flex flex-wrap items-end gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
            >
                <div class="grid gap-1.5">
                    <Label
                        for="log-duration"
                        class="text-xs text-muted-foreground"
                    >
                        Duration
                    </Label>
                    <Input
                        id="log-duration"
                        v-model="duration"
                        class="w-28"
                        placeholder="1h 30m"
                        @keydown.enter.prevent="logTime"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="log-date" class="text-xs text-muted-foreground">
                        Date
                    </Label>
                    <Input
                        id="log-date"
                        v-model="spentOn"
                        type="date"
                        class="w-40"
                    />
                </div>
                <div class="grid flex-1 gap-1.5">
                    <Label for="log-note" class="text-xs text-muted-foreground">
                        Note (optional)
                    </Label>
                    <Input
                        id="log-note"
                        v-model="note"
                        placeholder="What did you work on?"
                        @keydown.enter.prevent="logTime"
                    />
                </div>
                <Button type="button" @click="logTime">Log time</Button>
            </div>
            <InputError v-if="timeError" :message="timeError" />
        </div>
    </section>

    <section class="p-4 pt-0">
        <div class="flex flex-col gap-4 lg:max-w-2xl">
            <h2 class="text-sm font-medium">
                Comments
                <span
                    v-if="issue.comments.length > 0"
                    class="text-muted-foreground"
                >
                    ({{ issue.comments.length }})
                </span>
            </h2>

            <div v-if="issue.comments.length > 0" class="flex flex-col gap-4">
                <div
                    v-for="comment in issue.comments"
                    :key="comment.id"
                    class="flex gap-3"
                >
                    <Avatar class="size-8 shrink-0">
                        <AvatarFallback class="text-xs">
                            {{ initials(comment.user?.name ?? '?') }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">
                                {{ comment.user?.name ?? 'Unknown' }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatTimestamp(comment.createdAt) }}
                            </span>
                            <Button
                                v-if="canRemoveComment(comment)"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="ml-auto size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                @click="removeComment(comment)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                        <p class="mt-0.5 text-sm whitespace-pre-wrap">
                            {{ comment.body }}
                        </p>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">No comments yet.</p>

            <div class="flex flex-col gap-2 pt-2">
                <AutoTextarea
                    v-model="commentBody"
                    rows="2"
                    placeholder="Leave a comment…"
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
                        Comment
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
