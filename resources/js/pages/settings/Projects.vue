<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Settings/ProjectController';
import EditProjectDialog from '@/components/EditProjectDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/projects';
import type { Team } from '@/types';

defineProps<{
    projects: Team[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const palette = [
    '#d85a30',
    '#1d9e75',
    '#378add',
    '#ef9f27',
    '#d4537e',
    '#7f77dd',
];

const newColor = ref(palette[0]);
</script>

<template>
    <Head title="Projects" />

    <h1 class="sr-only">Projects</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Projects"
            description="Projects give issues their prefix (e.g. THI-274), color, and independent numbering"
        />

        <Form
            v-bind="ProjectController.store.form()"
            reset-on-success
            class="flex flex-wrap items-end gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="key">Key</Label>
                <Input
                    id="key"
                    name="key"
                    maxlength="10"
                    pattern="[A-Z]{2,10}"
                    class="w-28 uppercase"
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
                    class="w-56"
                    placeholder="Thijssen Software"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>Color</Label>
                <input type="hidden" name="color" :value="newColor" />
                <div class="flex h-9 items-center gap-1.5">
                    <button
                        v-for="color in palette"
                        :key="color"
                        type="button"
                        class="flex size-6 items-center justify-center rounded-full"
                        :style="{ backgroundColor: color }"
                        :aria-label="`Use color ${color}`"
                        @click="newColor = color"
                    >
                        <Check
                            v-if="newColor === color"
                            class="size-3.5 text-white"
                        />
                    </button>
                </div>
            </div>

            <Button type="submit" :disabled="processing">Add project</Button>
        </Form>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <p
                v-if="projects.length === 0"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No projects yet — add one above.
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
                <span class="truncate text-sm">{{ project.name }}</span>
                <span class="ml-auto text-xs text-muted-foreground">
                    {{ project.issuesCount }}
                    {{ project.issuesCount === 1 ? 'issue' : 'issues' }}
                </span>
                <EditProjectDialog :project="project" :palette="palette" />
            </div>
        </div>
    </div>
</template>
