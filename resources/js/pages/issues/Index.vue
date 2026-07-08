<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LabelBadge from '@/components/LabelBadge.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show } from '@/routes/issues';
import type { EpicOption, Issue, IssueLabel, Team } from '@/types';

defineProps<{
    issues: Issue[];
    teams: Pick<Team, 'id' | 'key' | 'name'>[];
    epics: EpicOption[];
    labels: IssueLabel[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Issues',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Issues" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <Heading
            title="Issues"
            description="Create issues and hand off a ready-to-use branch name"
        />

        <Form
            v-bind="IssueController.store.form()"
            reset-on-success
            class="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="team_id">Team</Label>
                    <Select name="team_id">
                        <SelectTrigger id="team_id" class="w-full">
                            <SelectValue placeholder="Select a team" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="team in teams"
                                :key="team.id"
                                :value="String(team.id)"
                            >
                                {{ team.key }} - {{ team.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.team_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <Select name="type" default-value="feature">
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
            </div>

            <div class="grid gap-2">
                <Label for="parent_id">Epic (optional)</Label>
                <Select name="parent_id">
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

            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input
                    id="title"
                    name="title"
                    required
                    placeholder="Add per-lesson quiz question pools"
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                />
                <InputError :message="errors.description" />
            </div>

            <div>
                <Button type="submit" :disabled="processing">
                    Create issue
                </Button>
            </div>
        </Form>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Identifier</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Priority</TableHead>
                    <TableHead>Labels</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty v-if="issues.length === 0" :colspan="6">
                    No issues yet - create one above.
                </TableEmpty>
                <TableRow v-for="issue in issues" :key="issue.identifier">
                    <TableCell class="font-mono">
                        <Link
                            :href="show({ issue: issue.identifier })"
                            class="hover:underline"
                        >
                            {{ issue.identifier }}
                        </Link>
                    </TableCell>
                    <TableCell>
                        {{ issue.title }}
                        <span
                            v-if="issue.childrenCount > 0"
                            class="ml-2 text-xs text-muted-foreground"
                        >
                            ({{ issue.childrenCount }} sub-issue{{
                                issue.childrenCount === 1 ? '' : 's'
                            }})
                        </span>
                    </TableCell>
                    <TableCell>
                        <Badge variant="outline">{{ issue.type }}</Badge>
                    </TableCell>
                    <TableCell>
                        <PriorityBadge :priority="issue.priority" />
                    </TableCell>
                    <TableCell>
                        <div class="flex flex-wrap gap-1">
                            <LabelBadge
                                v-for="label in issue.labels"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                        </div>
                    </TableCell>
                    <TableCell>
                        <Badge variant="secondary">{{ issue.status }}</Badge>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
