<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PriorityBadge from '@/components/PriorityBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { EpicOption, Issue } from '@/types';

const props = defineProps<{
    issue: Issue;
    epics: EpicOption[];
}>();

const doneChildrenCount = computed(
    () =>
        props.issue.children.filter((child) => child.status === 'done').length,
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Issues', href: index() }],
    },
});
</script>

<template>
    <Head :title="issue.identifier" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <div class="flex flex-col gap-2">
            <Heading
                :title="`${issue.identifier} - ${issue.title}`"
                :description="`${issue.team.key} - ${issue.team.name}`"
            />
            <p v-if="issue.parent" class="text-sm text-muted-foreground">
                Part of
                <Link
                    :href="show({ issue: issue.parent.identifier })"
                    class="hover:underline"
                    >{{ issue.parent.identifier }} -
                    {{ issue.parent.title }}</Link
                >
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{{ issue.type }}</Badge>
                <PriorityBadge :priority="issue.priority" />
                <Badge variant="secondary">{{ issue.status }}</Badge>
                <Badge v-if="issue.archivedAt" variant="outline"
                    >Archived</Badge
                >
                <a
                    v-if="issue.githubPrUrl"
                    :href="issue.githubPrUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-muted-foreground hover:underline"
                >
                    View PR
                </a>
            </div>
        </div>

        <div
            class="grid gap-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <Label>Branch name</Label>
            <code class="text-sm text-muted-foreground">{{
                issue.branchName
            }}</code>
        </div>

        <div
            v-if="issue.children.length > 0"
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between">
                <Label>Sub-issues</Label>
                <span class="text-sm text-muted-foreground"
                    >{{ doneChildrenCount }} of
                    {{ issue.children.length }} done</span
                >
            </div>
            <Link
                v-for="child in issue.children"
                :key="child.identifier"
                :href="show({ issue: child.identifier })"
                class="flex items-center justify-between rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm hover:bg-accent dark:border-sidebar-border"
            >
                <span
                    ><span class="font-mono text-xs text-muted-foreground">{{
                        child.identifier
                    }}</span>
                    {{ child.title }}</span
                >
                <Badge variant="secondary">{{ child.status }}</Badge>
            </Link>
        </div>

        <Form
            v-bind="IssueController.update.form({ issue: issue.identifier })"
            class="grid max-w-xl gap-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input
                    id="title"
                    name="title"
                    :default-value="issue.title"
                    required
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="type">Type</Label>
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

            <div class="grid gap-2">
                <Label for="priority">Priority</Label>
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

            <div v-if="issue.children.length === 0" class="grid gap-2">
                <Label for="parent_id">Epic</Label>
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
                            {{ epic.identifier }} - {{ epic.title }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.parent_id" />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                This issue has sub-issues, so it can't be assigned to an epic
                itself.
            </p>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    :default-value="issue.description ?? ''"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
                <InputError :message="errors.description" />
            </div>

            <div>
                <Button type="submit" :disabled="processing">
                    Save changes
                </Button>
            </div>
        </Form>
    </div>
</template>
