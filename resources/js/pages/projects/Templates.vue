<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import IssueTemplateDialog from '@/components/IssueTemplateDialog.vue';
import LabelBadge from '@/components/LabelBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as projectsIndex } from '@/routes/projects';
import { destroy as destroyTemplate } from '@/routes/projects/templates';
import type { CopyableIssueTemplate, IssueLabel, IssueTemplate } from '@/types';

const props = defineProps<{
    project: { key: string; name: string };
    templates: IssueTemplate[];
    copyable: CopyableIssueTemplate[];
    labels: IssueLabel[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const createOpen = ref(false);
const editing = ref<IssueTemplate | null>(null);
const deleting = ref<IssueTemplate | null>(null);

const typeLabels: Record<string, string> = { feature: 'Feature', fix: 'Fix' };
const priorityLabels: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

function labelsFor(template: IssueTemplate): IssueLabel[] {
    return props.labels.filter((label) => template.labelIds.includes(label.id));
}

function remove() {
    if (deleting.value === null) {
        return;
    }

    router.delete(
        destroyTemplate({
            project: props.project.key,
            template: deleting.value.id,
        }).url,
        {
            preserveScroll: true,
            onFinish: () => {
                deleting.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="`${project.name} templates`" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`${project.name} templates`"
                description="Starting points for new issues in this project"
            />

            <Button
                v-if="canManage"
                size="sm"
                class="shrink-0"
                @click="createOpen = true"
            >
                <Plus />
                New template
            </Button>
        </div>

        <div
            v-if="templates.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center dark:border-sidebar-border"
        >
            <div class="rounded-full bg-muted p-3">
                <FileText class="size-6 text-muted-foreground" />
            </div>
            <div class="space-y-1">
                <p class="text-sm font-medium">No templates yet</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Templates prefill an issue's description and defaults, so
                    recurring work starts from the same shape every time.
                </p>
            </div>
        </div>

        <div
            v-else
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-for="template in templates"
                :key="template.id"
                class="flex items-start gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <FileText
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium">{{ template.name }}</p>
                        <Badge v-if="template.type" variant="secondary">
                            {{ typeLabels[template.type] }}
                        </Badge>
                        <Badge v-if="template.priority" variant="secondary">
                            {{ priorityLabels[template.priority] }}
                        </Badge>
                        <LabelBadge
                            v-for="label in labelsFor(template)"
                            :key="label.id"
                            :name="label.name"
                            :color="label.color"
                        />
                    </div>
                    <p
                        v-if="template.description"
                        class="mt-1 line-clamp-2 font-mono text-xs whitespace-pre-line text-muted-foreground"
                    >
                        {{ template.description }}
                    </p>
                </div>

                <template v-if="canManage">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="editing = template"
                    >
                        Edit
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="deleting = template"
                    >
                        Delete
                    </Button>
                </template>
            </div>
        </div>
    </div>

    <IssueTemplateDialog
        v-if="canManage"
        v-model:open="createOpen"
        :project-key="project.key"
        :labels="labels"
        :copyable="copyable"
    />

    <IssueTemplateDialog
        v-if="canManage && editing"
        :key="editing.id"
        :open="true"
        :project-key="project.key"
        :labels="labels"
        :template="editing"
        @update:open="(value) => !value && (editing = null)"
    />

    <Dialog
        :open="deleting !== null"
        @update:open="(open) => !open && (deleting = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete template</DialogTitle>
                <DialogDescription>
                    Delete "{{ deleting?.name }}"? Issues already created from
                    it are untouched.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button variant="destructive" @click="remove">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
