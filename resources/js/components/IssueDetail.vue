<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    Bot,
    Check,
    Eye,
    EyeOff,
    ExternalLink,
    FileText,
    GitBranch,
    GitCommit,
    GitPullRequest,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AutoTextarea from '@/components/AutoTextarea.vue';
import InputError from '@/components/InputError.vue';
import IssuePropertyBar from '@/components/IssuePropertyBar.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/composables/useInitials';
import {
    archive,
    show,
    unarchive,
    watch as watchIssue,
    update,
    updateStatus,
} from '@/routes/issues';
import {
    destroy as destroyComment,
    update as updateComment,
    store as storeComment,
} from '@/routes/issues/comments';
import { destroy as destroyLink } from '@/routes/issues/links';
import type {
    EpicOption,
    Issue,
    IssueComment,
    IssueLabel,
    IssueUser,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    issue: Issue;
    timeline: TimelineItem[];
    epics: EpicOption[];
    labels: IssueLabel[];
    members: IssueUser[];
    canModerateComments: boolean;
    canArchive: boolean;
    currentUserId: number;
}>();

const { t } = useI18n();

const form = reactive({
    title: props.issue.title,
    description: props.issue.description ?? '',
    type: props.issue.type,
    priority: props.issue.priority,
    // "" and "unassigned" stand in for null; the request normalises both back.
    parentId: props.issue.parent ? String(props.issue.parent.id) : '',
    assigneeId: props.issue.assignee
        ? String(props.issue.assignee.id)
        : 'unassigned',
    labels: props.issue.labels.map((label) => label.id),
});

const saveState = ref<'idle' | 'saving' | 'saved'>('idle');
const errors = ref<Record<string, string>>({});

// The update endpoint keeps full-form semantics (an omitted `labels` clears
// them), so every autosave sends the whole payload rather than a partial patch.
const save = useDebounceFn(() => {
    router.patch(
        update({ issue: props.issue.identifier }).url,
        {
            title: form.title,
            description: form.description,
            type: form.type,
            priority: form.priority,
            parent_id: form.parentId,
            assignee_id:
                form.assigneeId === 'unassigned' ? '' : form.assigneeId,
            labels: form.labels,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saveState.value = 'saving';
            },
            onSuccess: () => {
                errors.value = {};
                saveState.value = 'saved';
            },
            onError: (received) => {
                errors.value = received;
                saveState.value = 'idle';
            },
        },
    );
}, 600);

watch(form, save);

// Status has its own endpoint because a plain PATCH ignores the field.
const status = ref(props.issue.status);

watch(status, (value) => {
    router.patch(
        updateStatus({ issue: props.issue.identifier }).url,
        { status: value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saveState.value = 'saving';
            },
            onSuccess: () => {
                saveState.value = 'saved';
            },
        },
    );
});

function toggleLabel(id: number, checked: boolean) {
    form.labels = checked
        ? [...form.labels, id]
        : form.labels.filter((labelId) => labelId !== id);
}

const selectedLabels = computed(() =>
    props.labels.filter((label) => form.labels.includes(label.id)),
);

const archiveOpen = ref(false);
const archiveReason = ref('');

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

// Grouped by relation so "Blocked by" reads as a list rather than a repeated
// label on every row.
const groupedLinks = computed(() => {
    const groups = new Map<
        string,
        { relation: string; label: string; links: Issue['links'] }
    >();

    for (const link of props.issue.links) {
        const group = groups.get(link.relation) ?? {
            relation: link.relation,
            label: link.label,
            links: [],
        };
        group.links.push(link);
        groups.set(link.relation, group);
    }

    return [...groups.values()];
});

function removeLink(id: number) {
    router.delete(
        destroyLink({ issue: props.issue.identifier, link: id }).url,
        {
            preserveScroll: true,
        },
    );
}

function toggleWatch() {
    const url = watchIssue({ issue: props.issue.identifier }).url;

    if (props.issue.watching) {
        router.delete(url, { preserveScroll: true });

        return;
    }

    router.post(url, {}, { preserveScroll: true });
}

function unarchiveIssue() {
    router.post(
        unarchive({ issue: props.issue.identifier }).url,
        {},
        { preserveScroll: true },
    );
}

const commentBody = ref('');
const commentError = ref<string | null>(null);

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
            onError: (received) => {
                commentError.value = received.body ?? null;
            },
        },
    );
}

const editingCommentId = ref<number | null>(null);
const editingBody = ref('');

function startEditing(comment: { id: number; body: string }) {
    editingCommentId.value = comment.id;
    editingBody.value = comment.body;
}

function cancelEditing() {
    editingCommentId.value = null;
    editingBody.value = '';
}

function saveComment(comment: { id: number }) {
    if (editingBody.value.trim() === '') {
        return;
    }

    router.patch(
        updateComment({
            issue: props.issue.identifier,
            comment: comment.id,
        }).url,
        { body: editingBody.value },
        { preserveScroll: true, onSuccess: cancelEditing },
    );
}

function removeComment(comment: IssueComment) {
    router.delete(
        destroyComment({ issue: props.issue.identifier, comment: comment.id })
            .url,
        { preserveScroll: true },
    );
}

const canRemoveComment = (comment: IssueComment) =>
    props.canModerateComments || comment.user?.id === props.currentUserId;

// Only the author edits. A moderator can remove a comment but not rewrite it.
const canEditComment = (comment: { user: IssueUser | null }) =>
    comment.user?.id === props.currentUserId;

function formatTimestamp(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

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
        case 'priority_changed':
            return t('activity.priorityTo', {
                priority: t(`priority.${data.to}`),
            });
        case 'type_changed':
            return t('activity.typeTo', {
                type: t(`issueType.${data.to}`),
            });
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

const doneChildrenCount = computed(
    () =>
        props.issue.children.filter((child) => child.status === 'done').length,
);

const childProgress = computed(() =>
    props.issue.children.length === 0
        ? 0
        : Math.round(
              (doneChildrenCount.value / props.issue.children.length) * 100,
          ),
);

const statusDot: Record<Issue['status'], string> = {
    backlog: 'bg-muted-foreground/50',
    in_progress: 'bg-primary',
    in_review: 'bg-sky-500',
    done: 'bg-emerald-500',
};
</script>

<template>
    <div
        class="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-2.5 text-sm dark:border-sidebar-border"
    >
        <span class="text-muted-foreground">{{ issue.project.key }}</span>
        <span class="text-muted-foreground/50">/</span>
        <span class="font-mono text-muted-foreground">
            {{ issue.identifier }}
        </span>
        <span
            v-if="issue.archivedAt"
            class="ml-1 inline-flex items-center gap-1.5 rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground"
            :title="issue.archiveReason ?? undefined"
        >
            <Archive class="size-3" />
            {{ $t('issue.archived') }}
        </span>

        <div class="ml-auto flex items-center gap-3">
            <span
                v-if="saveState !== 'idle'"
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Check
                    v-if="saveState === 'saved'"
                    class="size-3.5 text-emerald-500"
                />
                {{
                    saveState === 'saving'
                        ? $t('issue.saving')
                        : $t('issue.saved')
                }}
            </span>

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
        class="px-4 pt-4 text-sm text-muted-foreground"
    >
        <span class="font-medium text-foreground">
            {{ $t('issue.archivedBanner') }}
        </span>
        {{ issue.archiveReason }}
    </p>

    <div class="grid flex-1 items-start lg:grid-cols-[minmax(0,1fr)_272px]">
        <div class="flex min-w-0 flex-col gap-6 p-4 lg:p-6">
            <div class="grid gap-1.5">
                <Input
                    v-model="form.title"
                    required
                    class="h-auto border-0 bg-transparent px-0 py-0 text-xl font-medium shadow-none focus-visible:ring-0 dark:bg-transparent"
                />
                <p v-if="issue.parent" class="text-sm text-muted-foreground">
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
                <IssuePropertyBar
                    v-model:status="status"
                    v-model:priority="form.priority"
                    v-model:type="form.type"
                    v-model:assignee-id="form.assigneeId"
                    :members="members"
                />
                <InputError :message="errors.assignee_id" />
            </div>

            <div class="grid gap-1.5">
                <Label for="description" class="text-xs text-muted-foreground">
                    {{ $t('common.description') }}
                </Label>
                <MarkdownEditor
                    v-model="form.description"
                    name="description"
                    :rows="3"
                    :placeholder="$t('newIssue.descriptionPlaceholder')"
                />
                <InputError :message="errors.description" />
            </div>

            <div
                v-if="issue.children.length > 0"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/70 bg-muted/50 px-3 py-2 dark:border-sidebar-border"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ $t('issue.subIssues') }}
                    </span>
                    <div class="ml-auto flex items-center gap-2">
                        <div
                            class="h-1 w-16 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-500"
                                :style="{ width: `${childProgress}%` }"
                            />
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{
                                $t('issue.subDone', {
                                    done: doneChildrenCount,
                                    total: issue.children.length,
                                })
                            }}
                        </span>
                    </div>
                </div>
                <Link
                    v-for="child in issue.children"
                    :key="child.identifier"
                    :href="show({ issue: child.identifier })"
                    class="flex items-center gap-2 border-b border-sidebar-border/70 px-3 py-2 text-sm last:border-b-0 hover:bg-accent dark:border-sidebar-border"
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

            <div
                class="flex flex-col gap-4 border-t border-sidebar-border/70 pt-5 dark:border-sidebar-border"
            >
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
                                        {{
                                            item.user?.name ??
                                            $t('issue.unknown')
                                        }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatTimestamp(item.createdAt) }}
                                    </span>
                                    <span
                                        v-if="item.editedAt"
                                        class="text-xs text-muted-foreground"
                                        :title="formatTimestamp(item.editedAt)"
                                    >
                                        · {{ $t('activity.edited') }}
                                    </span>
                                    <Button
                                        v-if="
                                            canEditComment(item) &&
                                            editingCommentId !== item.id
                                        "
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="ml-auto size-7 shrink-0 text-muted-foreground"
                                        @click="startEditing(item)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canRemoveComment(item)"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                        :class="{
                                            'ml-auto':
                                                !canEditComment(item) ||
                                                editingCommentId === item.id,
                                        }"
                                        @click="removeComment(item)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                                <div
                                    v-if="editingCommentId === item.id"
                                    class="mt-1 flex flex-col gap-2"
                                >
                                    <AutoTextarea
                                        v-model="editingBody"
                                        class="text-sm"
                                    />
                                    <div class="flex gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            @click="saveComment(item)"
                                        >
                                            {{ $t('activity.save') }}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            @click="cancelEditing"
                                        >
                                            {{ $t('common.cancel') }}
                                        </Button>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="mt-0.5 text-sm whitespace-pre-wrap"
                                >
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
                                    {{
                                        item.user?.name ??
                                        $t('activity.someone')
                                    }}
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
        </div>

        <aside
            class="flex flex-col gap-6 p-4 lg:sticky lg:top-0 lg:border-l lg:border-sidebar-border/70 lg:p-6 dark:lg:border-sidebar-border"
        >
            <div v-if="labels.length > 0" class="grid gap-2">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.labels') }}
                </Label>
                <div class="flex flex-wrap items-center gap-1.5">
                    <LabelBadge
                        v-for="label in selectedLabels"
                        :key="label.id"
                        :name="label.name"
                        :color="label.color"
                    />
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="inline-flex size-6 items-center justify-center rounded-full border border-dashed border-border text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            :aria-label="$t('issue.addLabel')"
                        >
                            <Plus class="size-3.5" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuCheckboxItem
                                v-for="label in labels"
                                :key="label.id"
                                :model-value="form.labels.includes(label.id)"
                                @update:model-value="
                                    (checked) => toggleLabel(label.id, checked)
                                "
                                @select.prevent
                            >
                                <LabelBadge
                                    :name="label.name"
                                    :color="label.color"
                                />
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <InputError :message="errors.labels" />
            </div>

            <div v-if="issue.children.length === 0" class="grid gap-1.5">
                <Label for="parent_id" class="text-xs text-muted-foreground">
                    {{ $t('issue.epic') }}
                </Label>
                <Select v-model="form.parentId">
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

            <div class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.owner') }}
                </Label>
                <p class="text-sm">
                    {{ issue.owner?.name ?? $t('issue.unknown') }}
                </p>
            </div>

            <div v-if="groupedLinks.length > 0" class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.links') }}
                </Label>
                <div
                    v-for="group in groupedLinks"
                    :key="group.relation"
                    class="grid gap-1"
                >
                    <span class="text-xs text-muted-foreground">{{
                        group.label
                    }}</span>
                    <div
                        v-for="link in group.links"
                        :key="link.id"
                        class="flex items-center gap-1.5 text-sm"
                    >
                        <span
                            class="size-1.5 shrink-0 rounded-full"
                            :class="
                                link.issue.status === 'done'
                                    ? 'bg-emerald-500'
                                    : 'bg-muted-foreground/50'
                            "
                        />
                        <Link
                            :href="show({ issue: link.issue.identifier })"
                            class="truncate hover:underline"
                            :title="link.issue.title"
                        >
                            <span class="font-mono text-xs">{{
                                link.issue.identifier
                            }}</span>
                            {{ link.issue.title }}
                        </Link>
                        <button
                            type="button"
                            class="ml-auto shrink-0 text-muted-foreground hover:text-destructive"
                            @click="removeLink(link.id)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.watching') }}
                </Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="justify-start"
                    @click="toggleWatch"
                >
                    <Eye v-if="issue.watching" class="size-4" />
                    <EyeOff v-else class="size-4" />
                    <span>{{
                        issue.watching
                            ? $t('issue.watchingOn')
                            : $t('issue.watchingOff')
                    }}</span>
                    <span
                        v-if="issue.watcherCount > 0"
                        class="ml-auto text-xs text-muted-foreground"
                        >{{ issue.watcherCount }}</span
                    >
                </Button>
            </div>

            <div v-if="issue.originatingReport" class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.reportedFrom') }}
                </Label>
                <a
                    v-if="issue.originatingReport.url"
                    :href="issue.originatingReport.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 text-sm hover:underline"
                >
                    <ExternalLink
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <span class="truncate"
                        >{{ issue.originatingReport.label
                        }}<template v-if="issue.originatingReport.reference">
                            #{{ issue.originatingReport.reference }}</template
                        ></span
                    >
                </a>
                <p v-else class="inline-flex items-center gap-1.5 text-sm">
                    <ExternalLink
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <span class="truncate"
                        >{{ issue.originatingReport.label
                        }}<template v-if="issue.originatingReport.reference">
                            #{{ issue.originatingReport.reference }}</template
                        ></span
                    >
                </p>
            </div>

            <div v-if="issue.externalReporter" class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.externalReporter') }}
                </Label>
                <p class="inline-flex items-center gap-1.5 text-sm">
                    <Bot class="size-4 shrink-0 text-muted-foreground" />
                    <span class="truncate">{{ issue.externalReporter }}</span>
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ $t('issue.externalReporterHint') }}
                </p>
            </div>

            <div v-if="issue.template" class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">
                    {{ $t('issue.template') }}
                </Label>
                <p class="inline-flex items-center gap-1.5 text-sm">
                    <FileText class="size-4 shrink-0 text-muted-foreground" />
                    <span class="truncate">{{ issue.template.name }}</span>
                </p>
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

    <Dialog v-model:open="archiveOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        $t('issue.archiveTitle', {
                            identifier: issue.identifier,
                        })
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{ $t('issue.archiveDialogBody') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="archive-reason">
                    {{ $t('issue.archiveReason') }}
                    <span class="text-muted-foreground">
                        {{ $t('issue.optional') }}
                    </span>
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
                    <Button type="button" variant="secondary">
                        {{ $t('common.cancel') }}
                    </Button>
                </DialogClose>
                <Button type="button" @click="archiveIssue">
                    {{ $t('issue.archive') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
