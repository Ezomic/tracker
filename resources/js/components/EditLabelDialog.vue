<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import LabelController from '@/actions/App/Http/Controllers/Settings/LabelController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { IssueLabel, LabelColor } from '@/types';

defineProps<{
    label: IssueLabel & { issuesCount: number };
}>();

const colors: { value: LabelColor; name: string }[] = [
    { value: 'gray', name: 'Gray' },
    { value: 'red', name: 'Red' },
    { value: 'yellow', name: 'Yellow' },
    { value: 'green', name: 'Green' },
    { value: 'blue', name: 'Blue' },
    { value: 'purple', name: 'Purple' },
];
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Edit</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="LabelController.update.form({ label: label.id })"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Edit label</DialogTitle>
                    <DialogDescription>
                        Update the label's name or color.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`name-${label.id}`">Name</Label>
                    <Input
                        :id="`name-${label.id}`"
                        name="name"
                        :default-value="label.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`color-${label.id}`">Color</Label>
                    <Select name="color" :default-value="label.color">
                        <SelectTrigger :id="`color-${label.id}`" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="color in colors"
                                :key="color.value"
                                :value="color.value"
                            >
                                {{ color.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.color" />
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
