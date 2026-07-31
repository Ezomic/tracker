<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CompletedByWeek, CompletedSeries } from '@/types';

const props = defineProps<{
    data: CompletedByWeek;
}>();

const { t } = useI18n();

const OTHER_COLOR = 'var(--muted-foreground)';

const currentIdx = computed(() => props.data.weeks.length - 1);

const cellMax = computed(() =>
    Math.max(1, ...props.data.series.flatMap((series) => series.values)),
);

function color(series: CompletedSeries): string {
    return series.color ?? OTHER_COLOR;
}

function cellStyle(
    series: CompletedSeries,
    count: number,
): Record<string, string> {
    if (count === 0) {
        return {};
    }

    const alpha = Math.round((0.1 + (count / cellMax.value) * 0.5) * 100);

    return {
        backgroundColor: `color-mix(in srgb, ${color(series)} ${alpha}%, transparent)`,
    };
}
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <header class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-medium">
                {{ t('dashboard.matrixTitle') }}
            </h2>
            <span class="text-xs text-muted-foreground">{{
                t('dashboard.matrixHint')
            }}</span>
        </header>

        <p
            v-if="data.grandTotal === 0"
            class="py-8 text-center text-sm text-muted-foreground"
        >
            {{ t('dashboard.completedEmpty') }}
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th
                            class="px-2.5 py-2 text-left text-xs font-medium text-muted-foreground"
                        >
                            {{ t('dashboard.project') }}
                        </th>
                        <th
                            v-for="(week, index) in data.weeks"
                            :key="week"
                            class="border-b border-border px-2.5 py-2 text-right text-xs font-medium whitespace-nowrap text-muted-foreground tabular-nums"
                            :class="index === currentIdx ? 'text-primary' : ''"
                        >
                            {{ week }}
                        </th>
                        <th
                            class="border-b border-l border-border px-2.5 py-2 text-right text-xs font-semibold text-muted-foreground"
                        >
                            {{ t('dashboard.total') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="series in data.series"
                        :key="series.key"
                        class="hover:bg-muted"
                    >
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            <span class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-[3px]"
                                    :style="{ backgroundColor: color(series) }"
                                />
                                <span
                                    :class="
                                        series.other
                                            ? 'text-muted-foreground italic'
                                            : 'font-medium'
                                    "
                                    >{{ series.name }}</span
                                >
                            </span>
                        </td>
                        <td
                            v-for="(count, index) in series.values"
                            :key="index"
                            class="px-2.5 py-2 text-right tabular-nums"
                            :class="[
                                index === currentIdx ? 'bg-primary/[0.06]' : '',
                                count === 0 ? 'text-muted-foreground/50' : '',
                            ]"
                        >
                            <span
                                class="inline-block min-w-[22px] rounded px-1.5 py-0.5"
                                :style="cellStyle(series, count)"
                                >{{ count }}</span
                            >
                        </td>
                        <td
                            class="border-l border-border px-2.5 py-2 text-right font-semibold tabular-nums"
                        >
                            {{ series.total }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td
                            class="border-t border-border px-2.5 py-2 font-semibold"
                        >
                            {{ t('dashboard.allProjects') }}
                        </td>
                        <td
                            v-for="(total, index) in data.weekTotals"
                            :key="index"
                            class="border-t border-border px-2.5 py-2 text-right font-semibold tabular-nums"
                            :class="
                                index === currentIdx ? 'bg-primary/[0.06]' : ''
                            "
                        >
                            {{ total }}
                        </td>
                        <td
                            class="border-t border-l border-border px-2.5 py-2 text-right font-semibold tabular-nums"
                        >
                            {{ data.grandTotal }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</template>
