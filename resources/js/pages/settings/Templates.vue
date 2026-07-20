<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Plus, Repeat } from '@lucide/vue';
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
import { destroy as destroyTemplate, index } from '@/routes/templates';
import type { IssueLabel, IssueTemplate, Project } from '@/types';

const props = defineProps<{
    templates: IssueTemplate[];
    labels: IssueLabel[];
    projects: Pick<Project, 'id' | 'key' | 'name'>[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Templates', href: index() }],
    },
});

const createOpen = ref(false);
const editing = ref<IssueTemplate | null>(null);
const deleting = ref<IssueTemplate | null>(null);

function labelsFor(template: IssueTemplate): IssueLabel[] {
    return props.labels.filter((label) => template.labelIds.includes(label.id));
}

function remove() {
    if (deleting.value === null) {
        return;
    }

    router.delete(destroyTemplate({ template: deleting.value.id }).url, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head :title="$t('templates.title')" />

    <h1 class="sr-only">{{ $t('templates.title') }}</h1>

    <div class="flex flex-col space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('templates.title')"
                :description="$t('templates.description')"
            />

            <Button
                v-if="canManage"
                size="sm"
                class="shrink-0"
                @click="createOpen = true"
            >
                <Plus />
                {{ $t('templates.newTemplate') }}
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
                <p class="text-sm font-medium">{{ $t('templates.empty') }}</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    {{ $t('templates.emptyBody') }}
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
                            {{ $t(`issueType.${template.type}`) }}
                        </Badge>
                        <Badge v-if="template.priority" variant="secondary">
                            {{ $t(`priority.${template.priority}`) }}
                        </Badge>
                        <Badge
                            v-if="template.cadence !== 'none'"
                            variant="outline"
                            class="gap-1"
                        >
                            <Repeat class="size-3" />
                            {{
                                $t(
                                    'templates.repeat' +
                                        template.cadence
                                            .charAt(0)
                                            .toUpperCase() +
                                        template.cadence.slice(1),
                                )
                            }}
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
                        {{ $t('common.edit') }}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="deleting = template"
                    >
                        {{ $t('common.delete') }}
                    </Button>
                </template>
            </div>
        </div>
    </div>

    <IssueTemplateDialog
        v-if="canManage"
        v-model:open="createOpen"
        :labels="labels"
        :projects="projects"
    />

    <IssueTemplateDialog
        v-if="canManage && editing"
        :key="editing.id"
        :open="true"
        :labels="labels"
        :projects="projects"
        :template="editing"
        @update:open="(value) => !value && (editing = null)"
    />

    <Dialog
        :open="deleting !== null"
        @update:open="(open) => !open && (deleting = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('templates.deleteTemplate') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t('templates.deleteConfirm', { name: deleting?.name })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">{{
                        $t('common.cancel')
                    }}</Button>
                </DialogClose>
                <Button variant="destructive" @click="remove">{{
                    $t('common.delete')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
