<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FolderPlus } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ActiveProjects from '@/components/dashboard/ActiveProjects.vue';
import AttentionList from '@/components/dashboard/AttentionList.vue';
import CompletedPerWeek from '@/components/dashboard/CompletedPerWeek.vue';
import Panel from '@/components/dashboard/Panel.vue';
import ProjectWeekMatrix from '@/components/dashboard/ProjectWeekMatrix.vue';
import StatTile from '@/components/dashboard/StatTile.vue';
import StatusThroughput from '@/components/dashboard/StatusThroughput.vue';
import TimePanel from '@/components/dashboard/TimePanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as projectsIndex } from '@/routes/projects';
import type {
    ActiveByProject,
    CompletedByWeek,
    DashboardMetrics,
    DashboardStats,
    DashboardTime,
    IssueRow,
    StatusBreakdown,
} from '@/types';

const props = defineProps<{
    stats: DashboardStats;
    statusBreakdown: StatusBreakdown;
    hasProjects: boolean;
    activeByProject: ActiveByProject[];
    attention: IssueRow[];
    completedByWeek: CompletedByWeek;
    metrics: DashboardMetrics;
    time: DashboardTime;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const { t } = useI18n();

const cycleValue = computed(() =>
    props.metrics.cycleDays === null ? '—' : `${props.metrics.cycleDays}d`,
);
</script>

<template>
    <Head :title="$t('dashboard.title')" />

    <div
        v-if="!hasProjects"
        class="flex h-full flex-1 flex-col items-center justify-center gap-4 p-8 text-center"
    >
        <div class="rounded-full bg-muted p-4">
            <FolderPlus class="size-8 text-muted-foreground" />
        </div>
        <div class="space-y-1">
            <h2 class="text-lg font-medium">
                {{ $t('dashboard.welcomeTitle') }}
            </h2>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{ $t('dashboard.welcomeBody') }}
            </p>
        </div>
        <Button as-child>
            <Link :href="projectsIndex()">
                <FolderPlus class="size-4" />
                {{ $t('dashboard.createFirstProject') }}
            </Link>
        </Button>
    </div>

    <div v-else class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatTile
                :label="t('dashboard.statOpen')"
                :value="stats.open"
                :hint="
                    t('dashboard.openBreakdown', {
                        inProgress: stats.in_progress,
                        inReview: stats.in_review,
                    })
                "
            />
            <StatTile
                hero
                :label="t('dashboard.completedPerWeek')"
                :value="metrics.completed"
                :delta="metrics.completedDelta"
                :spark="metrics.completedSpark"
                accent="text-primary"
            />
            <StatTile
                :label="t('dashboard.medianCycle')"
                :value="cycleValue"
                :delta="metrics.cycleDelta"
                delta-unit="d"
                invert
                :spark="metrics.cycleSpark"
                accent="text-emerald-500"
            />
            <StatTile
                :label="t('dashboard.wipLoad')"
                :value="metrics.wip"
                :hint="t('dashboard.urgentOpen', { count: stats.urgentOpen })"
            />
        </div>

        <CompletedPerWeek :data="completedByWeek" />

        <ProjectWeekMatrix :data="completedByWeek" />

        <TimePanel :time="time" />

        <div class="grid gap-4 lg:grid-cols-[1.55fr_1fr]">
            <Panel :title="t('dashboard.attentionTitle')">
                <AttentionList :rows="attention" />
            </Panel>
            <div class="flex flex-col gap-4">
                <Panel :title="t('dashboard.activeByProject')">
                    <ActiveProjects :projects="activeByProject" />
                </Panel>
                <Panel :title="t('dashboard.statusBreakdown')">
                    <StatusThroughput :breakdown="statusBreakdown" />
                </Panel>
            </div>
        </div>
    </div>
</template>
