<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import IssueViewToggle from '@/components/IssueViewToggle.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import NewIssueDialog from '@/components/NewIssueDialog.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import SavedViews from '@/components/SavedViews.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { bulk, index, show } from '@/routes/issues';
import type {
    EpicOption,
    Issue,
    IssueFilters,
    IssueLabel,
    Project,
    SavedView,
} from '@/types';

const props = defineProps<{
    issues: Issue[];
    projects: Pick<Project, 'id' | 'key' | 'name' | 'links'>[];
    epics: EpicOption[];
    labels: IssueLabel[];
    filters: IssueFilters;
    savedViews: SavedView[];
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

const { t } = useI18n();

const heading = computed(() =>
    isScoped.value && scopedProject.value
        ? `${scopedProject.value.key} · ${t('list.title')}`
        : t('nav.allIssues'),
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

const currentCriteria = computed<Partial<IssueFilters>>(() => {
    const criteria: Partial<IssueFilters> = {};

    if (search.value) {
        criteria.search = search.value;
    }

    if (!isScoped.value && projectId.value !== 'all') {
        criteria.project_id = Number(projectId.value);
    }

    if (status.value !== 'all') {
        criteria.status = status.value as Issue['status'];
    }

    if (type.value !== 'all') {
        criteria.type = type.value as Issue['type'];
    }

    if (priority.value !== 'all') {
        criteria.priority = priority.value as Issue['priority'];
    }

    if (labelId.value !== 'all') {
        criteria.label_id = Number(labelId.value);
    }

    return criteria;
});

const scopeProjectId = computed(() =>
    isScoped.value && scopedProject.value ? scopedProject.value.id : null,
);

function applyView(criteria: Partial<IssueFilters>) {
    search.value = criteria.search ?? '';
    projectId.value = criteria.project_id ? String(criteria.project_id) : 'all';
    status.value = criteria.status ?? 'all';
    type.value = criteria.type ?? 'all';
    priority.value = criteria.priority ?? 'all';
    labelId.value = criteria.label_id ? String(criteria.label_id) : 'all';
}

const priorityDot: Record<Issue['priority'], string> = {
    none: 'border border-muted-foreground/40',
    low: 'bg-sky-400',
    medium: 'bg-amber-400',
    high: 'bg-orange-500',
    urgent: 'bg-red-500',
};

const statusMeta: {
    value: Issue['status'];
    dot: string;
}[] = [
    { value: 'in_progress', dot: 'bg-primary' },
    { value: 'in_review', dot: 'bg-sky-500' },
    { value: 'backlog', dot: 'bg-muted-foreground/50' },
    { value: 'done', dot: 'bg-emerald-500' },
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

// Selection lives on the page rather than in the URL: it is a thing you are
// doing right now, not a view worth sharing or restoring.
const selected = ref<Set<string>>(new Set());

const selectedCount = computed(() => selected.value.size);

function toggleSelected(identifier: string) {
    const next = new Set(selected.value);

    if (next.has(identifier)) {
        next.delete(identifier);
    } else {
        next.add(identifier);
    }

    selected.value = next;
}

function selectAllInView() {
    selected.value = new Set(props.issues.map((issue) => issue.identifier));
}

function clearSelection() {
    selected.value = new Set();
}

const bulkBusy = ref(false);

function applyBulk(changes: Record<string, unknown>) {
    if (selected.value.size === 0 || bulkBusy.value) {
        return;
    }

    bulkBusy.value = true;

    router.patch(
        bulk().url,
        { issues: [...selected.value], ...changes },
        {
            preserveScroll: true,
            onSuccess: clearSelection,
            onFinish: () => (bulkBusy.value = false),
        },
    );
}
</script>

<template>
    <Head :title="$t('nav.allIssues')" />

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
                        :placeholder="$t('common.search')"
                        class="h-9 w-44 pl-8"
                    />
                </div>

                <Button size="sm" @click="createOpen = true">
                    <Plus />
                    {{ $t('nav.newIssue') }}
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Select v-if="!isScoped" v-model="projectId">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue :placeholder="$t('list.project')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('list.allProjects')
                    }}</SelectItem>
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
                    <SelectItem value="all">{{
                        $t('list.allStatuses')
                    }}</SelectItem>
                    <SelectItem value="backlog">{{
                        $t('status.backlog')
                    }}</SelectItem>
                    <SelectItem value="in_progress">{{
                        $t('status.in_progress')
                    }}</SelectItem>
                    <SelectItem value="in_review">{{
                        $t('status.in_review')
                    }}</SelectItem>
                    <SelectItem value="done">{{
                        $t('status.done')
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="type">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('list.allTypes')
                    }}</SelectItem>
                    <SelectItem value="feature">{{
                        $t('issueType.feature')
                    }}</SelectItem>
                    <SelectItem value="fix">{{
                        $t('issueType.fix')
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="priority">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('list.allPriorities')
                    }}</SelectItem>
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

            <Select v-if="labels.length > 0" v-model="labelId">
                <SelectTrigger class="h-8 w-auto gap-1.5">
                    <SelectValue :placeholder="$t('list.label')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('list.allLabels')
                    }}</SelectItem>
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
                {{ $t('list.clear') }}
            </Button>

            <SavedViews
                class="ml-auto"
                :views="savedViews"
                :criteria="currentCriteria"
                :project-id="scopeProjectId"
                :can-save="hasActiveFilters"
                @apply="applyView"
            />
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
                        ? $t('list.emptyFiltered')
                        : $t('list.emptyNone')
                }}
            </div>

            <template v-for="group in groups" :key="group.value">
                <div
                    class="flex items-center gap-2 bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground"
                >
                    <span class="size-2 rounded-full" :class="group.dot" />
                    {{ $t(`status.${group.value}`) }}
                    <span class="text-muted-foreground/70">
                        {{ group.issues.length }}
                    </span>
                </div>

                <div
                    v-for="issue in group.issues"
                    :key="issue.identifier"
                    class="flex items-center border-t border-sidebar-border/70 transition-colors hover:bg-accent dark:border-sidebar-border"
                    :class="{ 'bg-accent/60': selected.has(issue.identifier) }"
                >
                    <!-- Outside the Link: a checkbox inside one navigates. -->
                    <label
                        class="flex shrink-0 cursor-pointer items-center py-2.5 pl-4"
                    >
                        <Checkbox
                            :model-value="selected.has(issue.identifier)"
                            :aria-label="issue.identifier"
                            @update:model-value="
                                () => toggleSelected(issue.identifier)
                            "
                        />
                    </label>
                    <Link
                        :href="show({ issue: issue.identifier })"
                        class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5"
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
                            <Badge
                                v-if="issue.originatingReport"
                                variant="secondary"
                                class="font-normal"
                                :title="
                                    $t('issue.filedBy', {
                                        source: issue.originatingReport.label,
                                    })
                                "
                            >
                                {{ issue.originatingReport.label }}
                            </Badge>
                            <LabelBadge
                                v-for="label in issue.labels"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                            <Badge variant="outline" class="font-normal">
                                {{ $t(`issueType.${issue.type}`) }}
                            </Badge>
                        </div>
                    </Link>
                </div>
            </template>
        </div>
    </div>

    <div
        v-if="selectedCount > 0"
        class="sticky bottom-4 z-10 mx-4 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-background/95 px-3 py-2 shadow-lg backdrop-blur"
    >
        <span class="text-sm font-medium">
            {{ $t('bulk.selected', { count: selectedCount }) }}
        </span>
        <Button size="sm" variant="ghost" @click="selectAllInView">
            {{ $t('bulk.selectAll') }}
        </Button>
        <Button size="sm" variant="ghost" @click="clearSelection">
            {{ $t('bulk.clear') }}
        </Button>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <Select @update:model-value="(v) => applyBulk({ status: v })">
                <SelectTrigger class="h-8 w-auto gap-1.5" :disabled="bulkBusy">
                    <SelectValue :placeholder="$t('bulk.setStatus')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in statusMeta"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ $t(`status.${option.value}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select @update:model-value="(v) => applyBulk({ priority: v })">
                <SelectTrigger class="h-8 w-auto gap-1.5" :disabled="bulkBusy">
                    <SelectValue :placeholder="$t('bulk.setPriority')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in Object.keys(priorityDot)"
                        :key="option"
                        :value="option"
                    >
                        {{ $t(`priority.${option}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button
                size="sm"
                variant="outline"
                :disabled="bulkBusy"
                @click="applyBulk({ archived: true })"
            >
                {{ $t('bulk.archive') }}
            </Button>
        </div>
    </div>

    <NewIssueDialog
        v-model:open="createOpen"
        :projects="projects"
        :epics="epics"
        :default-project-id="scopedProject?.id ?? null"
    />
</template>
