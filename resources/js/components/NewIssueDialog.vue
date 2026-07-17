<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import InputError from '@/components/InputError.vue';
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
import type { EpicOption, Project } from '@/types';

const props = defineProps<{
    projects: Pick<Project, 'id' | 'key' | 'name'>[];
    // Omitted when the dialog is opened outside a project's issue list, where
    // there is no sensible epic list to offer.
    epics?: EpicOption[];
    defaultProjectId?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const projectId = ref<string | undefined>();

// Reset each time it opens, so navigating between projects re-preselects.
watch(open, (isOpen) => {
    if (isOpen) {
        projectId.value = props.defaultProjectId
            ? String(props.defaultProjectId)
            : undefined;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>New issue</DialogTitle>
                <DialogDescription>
                    Create an issue and hand off a ready-to-use branch name.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="IssueController.store.form()"
                reset-on-success
                class="grid gap-4"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="new-project">Project</Label>
                        <Select v-model="projectId" name="project_id">
                            <SelectTrigger id="new-project" class="w-full">
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
                        <InputError :message="errors.project_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="new-type">Type</Label>
                        <Select name="type" default-value="feature">
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
</template>
