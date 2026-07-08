<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import LabelController from '@/actions/App/Http/Controllers/Settings/LabelController';
import EditLabelDialog from '@/components/EditLabelDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import { Button } from '@/components/ui/button';
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
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, index } from '@/routes/labels';
import type { IssueLabel, LabelColor } from '@/types';

defineProps<{
    labels: (IssueLabel & { issuesCount: number })[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Labels',
                href: index(),
            },
        ],
    },
});

const colors: { value: LabelColor; name: string }[] = [
    { value: 'gray', name: 'Gray' },
    { value: 'red', name: 'Red' },
    { value: 'yellow', name: 'Yellow' },
    { value: 'green', name: 'Green' },
    { value: 'blue', name: 'Blue' },
    { value: 'purple', name: 'Purple' },
];

function remove(label: IssueLabel) {
    router.delete(destroy.url({ label: label.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Labels" />

    <h1 class="sr-only">Labels</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Labels"
            description="Labels let you tag issues to group them across teams and epics"
        />

        <Form
            v-bind="LabelController.store.form()"
            reset-on-success
            class="flex items-end gap-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    class="w-64"
                    placeholder="bug"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="color">Color</Label>
                <Select name="color" default-value="gray">
                    <SelectTrigger id="color" class="w-40">
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

            <Button type="submit" :disabled="processing">Add label</Button>
        </Form>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Label</TableHead>
                    <TableHead>Issues</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty v-if="labels.length === 0" :colspan="3">
                    No labels yet - add one above.
                </TableEmpty>
                <TableRow v-for="label in labels" :key="label.id">
                    <TableCell>
                        <LabelBadge :name="label.name" :color="label.color" />
                    </TableCell>
                    <TableCell>{{ label.issuesCount }}</TableCell>
                    <TableCell class="text-right">
                        <div class="flex justify-end gap-2">
                            <EditLabelDialog :label="label" />
                            <Button
                                variant="outline"
                                size="sm"
                                @click="remove(label)"
                            >
                                Delete
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
