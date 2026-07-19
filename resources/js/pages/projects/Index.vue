<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Archive, Clock, Plus, Search, Star, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ProjectsController from '@/actions/App/Http/Controllers/ProjectsController';
import ColorSwatches from '@/components/ColorSwatches.vue';
import EditProjectDialog from '@/components/EditProjectDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectLinks from '@/components/ProjectLinks.vue';
import RepoInputs from '@/components/RepoInputs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { formatDuration } from '@/lib/duration';
import { board, favorite, index } from '@/routes/projects';
import { index as membersIndex } from '@/routes/projects/members';
import type { Project, ProjectCategory } from '@/types';

const props = defineProps<{
    projects: Project[];
    categories: ProjectCategory[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const palette = [
    '#d85a30',
    '#e2413f',
    '#ca8a04',
    '#ef9f27',
    '#84a017',
    '#639922',
    '#1d9e75',
    '#0e9aa7',
    '#378add',
    '#4f5bd5',
    '#7f77dd',
    '#9b51e0',
    '#c14bc4',
    '#d4537e',
    '#a1663a',
    '#6b7280',
];

const usedColors = computed(() =>
    props.projects.map((project) => project.color),
);

const { t } = useI18n();

const newColor = ref(palette[0]);
const createOpen = ref(false);

const search = ref('');
const sort = ref('open');

const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    const matches =
        query === ''
            ? [...props.projects]
            : props.projects.filter(
                  (project) =>
                      project.key.toLowerCase().includes(query) ||
                      project.name.toLowerCase().includes(query) ||
                      (project.description ?? '').toLowerCase().includes(query),
              );

    return matches.sort((a, b) => {
        if (sort.value === 'name') {
            return a.name.localeCompare(b.name);
        }

        if (sort.value === 'logged') {
            return b.loggedMinutes - a.loggedMinutes;
        }

        return b.openCount - a.openCount;
    });
});

interface ProjectGroup {
    key: string;
    label: string;
    depth: number;
    items: Project[];
}

const groups = computed<ProjectGroup[]>(() => {
    const favorites = filtered.value.filter((project) => project.isFavorite);
    const rest = filtered.value.filter((project) => !project.isFavorite);
    const result: ProjectGroup[] = [];

    if (favorites.length > 0) {
        result.push({
            key: 'favorites',
            label: t('projects.favorites'),
            depth: 0,
            items: favorites,
        });
    }

    // No categories defined: keep the simple single "All projects" list.
    if (props.categories.length === 0) {
        if (rest.length > 0) {
            result.push({
                key: 'all',
                label: favorites.length > 0 ? t('projects.allProjects') : '',
                depth: 0,
                items: rest,
            });
        }

        return result;
    }

    const byCategory = new Map<number, Project[]>();
    const uncategorized: Project[] = [];

    for (const project of rest) {
        if (project.categoryId === null) {
            uncategorized.push(project);
            continue;
        }

        const bucket = byCategory.get(project.categoryId);

        if (bucket) {
            bucket.push(project);
        } else {
            byCategory.set(project.categoryId, [project]);
        }
    }

    // A category is shown if it, or any descendant, holds a matching project.
    const childrenOf = new Map<number | null, ProjectCategory[]>();

    for (const category of props.categories) {
        const siblings = childrenOf.get(category.parentId) ?? [];
        siblings.push(category);
        childrenOf.set(category.parentId, siblings);
    }

    const subtreeHasProjects = new Map<number, boolean>();
    const compute = (category: ProjectCategory): boolean => {
        let has = (byCategory.get(category.id)?.length ?? 0) > 0;

        for (const child of childrenOf.get(category.id) ?? []) {
            if (compute(child)) {
                has = true;
            }
        }

        subtreeHasProjects.set(category.id, has);

        return has;
    };

    for (const root of childrenOf.get(null) ?? []) {
        compute(root);
    }

    // props.categories is already depth-first, so emitting in order keeps the
    // tree shape; skip branches with no matching projects.
    for (const category of props.categories) {
        if (!subtreeHasProjects.get(category.id)) {
            continue;
        }

        result.push({
            key: `category-${category.id}`,
            label: category.name,
            depth: category.depth,
            items: byCategory.get(category.id) ?? [],
        });
    }

    if (uncategorized.length > 0) {
        result.push({
            key: 'uncategorized',
            label: t('projects.uncategorized'),
            depth: 0,
            items: uncategorized,
        });
    }

    return result;
});

function tint(hex: string): string {
    return `${hex}22`;
}

function archiveLabel(days: number | null): string {
    if (days === null) {
        return t('archiveDuration.never');
    }

    return (
        {
            1: t('archiveDuration.oneDay'),
            7: t('archiveDuration.oneWeek'),
            14: t('archiveDuration.twoWeeks'),
            30: t('archiveDuration.oneMonth'),
        }[days] ?? t('archiveDuration.days', { n: days })
    );
}

function toggleFavorite(project: Project) {
    router.patch(
        favorite(project.key).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head :title="$t('projects.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('projects.title')"
                :description="$t('projects.description')"
            />

            <Dialog v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button size="sm" class="shrink-0">
                        <Plus />
                        {{ $t('projects.newProject') }}
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-lg">
                    <Form
                        v-bind="ProjectsController.store.form()"
                        reset-on-success
                        class="space-y-6"
                        @success="createOpen = false"
                        v-slot="{ errors, processing }"
                    >
                        <DialogHeader>
                            <DialogTitle>{{
                                $t('projects.newProject')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{ $t('projects.addProjectDescription') }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="key">{{
                                    $t('projects.key')
                                }}</Label>
                                <Input
                                    id="key"
                                    name="key"
                                    maxlength="10"
                                    pattern="[A-Z]{2,10}"
                                    class="uppercase"
                                    placeholder="THI"
                                    required
                                />
                                <InputError :message="errors.key" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="name">{{
                                    $t('common.name')
                                }}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="Thijssen Software"
                                    required
                                />
                                <InputError :message="errors.name" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">{{
                                $t('common.description')
                            }}</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows="2"
                                :placeholder="
                                    $t('newIssue.descriptionPlaceholder')
                                "
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ $t('projects.githubRepos') }}</Label>
                            <RepoInputs :model-value="[]" />
                            <InputError :message="errors.github_repos" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="production_url">{{
                                $t('projects.productionUrl')
                            }}</Label>
                            <Input
                                id="production_url"
                                name="production_url"
                                type="url"
                                placeholder="https://example.com"
                            />
                            <InputError :message="errors.production_url" />
                        </div>

                        <div v-if="categories.length > 0" class="grid gap-2">
                            <Label for="category_id">{{
                                $t('projects.category')
                            }}</Label>
                            <Select name="category_id" default-value="">
                                <SelectTrigger id="category_id" class="w-full">
                                    <SelectValue
                                        :placeholder="$t('common.none')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">{{
                                        $t('common.none')
                                    }}</SelectItem>
                                    <SelectItem
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="String(category.id)"
                                    >
                                        {{ '  '.repeat(category.depth)
                                        }}{{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ $t('common.color') }}</Label>
                            <input
                                type="hidden"
                                name="color"
                                :value="newColor"
                            />
                            <ColorSwatches
                                v-model="newColor"
                                :palette="palette"
                                :used="usedColors"
                            />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">{{
                                    $t('common.cancel')
                                }}</Button>
                            </DialogClose>
                            <Button type="submit" :disabled="processing">
                                {{ $t('projects.addProject') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    :placeholder="$t('projects.searchPlaceholder')"
                    class="pl-8"
                />
            </div>
            <Select v-model="sort">
                <SelectTrigger class="w-40 sm:ml-auto">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="open">{{
                        $t('projects.sortMostOpen')
                    }}</SelectItem>
                    <SelectItem value="name">{{
                        $t('projects.sortName')
                    }}</SelectItem>
                    <SelectItem value="logged">{{
                        $t('projects.sortMostLogged')
                    }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <template v-for="group in groups" :key="group.key">
                <div
                    v-if="group.label"
                    class="border-t border-sidebar-border/70 bg-muted/40 px-4 py-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase first:border-t-0 dark:border-sidebar-border"
                    :style="
                        group.depth > 0
                            ? { paddingLeft: `${1 + group.depth * 1.25}rem` }
                            : undefined
                    "
                >
                    {{ group.label }}
                </div>

                <div
                    v-for="project in group.items"
                    :key="project.id"
                    class="group flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-2 transition-colors first:border-t-0 hover:bg-accent/40 dark:border-sidebar-border"
                >
                    <Link
                        :href="board(project.key)"
                        class="shrink-0 rounded px-1.5 py-0.5 font-mono text-[11px] font-medium transition-opacity hover:opacity-80"
                        :style="{
                            backgroundColor: tint(project.color),
                            color: project.color,
                        }"
                    >
                        {{ project.key }}
                    </Link>
                    <span
                        class="min-w-0 flex-1 truncate text-sm"
                        :title="project.description ?? undefined"
                    >
                        {{ project.name }}
                    </span>

                    <span
                        class="hidden w-16 shrink-0 text-right text-xs text-muted-foreground tabular-nums sm:block"
                    >
                        {{ $t('projects.openCount', { n: project.openCount }) }}
                    </span>
                    <span
                        class="hidden w-16 shrink-0 items-center justify-end gap-1 text-xs text-muted-foreground tabular-nums sm:flex"
                        :title="`${formatDuration(project.loggedMinutes)} ${$t('time.logged').toLowerCase()}`"
                    >
                        <template v-if="project.loggedMinutes > 0">
                            <Clock class="size-3.5" />
                            {{ formatDuration(project.loggedMinutes) }}
                        </template>
                    </span>
                    <span
                        class="hidden w-24 shrink-0 items-center justify-end gap-1 text-xs text-muted-foreground md:flex"
                        :title="`${$t('projects.autoArchive')}: ${archiveLabel(project.archiveAfterDays).toLowerCase()}`"
                    >
                        <Archive class="size-3.5" />
                        {{ archiveLabel(project.archiveAfterDays) }}
                    </span>
                    <div class="hidden w-28 shrink-0 justify-end md:flex">
                        <ProjectLinks :links="project.links" />
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent"
                            :aria-label="
                                project.isFavorite
                                    ? $t('projects.unfavorite')
                                    : $t('projects.favorite')
                            "
                            @click="toggleFavorite(project)"
                        >
                            <Star
                                class="size-4"
                                :class="
                                    project.isFavorite
                                        ? 'fill-amber-400 text-amber-400'
                                        : ''
                                "
                            />
                        </button>
                        <div
                            class="flex items-center gap-1 opacity-100 transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                as-child
                            >
                                <Link
                                    :href="
                                        membersIndex({ project: project.key })
                                    "
                                >
                                    <Users class="size-4" />
                                    <span class="sr-only">{{
                                        $t('projects.members')
                                    }}</span>
                                </Link>
                            </Button>
                            <EditProjectDialog
                                :project="project"
                                :palette="palette"
                                :categories="categories"
                                :used-colors="
                                    projects
                                        .filter(
                                            (other) => other.id !== project.id,
                                        )
                                        .map((other) => other.color)
                                "
                            />
                        </div>
                    </div>
                </div>
            </template>

            <p
                v-if="filtered.length === 0"
                class="px-4 py-10 text-center text-sm text-muted-foreground"
            >
                {{
                    projects.length === 0
                        ? $t('projects.empty')
                        : $t('projects.noMatch', { search })
                }}
            </p>
        </div>
    </div>
</template>
