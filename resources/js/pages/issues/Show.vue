<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { GitBranch, GitCommit, GitPullRequest } from '@lucide/vue';
import { computed, ref } from 'vue';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import InputError from '@/components/InputError.vue';
import LabelBadge from '@/components/LabelBadge.vue';
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
import { index, show } from '@/routes/issues';
import type { EpicOption, Issue, IssueLabel } from '@/types';

const props = defineProps<{
    issue: Issue;
    epics: EpicOption[];
    labels: IssueLabel[];
}>();

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
                    <textarea
                        id="description"
                        v-model="description"
                        name="description"
                        rows="6"
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
</template>
