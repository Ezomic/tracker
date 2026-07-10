<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Settings/ProjectController';
import InputError from '@/components/InputError.vue';
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
                    <Label>Color</Label>
                    <input type="hidden" name="color" :value="color" />
                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="swatch in palette"
                            :key="swatch"
                            type="button"
                            class="flex size-6 items-center justify-center rounded-full"
                            :style="{ backgroundColor: swatch }"
                            :aria-label="`Use color ${swatch}`"
                            @click="color = swatch"
                        >
                            <Check
                                v-if="color === swatch"
                                class="size-3.5 text-white"
                            />
                        </button>
                    </div>
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
