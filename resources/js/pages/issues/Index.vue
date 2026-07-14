<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import InputError from '@/components/InputError.vue';
import IssueViewToggle from '@/components/IssueViewToggle.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { index, show } from '@/routes/issues';
import type {
    EpicOption,
    Issue,
    IssueFilters,
    IssueLabel,
    Team,
} from '@/types';

const props = defineProps<{
    issues: Issue[];
    teams: Pick<Team, 'id' | 'key' | 'name'>[];
    epics: EpicOption[];
    labels: IssueLabel[];
    filters: IssueFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Issues', href: index() }],
    },
});

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
const isScoped = computed(() => !currentPath.value.startsWith('/issues'));

const scopedProject = computed(() =>
    props.filters.team_id
        ? props.teams.find((team) => team.id === props.filters.team_id)
        : undefined,
);

const heading = computed(() =>
    isScoped.value && scopedProject.value
        ? `${scopedProject.value.key} · Tickets`
        : 'Issues',
);

const search = ref(props.filters.search ?? '');
const teamId = ref(
    props.filters.team_id ? String(props.filters.team_id) : 'all',
);
const status = ref(props.filters.status ?? 'all');
const type = ref(props.filters.type ?? 'all');
const priority = ref(props.filters.priority ?? 'all');
const labelId = ref(
    props.filters.label_id ? String(props.filters.label_id) : 'all',
);

const debouncedSearch = refDebounced(search, 300);

function applyFilters() {
    router.get(
        currentPath.value,
        {
            search: search.value || undefined,
            team_id:
                !isScoped.value && teamId.value !== 'all'
                    ? teamId.value
                    : undefined,
            status: status.value !== 'all' ? status.value : undefined,
            type: type.value !== 'all' ? type.value : undefined,
            priority: priority.value !== 'all' ? priority.value : undefined,
            label_id: labelId.value !== 'all' ? labelId.value : undefined,
        },
        { preserveState: true, replace: true, only: ['issues', 'filters'] },
    );
}

watch(debouncedSearch, applyFilters);
watch([teamId, status, type, priority, labelId], applyFilters);

function clearFilters() {
    search.value = '';
    teamId.value = 'all';
    status.value = 'all';
    type.value = 'all';
    priority.value = 'all';
    labelId.value = 'all';
}

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        (!isScoped.value && teamId.value !== 'all') ||
        status.value !== 'all' ||
        type.value !== 'all' ||
        priority.value !== 'all' ||
        labelId.value !== 'all',
);

const priorityDot: Record<Issue['priority'], string> = {
    none: 'border border-muted-foreground/40',
    low: 'bg-sky-400',
    medium: 'bg-amber-400',
    high: 'bg-orange-500',
    urgent: 'bg-red-500',
};

const statusMeta: {
    value: Issue['status'];
    label: string;
    dot: string;
}[] = [
    { value: 'in_progress', label: 'In progress', dot: 'bg-primary' },
    { value: 'in_review', label: 'In review', dot: 'bg-sky-500' },
    { value: 'backlog', label: 'Backlog', dot: 'bg-muted-foreground/50' },
    { value: 'done', label: 'Done', dot: 'bg-emerald-500' },
];

const groups = computed(() =>
    statusMeta
        .map((meta) => ({
            ...meta,
            issues: props.issues.filter((issue) => issue.status === meta.value),
        }))
        .filter((group) => group.issues.length > 0),
);

const createOpen = ref(false);
</script>

<template>
    <Head title="Issues" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex items-baseline gap-2">
                    <h1 class="text-lg font-medium">{{ heading }}</h1>
                    <span class="text-sm text-muted-foreground">
                        {{ issues.length }}
                    </span>
                </div>
                <IssueViewToggle
                    active="list"
                    :project-key="scopedProject?.key"
                />
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search"
                        class="h-9 w-44 pl-8"
                    />
                </div>

                <Dialog v-model:open="createOpen">
                    <DialogTrigger as-child>
                        <Button size="sm">
                            <Plus />
                            New issue
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>New issue</DialogTitle>
                            <DialogDescription>
                                Create an issue and hand off a ready-to-use
                                branch name.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="IssueController.store.form()"
                            reset-on-success
                            class="grid gap-4"
                            @success="createOpen = false"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="new-team">Project</Label>
                                    <Select
                                        name="team_id"
                                        :default-value="
                                            scopedProject
                                                ? String(scopedProject.id)
                                                : undefined
                                        "
                                    >
                                        <SelectTrigger
                                            id="new-team"
                                            class="w-full"
                                        >
                                            <SelectValue
                                                placeholder="Select a project"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="team in teams"
                                                :key="team.id"
                                                :value="String(team.id)"
                                            >
                                                {{ team.key }} — {{ team.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="errors.team_id" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="new-type">Type</Label>
                                    <Select name="type" default-value="feature">
                                        <SelectTrigger
                                            id="new-type"
                                            class="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="feature">
                                                Feature
                                            </SelectItem>
                                            <SelectItem value="fix">
                                                Fix
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="errors.type" />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="new-parent">Epic (optional)</Label>
                                <Select name="parent_id">
                                    <SelectTrigger
                                        id="new-parent"
                                        class="w-full"
                                    >
                                        <SelectValue placeholder="No epic" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="epic in epics"
                                            :key="epic.id"
                                            :value="String(epic.id)"
                                        >
                                            {{ epic.identifier }} —
                                            {{ epic.title }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.parent_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="new-title">Title</Label>
                                <Input
                                    id="new-title"
                                    name="title"
                                    required
                                    placeholder="Add per-lesson quiz question pools"
                                />
                                <InputError :message="errors.title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="new-description">Description</Label>
                                <textarea
                                    id="new-description"
                                    name="description"
                                    rows="3"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                />
                                <InputError :message="errors.description" />
                            </div>

                            <div class="flex justify-end">
                                <Button type="submit" :disabled="processing">
                                    Create issue
                                </Button>
                            </div>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Select v-if="!isScoped" v-model="teamId">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue placeholder="Project" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All projects</SelectItem>
                    <SelectItem
                        v-for="team in teams"
                        :key="team.id"
                        :value="String(team.id)"
                    >
                        {{ team.key }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="status">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem value="backlog">Backlog</SelectItem>
                    <SelectItem value="in_progress">In progress</SelectItem>
                    <SelectItem value="in_review">In review</SelectItem>
                    <SelectItem value="done">Done</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="type">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All types</SelectItem>
                    <SelectItem value="feature">Feature</SelectItem>
                    <SelectItem value="fix">Fix</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="priority">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All priorities</SelectItem>
                    <SelectItem value="none">No priority</SelectItem>
                    <SelectItem value="low">Low</SelectItem>
                    <SelectItem value="medium">Medium</SelectItem>
                    <SelectItem value="high">High</SelectItem>
                    <SelectItem value="urgent">Urgent</SelectItem>
                </SelectContent>
            </Select>

            <Select v-if="labels.length > 0" v-model="labelId">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue placeholder="Label" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All labels</SelectItem>
                    <SelectItem
                        v-for="label in labels"
                        :key="label.id"
                        :value="String(label.id)"
                    >
                        {{ label.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="clearFilters"
            >
                Clear
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-if="issues.length === 0"
                class="p-10 text-center text-sm text-muted-foreground"
            >
                {{
                    hasActiveFilters
                        ? 'No issues match these filters.'
                        : 'No issues yet — create one with the New issue button.'
                }}
            </div>

            <template v-for="group in groups" :key="group.value">
                <div
                    class="flex items-center gap-2 bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground"
                >
                    <span class="size-2 rounded-full" :class="group.dot" />
                    {{ group.label }}
                    <span class="text-muted-foreground/70">
                        {{ group.issues.length }}
                    </span>
                </div>

                <Link
                    v-for="issue in group.issues"
                    :key="issue.identifier"
                    :href="show({ issue: issue.identifier })"
                    class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-2.5 transition-colors hover:bg-accent dark:border-sidebar-border"
                >
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :class="priorityDot[issue.priority]"
                    />
                    <span
                        class="w-20 shrink-0 font-mono text-xs text-muted-foreground"
                    >
                        {{ issue.identifier }}
                    </span>
                    <span class="truncate text-sm">{{ issue.title }}</span>
                    <span
                        v-if="issue.childrenCount > 0"
                        class="shrink-0 text-xs text-muted-foreground"
                    >
                        {{ issue.childrenCount }} sub
                    </span>
                    <div class="ml-auto flex shrink-0 items-center gap-1.5">
                        <LabelBadge
                            v-for="label in issue.labels"
                            :key="label.id"
                            :name="label.name"
                            :color="label.color"
                        />
                        <Badge variant="outline" class="font-normal">
                            {{ issue.type }}
                        </Badge>
                    </div>
                </Link>
            </template>
        </div>
    </div>
</template>
