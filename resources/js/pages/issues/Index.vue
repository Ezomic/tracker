<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import IssueViewToggle from '@/components/IssueViewToggle.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import NewIssueDialog from '@/components/NewIssueDialog.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    Project,
} from '@/types';

const props = defineProps<{
    issues: Issue[];
    projects: Pick<Project, 'id' | 'key' | 'name' | 'links'>[];
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
    props.filters.project_id
        ? props.projects.find(
              (project) => project.id === props.filters.project_id,
          )
        : undefined,
);

const heading = computed(() =>
    isScoped.value && scopedProject.value
        ? `${scopedProject.value.key} · Tickets`
        : 'Issues',
);

const search = ref(props.filters.search ?? '');
const projectId = ref(
    props.filters.project_id ? String(props.filters.project_id) : 'all',
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
            project_id:
                !isScoped.value && projectId.value !== 'all'
                    ? projectId.value
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
watch([projectId, status, type, priority, labelId], applyFilters);

function clearFilters() {
    search.value = '';
    projectId.value = 'all';
    status.value = 'all';
    type.value = 'all';
    priority.value = 'all';
    labelId.value = 'all';
}

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        (!isScoped.value && projectId.value !== 'all') ||
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
                <ProjectLinks
                    v-if="scopedProject"
                    :links="scopedProject.links"
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

                <Button size="sm" @click="createOpen = true">
                    <Plus />
                    New issue
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Select v-if="!isScoped" v-model="projectId">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue placeholder="Project" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All projects</SelectItem>
                    <SelectItem
                        v-for="project in projects"
                        :key="project.id"
                        :value="String(project.id)"
                    >
                        {{ project.key }}
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

    <NewIssueDialog
        v-model:open="createOpen"
        :projects="projects"
        :epics="epics"
        :default-project-id="scopedProject?.id ?? null"
    />
</template>
