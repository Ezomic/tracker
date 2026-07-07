<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
import { index } from '@/routes/issues';
import type { Issue } from '@/types';

defineProps<{
    issue: Issue;
}>();

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
