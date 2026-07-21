<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FolderPlus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import BoardView from '@/components/dashboard/BoardView.vue';
import FocusView from '@/components/dashboard/FocusView.vue';
import MetricsView from '@/components/dashboard/MetricsView.vue';
import { Button } from '@/components/ui/button';
import { useDashboardView } from '@/composables/useDashboardView';
import { dashboard } from '@/routes';
import { index as projectsIndex } from '@/routes/projects';
import type {
    ActiveByProject,
    BoardColumns,
    DashboardMetrics,
    DashboardStats,
    DashboardView,
    IssueRow,
    StatusBreakdown,
    TrendPoint,
} from '@/types';

defineProps<{
    stats: DashboardStats;
    statusBreakdown: StatusBreakdown;
    hasProjects: boolean;
    activeByProject: ActiveByProject[];
    attention: IssueRow[];
    board: BoardColumns;
    trend: TrendPoint[];
    metrics: DashboardMetrics;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const { t } = useI18n();
const { view, setView } = useDashboardView();

const tabs: { key: DashboardView; label: string }[] = [
    { key: 'focus', label: 'dashboard.viewFocus' },
    { key: 'metrics', label: 'dashboard.viewMetrics' },
    { key: 'board', label: 'dashboard.viewBoard' },
];
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
        <div class="flex items-center justify-between gap-3">
            <div
                class="inline-flex rounded-lg border border-sidebar-border/70 bg-card p-0.5 dark:border-sidebar-border"
                role="tablist"
                :aria-label="$t('dashboard.viewLabel')"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="view === tab.key"
                    class="rounded-[7px] px-3.5 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        view === tab.key
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setView(tab.key)"
                >
                    {{ t(tab.label) }}
                </button>
            </div>
        </div>

        <FocusView
            v-if="view === 'focus'"
            :stats="stats"
            :status-breakdown="statusBreakdown"
            :active-by-project="activeByProject"
            :attention="attention"
            :metrics="metrics"
        />
        <MetricsView
            v-else-if="view === 'metrics'"
            :metrics="metrics"
            :trend="trend"
            :status-breakdown="statusBreakdown"
            :active-by-project="activeByProject"
            :attention="attention"
        />
        <BoardView
            v-else
            :board="board"
            :status-breakdown="statusBreakdown"
            :metrics="metrics"
        />
    </div>
</template>
