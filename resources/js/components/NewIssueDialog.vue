<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { ArrowLeft, FilePlus2, FileText, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import IssueDraftController from '@/actions/App/Http/Controllers/IssueDraftController';
import InputError from '@/components/InputError.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { applyDraft, requestIssueDraft } from '@/lib/issueDraft';
import { options as templateOptions } from '@/routes/templates';
import type { EpicOption, Issue, IssueTemplate, Project } from '@/types';

const DRAFT_TEXT_MAX = 10000;
const priorities: Issue['priority'][] = [
    'none',
    'low',
    'medium',
    'high',
    'urgent',
];

const props = defineProps<{
    projects: Pick<Project, 'id' | 'key' | 'name'>[];
    // Omitted when the dialog is opened outside a project's issue list, where
    // there is no sensible epic list to offer.
    epics?: EpicOption[];
    defaultProjectId?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const step = ref<'choose' | 'form'>('choose');
const projectId = ref<string | undefined>();
const templates = ref<IssueTemplate[]>([]);
const loadingTemplates = ref(false);
const chosen = ref<IssueTemplate | null>(null);

const title = ref('');
const description = ref('');
const type = ref<Issue['type']>('feature');
const priority = ref<Issue['priority']>('none');

const page = usePage();
const { t } = useI18n();
const canDraft = computed(
    () => page.props.currentOrganization?.aiDrafting ?? false,
);
const draftText = ref('');
const drafting = ref(false);
const draftError = ref<string | null>(null);
const drafted = ref(false);
// Bumped whenever the form is reset, so a draft that lands afterwards is dropped.
let draftRun = 0;

function resetDraft() {
    draftRun++;
    drafting.value = false;
    drafted.value = false;
    draftError.value = null;
}

const selectedProject = computed(() =>
    props.projects.find((project) => String(project.id) === projectId.value),
);

// Reset each time it opens, so navigating between projects re-preselects and a
// previous pick doesn't linger.
watch(open, (isOpen) => {
    resetDraft();

    if (isOpen) {
        step.value = 'choose';
        chosen.value = null;
        draftText.value = '';
        projectId.value = props.defaultProjectId
            ? String(props.defaultProjectId)
            : undefined;
    }
});

// Templates are organization-wide, so fetch the current org's set once on
// open rather than per selected project or on every page load.
watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    templates.value = [];
    loadingTemplates.value = true;

    try {
        const response = await fetch(templateOptions().url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (response.ok) {
            templates.value = await response.json();
        }
    } finally {
        loadingTemplates.value = false;
    }
});

function choose(template: IssueTemplate | null) {
    chosen.value = template;
    title.value = '';
    description.value = template?.description ?? '';
    type.value = template?.type ?? 'feature';
    priority.value = template?.priority ?? 'none';
    resetDraft();
    step.value = 'form';
}

function back() {
    resetDraft();
    step.value = 'choose';
}

async function draft() {
    if (!selectedProject.value || draftText.value.trim() === '') {
        return;
    }

    const run = ++draftRun;
    drafting.value = true;
    draftError.value = null;

    try {
        const result = await requestIssueDraft(IssueDraftController.url(), {
            project_id: selectedProject.value.id,
            template_id: chosen.value?.id ?? null,
            text: draftText.value,
        });

        if (run !== draftRun) {
            return;
        }

        if ('error' in result) {
            draftError.value =
                result.message ?? t(`newIssue.draftError.${result.error}`);

            return;
        }

        const filled = applyDraft(
            {
                template: chosen.value,
                title: title.value,
                type: type.value,
                priority: priority.value,
                description: description.value,
            },
            result.draft,
            templates.value,
        );

        chosen.value = filled.template;
        title.value = filled.title;
        type.value = filled.type;
        priority.value = filled.priority;
        description.value = filled.description;
        drafted.value = true;
    } finally {
        if (run === draftRun) {
            drafting.value = false;
        }
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('newIssue.title') }}</DialogTitle>
                <DialogDescription>
                    {{
                        step === 'choose'
                            ? $t('newIssue.subtitleChoose')
                            : $t('newIssue.subtitleForm')
                    }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="step === 'choose'" class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="choose-project">{{
                        $t('newIssue.project')
                    }}</Label>
                    <Select v-model="projectId">
                        <SelectTrigger id="choose-project" class="w-full">
                            <SelectValue
                                :placeholder="$t('newIssue.selectProject')"
                            />
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
                </div>

                <p
                    v-if="!selectedProject"
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    {{ $t('newIssue.pickProject') }}
                </p>

                <div v-else class="grid gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2.5 text-left hover:bg-accent dark:border-sidebar-border"
                        @click="choose(null)"
                    >
                        <FilePlus2
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="text-sm font-medium">{{
                            $t('newIssue.blankIssue')
                        }}</span>
                    </button>

                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2.5 text-left hover:bg-accent dark:border-sidebar-border"
                        @click="choose(template)"
                    >
                        <FileText
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium">
                                    {{ template.name }}
                                </span>
                                <Badge v-if="template.type" variant="secondary">
                                    {{ $t(`issueType.${template.type}`) }}
                                </Badge>
                                <Badge
                                    v-if="template.priority"
                                    variant="secondary"
                                >
                                    {{ $t(`priority.${template.priority}`) }}
                                </Badge>
                            </span>
                            <span
                                v-if="template.description"
                                class="mt-0.5 line-clamp-1 block font-mono text-xs text-muted-foreground"
                            >
                                {{ template.description }}
                            </span>
                        </span>
                    </button>

                    <p
                        v-if="!loadingTemplates && templates.length === 0"
                        class="px-1 text-xs text-muted-foreground"
                    >
                        {{
                            $t('newIssue.noTemplates', {
                                key: selectedProject.key,
                            })
                        }}
                    </p>
                </div>
            </div>

            <Form
                v-else
                v-bind="IssueController.store.form()"
                reset-on-success
                class="grid gap-4"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="project_id" :value="projectId" />
                <input
                    type="hidden"
                    name="template_id"
                    :value="chosen?.id ?? ''"
                />

                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                        @click="back"
                    >
                        <ArrowLeft class="size-3.5" />
                        {{ $t('newIssue.back') }}
                    </button>
                    <span>·</span>
                    <span>{{ selectedProject?.key }}</span>
                    <span>·</span>
                    <span>{{ chosen?.name ?? $t('newIssue.blankIssue') }}</span>
                </div>

                <div
                    v-if="canDraft"
                    class="grid gap-2 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <Label for="draft-text">{{
                        $t('newIssue.draftLabel')
                    }}</Label>
                    <textarea
                        id="draft-text"
                        v-model="draftText"
                        rows="3"
                        :maxlength="DRAFT_TEXT_MAX"
                        :disabled="drafting"
                        :placeholder="$t('newIssue.draftPlaceholder')"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50 dark:bg-input/30"
                    />
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-muted-foreground">
                            <template v-if="drafted">
                                {{ $t('newIssue.draftNote') }}
                            </template>
                        </p>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            :disabled="drafting || draftText.trim() === ''"
                            @click="draft"
                        >
                            <Spinner v-if="drafting" />
                            <Sparkles v-else class="size-4" />
                            {{ $t('newIssue.draftButton') }}
                        </Button>
                    </div>
                    <InputError :message="draftError ?? undefined" />
                </div>

                <div class="grid gap-2">
                    <Label for="new-title">{{ $t('issue.title') }}</Label>
                    <Input
                        id="new-title"
                        v-model="title"
                        name="title"
                        required
                        autofocus
                        :placeholder="$t('newIssue.titlePlaceholder')"
                    />
                    <InputError :message="errors.title" />
                    <InputError :message="errors.project_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="new-type">{{ $t('issue.type') }}</Label>
                    <input type="hidden" name="type" :value="type" />
                    <Select v-model="type">
                        <SelectTrigger id="new-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="feature">{{
                                $t('issueType.feature')
                            }}</SelectItem>
                            <SelectItem value="fix">{{
                                $t('issueType.fix')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="new-priority">{{ $t('issue.priority') }}</Label>
                    <input type="hidden" name="priority" :value="priority" />
                    <Select v-model="priority">
                        <SelectTrigger id="new-priority" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="each in priorities"
                                :key="each"
                                :value="each"
                            >
                                {{ $t(`priority.${each}`) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.priority" />
                </div>

                <div v-if="epics && epics.length > 0" class="grid gap-2">
                    <Label for="new-parent">{{
                        $t('issue.epicOptional')
                    }}</Label>
                    <Select name="parent_id">
                        <SelectTrigger id="new-parent" class="w-full">
                            <SelectValue :placeholder="$t('newIssue.noEpic')" />
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
                    <Label for="new-description">{{
                        $t('common.description')
                    }}</Label>
                    <MarkdownEditor
                        v-model="description"
                        name="description"
                        :rows="3"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        {{ $t('newIssue.create') }}
                    </Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
