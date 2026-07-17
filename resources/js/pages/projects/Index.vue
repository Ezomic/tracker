<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Archive, FileText, Plus, Star, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { board, favorite, index } from '@/routes/projects';
import { index as membersIndex } from '@/routes/projects/members';
import { index as templatesIndex } from '@/routes/projects/templates';
import type { Project } from '@/types';

const props = defineProps<{
    projects: Project[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const usedColors = computed(() =>
    props.projects.map((project) => project.color),
);

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

const newColor = ref(palette[0]);
const createOpen = ref(false);

function archiveLabel(days: number | null): string {
    if (days === null) {
        return 'Never';
    }

    return (
        {
            1: '1 day',
            7: '1 week',
            14: '2 weeks',
            30: '1 month',
        }[days] ?? `${days} days`
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
    <Head title="Projects" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Projects"
                description="Manage projects and star the ones you want in the sidebar"
            />

            <Dialog v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button size="sm" class="shrink-0">
                        <Plus />
                        New project
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
                            <DialogTitle>New project</DialogTitle>
                            <DialogDescription>
                                Add a project with its key, description, repos,
                                and production URL.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="key">Key</Label>
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
                                <Label for="name">Name</Label>
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
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows="2"
                                placeholder="What is this project?"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label>GitHub repos</Label>
                            <RepoInputs :model-value="[]" />
                            <InputError :message="errors.github_repos" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="production_url">Production URL</Label>
                            <Input
                                id="production_url"
                                name="production_url"
                                type="url"
                                placeholder="https://example.com"
                            />
                            <InputError :message="errors.production_url" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Color</Label>
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
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button type="submit" :disabled="processing">
                                Add project
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-for="project in projects"
                :key="project.id"
                class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="shrink-0 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent"
                    :aria-label="project.isFavorite ? 'Unfavorite' : 'Favorite'"
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
                <span
                    class="size-3 shrink-0 rounded-full"
                    :style="{ backgroundColor: project.color }"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-3">
                        <Link
                            :href="board(project.key)"
                            class="shrink-0 font-mono text-sm hover:underline"
                        >
                            {{ project.key }}
                        </Link>
                        <span class="truncate text-sm">{{ project.name }}</span>
                    </div>
                    <p
                        v-if="project.description"
                        class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                    >
                        {{ project.description }}
                    </p>
                </div>
                <span
                    class="flex w-28 shrink-0 items-center justify-end gap-1 text-xs text-muted-foreground"
                    :title="`Auto-archives done issues: ${archiveLabel(project.archiveAfterDays).toLowerCase()}`"
                >
                    <Archive class="size-3.5" />
                    {{ archiveLabel(project.archiveAfterDays) }}
                </span>
                <div class="flex w-24 shrink-0 justify-end">
                    <ProjectLinks :links="project.links" />
                </div>
                <span
                    class="w-16 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                >
                    {{ project.openCount }} open
                </span>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="templatesIndex({ project: project.key })">
                        <FileText class="size-4" />
                        <span class="sr-only">Templates</span>
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="membersIndex({ project: project.key })">
                        <Users class="size-4" />
                        <span class="sr-only">Members</span>
                    </Link>
                </Button>
                <EditProjectDialog
                    :project="project"
                    :palette="palette"
                    :used-colors="
                        projects
                            .filter((other) => other.id !== project.id)
                            .map((other) => other.color)
                    "
                />
            </div>
        </div>
    </div>
</template>
