<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ActiveProjects from '@/components/dashboard/ActiveProjects.vue';
import AttentionList from '@/components/dashboard/AttentionList.vue';
import Panel from '@/components/dashboard/Panel.vue';
import StatTile from '@/components/dashboard/StatTile.vue';
import StatusThroughput from '@/components/dashboard/StatusThroughput.vue';
import TrendChart from '@/components/dashboard/TrendChart.vue';
import type {
    ActiveByProject,
    DashboardMetrics,
    IssueRow,
    StatusBreakdown,
    TrendPoint,
} from '@/types';

const props = defineProps<{
    metrics: DashboardMetrics;
    trend: TrendPoint[];
    statusBreakdown: StatusBreakdown;
    activeByProject: ActiveByProject[];
    attention: IssueRow[];
}>();

const { t } = useI18n();

const cycleValue = computed(() =>
    props.metrics.cycleDays === null ? '—' : `${props.metrics.cycleDays}d`,
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatTile
                hero
                :label="t('dashboard.completedPerWeek')"
                :value="metrics.completed"
                :delta="metrics.completedDelta"
                :spark="metrics.completedSpark"
                accent="text-primary"
            />
            <StatTile
                :label="t('dashboard.openedPerWeek')"
                :value="metrics.opened"
                :delta="metrics.openedDelta"
                :spark="metrics.openedSpark"
                accent="text-sky-500"
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
            <StatTile :label="t('dashboard.wipLoad')" :value="metrics.wip" />
        </div>

        <Panel :title="t('dashboard.trendTitle')">
            <template #action>
                <div class="flex gap-4 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5">
                        <span
                            class="inline-block h-0.5 w-4 rounded bg-sky-500"
                        />
                        {{ t('dashboard.opened') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span
                            class="inline-block h-0.5 w-4 rounded bg-primary"
                        />
                        {{ t('dashboard.completed') }}
                    </span>
                </div>
            </template>
            <TrendChart :points="trend" />
        </Panel>

        <div class="grid gap-4 lg:grid-cols-3">
            <Panel :title="t('dashboard.activeByProject')">
                <ActiveProjects :projects="activeByProject" size="sm" />
            </Panel>
            <Panel :title="t('dashboard.statusBreakdown')">
                <StatusThroughput :breakdown="statusBreakdown" />
            </Panel>
            <Panel :title="t('dashboard.attentionTitle')">
                <AttentionList :rows="attention.slice(0, 4)" compact />
            </Panel>
        </div>
    </div>
</template>
