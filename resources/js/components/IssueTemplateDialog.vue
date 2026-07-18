<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import {
    store as storeTemplate,
    update as updateTemplate,
} from '@/routes/templates';
import type { IssueLabel, IssueTemplate } from '@/types';

const props = defineProps<{
    labels: IssueLabel[];
    // Present when editing; absent when creating.
    template?: IssueTemplate | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const name = ref('');
const description = ref('');
const type = ref('none');
const priority = ref('none');
const labelIds = ref<number[]>([]);

// Reset from the template each time it opens, so a cancelled edit doesn't leak
// into the next one.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    name.value = props.template?.name ?? '';
    description.value = props.template?.description ?? '';
    type.value = props.template?.type ?? 'none';
    priority.value = props.template?.priority ?? 'none';
    labelIds.value = [...(props.template?.labelIds ?? [])];
});

function toggleLabel(id: number, checked: boolean) {
    labelIds.value = checked
        ? [...labelIds.value, id]
        : labelIds.value.filter((labelId) => labelId !== id);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <Form
                v-bind="
                    template
                        ? updateTemplate.form({ template: template.id })
                        : storeTemplate.form()
                "
                :options="{ preserveScroll: true }"
                class="space-y-5"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ template ? 'Edit template' : 'New template' }}
                    </DialogTitle>
                    <DialogDescription>
                        A starting point for new issues, available across every
                        project in this organization.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="template-name">Name</Label>
                    <Input
                        id="template-name"
                        v-model="name"
                        name="name"
                        required
                        placeholder="Bug report"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="template-description">Description</Label>
                    <MarkdownEditor
                        v-model="description"
                        name="description"
                        :rows="5"
                        :placeholder="'## Steps to reproduce\n## Expected\n## Actual'"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="template-type">Default type</Label>
                        <input
                            type="hidden"
                            name="type"
                            :value="type === 'none' ? '' : type"
                        />
                        <Select v-model="type">
                            <SelectTrigger id="template-type" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No default</SelectItem>
                                <SelectItem value="feature">Feature</SelectItem>
                                <SelectItem value="fix">Fix</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="template-priority">Default priority</Label>
                        <input
                            type="hidden"
                            name="priority"
                            :value="priority === 'none' ? '' : priority"
                        />
                        <Select v-model="priority">
                            <SelectTrigger
                                id="template-priority"
                                class="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No default</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.priority" />
                    </div>
                </div>

                <div v-if="labels.length > 0" class="grid gap-2">
                    <Label>Default labels</Label>
                    <input
                        v-for="id in labelIds"
                        :key="id"
                        type="hidden"
                        name="labels[]"
                        :value="id"
                    />
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="label in labels"
                            :key="label.id"
                            class="flex items-center gap-1.5"
                        >
                            <Checkbox
                                :model-value="labelIds.includes(label.id)"
                                @update:model-value="
                                    (checked) =>
                                        toggleLabel(label.id, Boolean(checked))
                                "
                            />
                            <LabelBadge
                                :name="label.name"
                                :color="label.color"
                            />
                        </label>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ template ? 'Save' : 'Create template' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
