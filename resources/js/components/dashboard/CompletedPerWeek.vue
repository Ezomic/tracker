<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CompletedByWeek, CompletedSeries } from '@/types';

const props = defineProps<{
    data: CompletedByWeek;
}>();

const { t } = useI18n();

const OTHER_COLOR = 'var(--muted-foreground)';
const CHART_HEIGHT = 168;

const currentIdx = computed(() => props.data.weeks.length - 1);

const maxTotal = computed(() => Math.max(1, ...props.data.weekTotals));

function color(series: CompletedSeries): string {
    return series.color ?? OTHER_COLOR;
}

const columns = computed(() =>
    props.data.weeks.map((label, week) => {
        const total = props.data.weekTotals[week] ?? 0;

        return {
            label,
            total,
            current: week === currentIdx.value,
            barHeight: (total / maxTotal.value) * CHART_HEIGHT,
            segments: props.data.series
                .filter((series) => (series.values[week] ?? 0) > 0)
                .map((series) => ({
                    key: series.key,
                    color: color(series),
                    height:
                        total > 0
                            ? ((series.values[week] ?? 0) / total) * 100
                            : 0,
                    title: `${series.name}: ${series.values[week]}`,
                })),
        };
    }),
);

const comparison = computed(() => {
    const totals = props.data.weekTotals;
    const current = totals[currentIdx.value] ?? 0;
    const previous = totals[currentIdx.value - 1] ?? 0;
    const delta =
        previous === 0
            ? current === 0
                ? 0
                : 100
            : Math.round(((current - previous) / previous) * 100);

    return { current, previous, delta };
});
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-sm font-medium">
                {{ t('dashboard.completedPerWeekTitle') }}
            </h2>
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium"
            >
                {{ t('dashboard.thisWeek') }}
                <b class="tabular-nums">{{ comparison.current }}</b>
                <span class="text-muted-foreground">·</span>
                {{ t('dashboard.previousWeek') }}
                <b class="tabular-nums">{{ comparison.previous }}</b>
                <span
                    v-if="comparison.delta !== 0"
                    class="tabular-nums"
                    :class="
                        comparison.delta < 0
                            ? 'text-amber-600 dark:text-amber-400'
                            : 'text-emerald-600 dark:text-emerald-400'
                    "
                >
                    {{ comparison.delta > 0 ? '+' : '' }}{{ comparison.delta }}%
                </span>
            </span>
        </header>

        <p
            v-if="data.grandTotal === 0"
            class="py-10 text-center text-sm text-muted-foreground"
        >
            {{ t('dashboard.completedEmpty') }}
        </p>

        <template v-else>
            <div
                class="flex items-end gap-3.5"
                :style="{ height: `${CHART_HEIGHT + 32}px` }"
            >
                <div
                    v-for="col in columns"
                    :key="col.label"
                    class="flex h-full flex-1 flex-col items-center justify-end gap-1.5"
                >
                    <span class="text-xs font-semibold tabular-nums">{{
                        col.total
                    }}</span>
                    <div
                        class="flex w-full max-w-[46px] flex-col-reverse overflow-hidden rounded-[5px]"
                        :class="
                            col.current
                                ? 'outline-2 outline-offset-2 outline-primary'
                                : ''
                        "
                        :style="{ height: `${col.barHeight}px` }"
                    >
                        <div
                            v-for="seg in col.segments"
                            :key="seg.key"
                            :style="{
                                height: `${seg.height}%`,
                                backgroundColor: seg.color,
                            }"
                            :title="seg.title"
                        />
                    </div>
                    <span
                        class="text-[11px] whitespace-nowrap"
                        :class="
                            col.current
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground'
                        "
                        >{{ col.label }}</span
                    >
                </div>
            </div>

            <div
                class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-border pt-3"
            >
                <span
                    v-for="series in data.series"
                    :key="series.key"
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span
                        class="size-2.5 rounded-[3px]"
                        :style="{ backgroundColor: color(series) }"
                    />
                    {{ series.name }}
                </span>
            </div>
        </template>
    </section>
</template>
