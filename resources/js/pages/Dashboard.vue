<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardTicketList from '@/components/DashboardTicketList.vue';
import DonutChart from '@/components/DonutChart.vue';
import { dashboard } from '@/routes';
import type {
    ActiveByProject,
    DashboardRow,
    DashboardStats,
    StatusBreakdown,
} from '@/types';

const props = defineProps<{
    stats: DashboardStats;
    statusBreakdown: StatusBreakdown;
    activeByProject: ActiveByProject[];
    recent: DashboardRow[];
    stale: DashboardRow[];
    inReview: DashboardRow[];
    recentlyCompleted: DashboardRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const statTiles = computed(() => [
    { label: 'Open', value: props.stats.open, accent: false },
    { label: 'In progress', value: props.stats.in_progress, accent: true },
    { label: 'In review', value: props.stats.in_review, accent: false },
    { label: 'Done', value: props.stats.done, accent: false },
    { label: 'Archived', value: props.stats.archived, accent: false },
]);

const donutSegments = computed(() =>
    props.activeByProject.map((project) => ({
        color: project.color,
        value: project.count,
    })),
);

const activeTotal = computed(() =>
    props.activeByProject.reduce((sum, project) => sum + project.count, 0),
);

const statusMeta: { key: keyof StatusBreakdown; label: string; dot: string }[] =
    [
        { key: 'backlog', label: 'Backlog', dot: 'bg-muted-foreground/50' },
        { key: 'in_progress', label: 'In progress', dot: 'bg-primary' },
        { key: 'in_review', label: 'In review', dot: 'bg-sky-500' },
        { key: 'done', label: 'Done', dot: 'bg-emerald-500' },
    ];

const statusMax = computed(() =>
    Math.max(1, ...statusMeta.map((meta) => props.statusBreakdown[meta.key])),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div
                v-for="tile in statTiles"
                :key="tile.label"
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">{{ tile.label }}</p>
                <p
                    class="mt-1 text-2xl font-medium tabular-nums"
                    :class="tile.accent ? 'text-primary' : ''"
                >
                    {{ tile.value }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-medium">
                    Active tickets by project
                </h2>
                <div
                    v-if="activeTotal === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No active tickets.
                </div>
                <div v-else class="flex items-center gap-6">
                    <div class="relative size-32 shrink-0">
                        <DonutChart :segments="donutSegments" :thickness="4" />
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center"
                        >
                            <span class="text-xl font-medium tabular-nums">
                                {{ activeTotal }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                active
                            </span>
                        </div>
                    </div>
                    <div class="grid min-w-0 flex-1 gap-1.5">
                        <div
                            v-for="project in activeByProject"
                            :key="project.key"
                            class="flex items-center gap-2 text-sm"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: project.color }"
                            />
                            <span class="min-w-0 flex-1 truncate">
                                {{ project.name }}
                            </span>
                            <span
                                class="shrink-0 font-medium text-muted-foreground tabular-nums"
                            >
                                {{ project.count }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-4 text-sm font-medium">Status breakdown</h2>
                <div class="grid gap-3">
                    <div
                        v-for="meta in statusMeta"
                        :key="meta.key"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="w-24 shrink-0 text-muted-foreground">
                            {{ meta.label }}
                        </span>
                        <div
                            class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="meta.dot"
                                :style="{
                                    width: `${(statusBreakdown[meta.key] / statusMax) * 100}%`,
                                }"
                            />
                        </div>
                        <span
                            class="w-8 shrink-0 text-right font-medium tabular-nums"
                        >
                            {{ statusBreakdown[meta.key] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <DashboardTicketList
                title="Most recent"
                :rows="recent"
                empty-text="No tickets yet."
            />
            <DashboardTicketList
                title="Most stale"
                :rows="stale"
                empty-text="No open tickets."
                highlight-age
            />
            <DashboardTicketList
                title="In review"
                :rows="inReview"
                empty-text="Nothing in review."
            />
            <DashboardTicketList
                title="Completed this week"
                :rows="recentlyCompleted"
                empty-text="Nothing completed in the last 7 days."
            />
        </div>
    </div>
</template>
