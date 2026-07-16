<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Settings/ProjectController';
import ArchiveDurationSelect from '@/components/ArchiveDurationSelect.vue';
import ColorSwatches from '@/components/ColorSwatches.vue';
import InputError from '@/components/InputError.vue';
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
import type { Team } from '@/types';

const props = defineProps<{
    project: Team;
    palette: string[];
    usedColors?: string[];
}>();

const color = ref(props.project.color);
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Edit</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="ProjectController.update.form({ project: project.id })"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Edit project</DialogTitle>
                    <DialogDescription>
                        Update the project's name, color{{
                            project.keyLocked ? '' : ', or key'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`name-${project.id}`">Name</Label>
                    <Input
                        :id="`name-${project.id}`"
                        name="name"
                        :default-value="project.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>GitHub repos</Label>
                    <RepoInputs
                        :key="project.id"
                        :model-value="project.githubRepos"
                    />
                    <InputError :message="errors.github_repos" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`production_url-${project.id}`">
                        Production URL
                    </Label>
                    <Input
                        :id="`production_url-${project.id}`"
                        name="production_url"
                        type="url"
                        :default-value="project.productionUrl ?? ''"
                        placeholder="https://example.com"
                    />
                    <InputError :message="errors.production_url" />
                </div>

                <div class="grid gap-2">
                    <Label>Auto-archive done issues</Label>
                    <ArchiveDurationSelect
                        :key="project.id"
                        :model-value="project.archiveAfterDays"
                    />
                    <InputError :message="errors.archive_after_days" />
                </div>

                <div class="grid gap-2">
                    <Label>Color</Label>
                    <input type="hidden" name="color" :value="color" />
                    <ColorSwatches
                        v-model="color"
                        :palette="palette"
                        :used="usedColors"
                        :current="project.color"
                    />
                </div>

                <div class="grid gap-2">
                    <Label :for="`key-${project.id}`">Key</Label>
                    <Input
                        :id="`key-${project.id}`"
                        name="key"
                        :default-value="project.key"
                        :disabled="project.keyLocked"
                        maxlength="10"
                        pattern="[A-Z]{2,10}"
                        class="uppercase"
                    />
                    <InputError :message="errors.key" />
                    <p
                        v-if="project.keyLocked"
                        class="text-sm text-muted-foreground"
                    >
                        The key can't change once a project has issues.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing"> Save </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
