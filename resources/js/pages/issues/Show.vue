<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import IssueDetail from '@/components/IssueDetail.vue';
import IssueDetailLegacy from '@/components/IssueDetailLegacy.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { index } from '@/routes/issues';
import type {
    EpicOption,
    Issue,
    IssueLabel,
    IssueUser,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    issue: Issue;
    timeline: TimelineItem[];
    epics: EpicOption[];
    labels: IssueLabel[];
    members: IssueUser[];
    canLogTime: boolean;
    canManageTime: boolean;
    canModerateComments: boolean;
    canArchive: boolean;
    currentUserId: number;
}>();

// Temporary scaffolding for comparing the TRACK-137 redesign against what it
// replaced. Per-browser rather than a user setting, so dropping it later is a
// pure delete with no migration.
const legacyLayout = useStorage('tracker:issue-detail-legacy', false);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Issues', href: index() }],
    },
});
</script>

<template>
    <Head :title="issue.identifier" />

    <div
        class="flex items-center justify-end gap-2 border-b border-sidebar-border/70 px-4 py-1.5 dark:border-sidebar-border"
    >
        <Label for="legacy-layout" class="text-xs text-muted-foreground">
            {{ $t('issue.legacyLayout') }}
        </Label>
        <Switch id="legacy-layout" v-model="legacyLayout" />
    </div>

    <IssueDetailLegacy v-if="legacyLayout" v-bind="props" />
    <IssueDetail v-else v-bind="props" />
</template>
