<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowLeft, FilePlus2, FileText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import AutoTextarea from '@/components/AutoTextarea.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
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
import { options as templateOptions } from '@/routes/projects/templates';
import type { EpicOption, IssueTemplate, Project } from '@/types';

const props = defineProps<{
    projects: Pick<Project, 'id' | 'key' | 'name'>[];
    // Omitted when the dialog is opened outside a project's issue list, where
    // there is no sensible epic list to offer.
    epics?: EpicOption[];
    defaultProjectId?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const step = ref<'choose' | 'form'>('choose');
const projectId = ref<string | undefined>();
const templates = ref<IssueTemplate[]>([]);
const loadingTemplates = ref(false);
const chosen = ref<IssueTemplate | null>(null);

const description = ref('');
const type = ref('feature');

const selectedProject = computed(() =>
    props.projects.find((project) => String(project.id) === projectId.value),
);

const typeLabels: Record<string, string> = { feature: 'Feature', fix: 'Fix' };
const priorityLabels: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

// Reset each time it opens, so navigating between projects re-preselects and a
// previous pick doesn't linger.
watch(open, (isOpen) => {
    if (isOpen) {
        step.value = 'choose';
        chosen.value = null;
        projectId.value = props.defaultProjectId
            ? String(props.defaultProjectId)
            : undefined;
    }
});

// Bodies are fetched per project rather than shipped on every page load.
watch([open, projectId], async ([isOpen, id]) => {
    templates.value = [];

    if (!isOpen || !id) {
        return;
    }

    const project = props.projects.find((item) => String(item.id) === id);

    if (!project) {
        return;
    }

    loadingTemplates.value = true;

    try {
        const response = await fetch(
            templateOptions({ project: project.key }).url,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            templates.value = await response.json();
        }
    } finally {
        loadingTemplates.value = false;
    }
});

function choose(template: IssueTemplate | null) {
    chosen.value = template;
    description.value = template?.description ?? '';
    type.value = template?.type ?? 'feature';
    step.value = 'form';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>New issue</DialogTitle>
                <DialogDescription>
                    {{
                        step === 'choose'
                            ? 'Start from a template, or a blank issue.'
                            : 'Create an issue and hand off a ready-to-use branch name.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="step === 'choose'" class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="choose-project">Project</Label>
                    <Select v-model="projectId">
                        <SelectTrigger id="choose-project" class="w-full">
                            <SelectValue placeholder="Select a project" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="project in projects"
                                :key="project.id"
                                :value="String(project.id)"
                            >
                                {{ project.key }} — {{ project.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <p
                    v-if="!selectedProject"
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    Pick a project to see its templates.
                </p>

                <div v-else class="grid gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2.5 text-left hover:bg-accent dark:border-sidebar-border"
                        @click="choose(null)"
                    >
                        <FilePlus2
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="text-sm font-medium">Blank issue</span>
                    </button>

                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2.5 text-left hover:bg-accent dark:border-sidebar-border"
                        @click="choose(template)"
                    >
                        <FileText
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium">
                                    {{ template.name }}
                                </span>
                                <Badge v-if="template.type" variant="secondary">
                                    {{ typeLabels[template.type] }}
                                </Badge>
                                <Badge
                                    v-if="template.priority"
                                    variant="secondary"
                                >
                                    {{ priorityLabels[template.priority] }}
                                </Badge>
                            </span>
                            <span
                                v-if="template.description"
                                class="mt-0.5 line-clamp-1 block font-mono text-xs text-muted-foreground"
                            >
                                {{ template.description }}
                            </span>
                        </span>
                    </button>

                    <p
                        v-if="!loadingTemplates && templates.length === 0"
                        class="px-1 text-xs text-muted-foreground"
                    >
                        {{ selectedProject.key }} has no templates yet.
                    </p>
                </div>
            </div>

            <Form
                v-else
                v-bind="IssueController.store.form()"
                reset-on-success
                class="grid gap-4"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="project_id" :value="projectId" />
                <input
                    type="hidden"
                    name="template_id"
                    :value="chosen?.id ?? ''"
                />

                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                        @click="step = 'choose'"
                    >
                        <ArrowLeft class="size-3.5" />
                        Back
                    </button>
                    <span>·</span>
                    <span>{{ selectedProject?.key }}</span>
                    <span>·</span>
                    <span>{{ chosen?.name ?? 'Blank issue' }}</span>
                </div>

                <div class="grid gap-2">
                    <Label for="new-title">Title</Label>
                    <Input
                        id="new-title"
                        name="title"
                        required
                        autofocus
                        placeholder="Add per-lesson quiz question pools"
                    />
                    <InputError :message="errors.title" />
                    <InputError :message="errors.project_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="new-type">Type</Label>
                    <input type="hidden" name="type" :value="type" />
                    <Select v-model="type">
                        <SelectTrigger id="new-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="feature">Feature</SelectItem>
                            <SelectItem value="fix">Fix</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.type" />
                </div>

                <div v-if="epics && epics.length > 0" class="grid gap-2">
                    <Label for="new-parent">Epic (optional)</Label>
                    <Select name="parent_id">
                        <SelectTrigger id="new-parent" class="w-full">
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

                <div class="grid gap-2">
                    <Label for="new-description">Description</Label>
                    <AutoTextarea
                        id="new-description"
                        v-model="description"
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
</template>
