<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ActiveProjects from '@/components/dashboard/ActiveProjects.vue';
import AttentionList from '@/components/dashboard/AttentionList.vue';
import Panel from '@/components/dashboard/Panel.vue';
import StatTile from '@/components/dashboard/StatTile.vue';
import StatusThroughput from '@/components/dashboard/StatusThroughput.vue';
import type {
    ActiveByProject,
    DashboardMetrics,
    DashboardStats,
    IssueRow,
    StatusBreakdown,
} from '@/types';

defineProps<{
    stats: DashboardStats;
    statusBreakdown: StatusBreakdown;
    activeByProject: ActiveByProject[];
    attention: IssueRow[];
    metrics: DashboardMetrics;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatTile :label="t('dashboard.statOpen')" :value="stats.open" />
            <StatTile
                :label="t('dashboard.statInProgress')"
                :value="stats.in_progress"
                accent="text-primary"
            />
            <StatTile
                :label="t('dashboard.statInReview')"
                :value="stats.in_review"
            />
            <StatTile
                hero
                :label="t('dashboard.completedPerWeek')"
                :value="metrics.completed"
                :delta="metrics.completedDelta"
                :spark="metrics.completedSpark"
                accent="text-primary"
            />
        </div>

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
