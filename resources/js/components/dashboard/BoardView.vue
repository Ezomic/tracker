<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { statusDotClass } from '@/lib/issueStatus';
import type {
    BoardColumns,
    DashboardMetrics,
    IssueStatusKey,
    StatusBreakdown,
} from '@/types';

const props = defineProps<{
    board: BoardColumns;
    statusBreakdown: StatusBreakdown;
    metrics: DashboardMetrics;
}>();

const { t } = useI18n();

const columns: IssueStatusKey[] = [
    'backlog',
    'in_progress',
    'in_review',
    'done',
];

const atRisk = computed(() =>
    columns.reduce(
        (sum, key) => sum + props.board[key].filter((row) => row.stale).length,
        0,
    ),
);

const pills = computed(() => [
    {
        label: t('status.backlog'),
        dot: statusDotClass.backlog,
        value: props.statusBreakdown.backlog,
    },
    {
        label: t('status.in_progress'),
        dot: statusDotClass.in_progress,
        value: props.statusBreakdown.in_progress,
    },
    {
        label: t('status.in_review'),
        dot: statusDotClass.in_review,
        value: props.statusBreakdown.in_review,
    },
    {
        label: t('dashboard.doneThisWeek'),
        dot: statusDotClass.done,
        value: props.metrics.completed,
    },
]);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap gap-2.5">
            <span
                v-for="pill in pills"
                :key="pill.label"
                class="inline-flex items-center gap-2 rounded-full border border-sidebar-border/70 bg-card px-3.5 py-1.5 text-sm dark:border-sidebar-border"
            >
                <span class="size-2 rounded-full" :class="pill.dot" />
                {{ pill.label }}
                <b class="font-semibold tabular-nums">{{ pill.value }}</b>
            </span>
            <span
                class="inline-flex items-center gap-2 rounded-full border border-amber-500/40 bg-card px-3.5 py-1.5 text-sm"
            >
                <span class="size-2 rounded-full bg-amber-500" />
                {{ t('dashboard.atRisk') }}
                <b class="font-semibold tabular-nums">{{ atRisk }}</b>
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <section
                v-for="key in columns"
                :key="key"
                class="flex flex-col gap-2.5 rounded-xl border border-sidebar-border/70 bg-card p-3 dark:border-sidebar-border"
            >
                <header
                    class="flex items-center justify-between text-sm font-medium"
                >
                    <span class="flex items-center gap-2">
                        <span
                            class="size-2 rounded-full"
                            :class="statusDotClass[key]"
                        />
                        {{ t(`status.${key}`) }}
                    </span>
                    <span
                        class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground tabular-nums"
                    >
                        {{ statusBreakdown[key] }}
                    </span>
                </header>

                <p
                    v-if="board[key].length === 0"
                    class="py-4 text-center text-xs text-muted-foreground"
                >
                    {{ t('board.emptyColumn') }}
                </p>
                <article
                    v-for="row in board[key]"
                    :key="row.identifier"
                    class="flex flex-col gap-1.5 rounded-lg border border-sidebar-border/70 bg-background p-2.5 dark:border-sidebar-border"
                    :class="row.stale ? 'border-l-2 border-l-amber-500' : ''"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="font-mono text-xs font-semibold text-muted-foreground tabular-nums"
                        >
                            {{ row.identifier }}
                        </span>
                        <span
                            class="size-2 rounded-[3px]"
                            :style="{ backgroundColor: row.projectColor }"
                            :title="row.projectName"
                        />
                    </div>
                    <p class="text-[13px] leading-snug">{{ row.title }}</p>
                </article>
            </section>
        </div>
    </div>
</template>
