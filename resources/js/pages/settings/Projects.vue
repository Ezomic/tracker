<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Settings/ProjectController';
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
import { index } from '@/routes/projects';
import type { Project } from '@/types';

const props = defineProps<{
    projects: Project[];
}>();

const usedColors = computed(() =>
    props.projects.map((project) => project.color),
);

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

const newColor = ref(palette[0]);
const createOpen = ref(false);
</script>

<template>
    <Head title="Projects" />

    <h1 class="sr-only">Projects</h1>

    <div class="flex flex-col space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Projects"
                description="Projects give issues their prefix (e.g. THI-274), color, and independent numbering"
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
                        v-bind="ProjectController.store.form()"
                        reset-on-success
                        class="space-y-6"
                        @success="createOpen = false"
                        v-slot="{ errors, processing }"
                    >
                        <DialogHeader>
                            <DialogTitle>New project</DialogTitle>
                            <DialogDescription>
                                Add a project with its key, color, repos, and
                                production URL.
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
            <p
                v-if="projects.length === 0"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No projects yet — add one with New project.
            </p>
            <div
                v-for="project in projects"
                :key="project.id"
                class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <span
                    class="size-3 shrink-0 rounded-full"
                    :style="{ backgroundColor: project.color }"
                />
                <span class="w-24 font-mono text-sm">{{ project.key }}</span>
                <span class="min-w-0 flex-1 truncate text-sm">
                    {{ project.name }}
                </span>
                <div class="flex w-24 justify-end">
                    <ProjectLinks :links="project.links" />
                </div>
                <span
                    class="w-20 text-right text-xs text-muted-foreground tabular-nums"
                >
                    {{ project.issuesCount }}
                    {{ project.issuesCount === 1 ? 'issue' : 'issues' }}
                </span>
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
