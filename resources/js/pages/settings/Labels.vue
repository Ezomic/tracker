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
import { destroy, index } from '@/routes/labels';
import type { IssueLabel, LabelColor } from '@/types';

defineProps<{
    labels: (IssueLabel & { issuesCount: number })[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Labels', href: index() }],
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
            description="Labels tag issues across the organization's projects and epics"
        />

        <Form
            v-if="canManage"
            v-bind="LabelController.store.form()"
            reset-on-success
            class="flex flex-wrap items-end gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    class="w-56"
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

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <p
                v-if="labels.length === 0"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No labels yet.
            </p>
            <div
                v-for="label in labels"
                :key="label.id"
                class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <LabelBadge :name="label.name" :color="label.color" />
                <span class="ml-auto text-xs text-muted-foreground">
                    {{ label.issuesCount }}
                    {{ label.issuesCount === 1 ? 'issue' : 'issues' }}
                </span>
                <template v-if="canManage">
                    <EditLabelDialog :label="label" />
                    <Button variant="outline" size="sm" @click="remove(label)">
                        Delete
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
