<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Panel from '@/components/dashboard/Panel.vue';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/lib/duration';
import type { AccuracyRow, EstimateAccuracyData } from '@/types';

const props = defineProps<{ accuracy: EstimateAccuracyData }>();

const { t } = useI18n();

const windows = [4, 12, 26];

function setWindow(weeks: number) {
    router.get(
        '/dashboard',
        { accuracy_weeks: weeks },
        { preserveScroll: true, preserveState: true, only: ['accuracy'] },
    );
}

/**
 * A ratio of 1.4 reads as "40% over". Null means nothing to compare, which is
 * shown as a dash rather than a misleading zero.
 */
function drift(row: AccuracyRow): string {
    if (row.ratio === null) {
        return '—';
    }

    const pct = Math.round((row.ratio - 1) * 100);

    return pct === 0 ? t('accuracy.onTarget') : `${pct > 0 ? '+' : ''}${pct}%`;
}

function driftClass(row: AccuracyRow): string {
    if (row.ratio === null || row.direction === 'none') {
        return 'text-muted-foreground';
    }

    return row.direction === 'over'
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-emerald-600 dark:text-emerald-400';
}
</script>

<template>
    <Panel
        :title="t('accuracy.title')"
        :description="t('accuracy.description')"
    >
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <Button
                v-for="weeks in windows"
                :key="weeks"
                size="sm"
                :variant="
                    props.accuracy.window === weeks ? 'secondary' : 'ghost'
                "
                @click="setWindow(weeks)"
            >
                {{ t('accuracy.weeks', { weeks }) }}
            </Button>
            <span class="ml-auto text-xs text-muted-foreground">
                {{
                    t('accuracy.sample', {
                        counted: props.accuracy.overall.sampleSize,
                        excluded: props.accuracy.overall.excluded,
                    })
                }}
            </span>
        </div>

        <p
            v-if="props.accuracy.overall.sampleSize === 0"
            class="py-6 text-center text-sm text-muted-foreground"
        >
            {{ t('accuracy.empty') }}
        </p>

        <template v-else>
            <div class="flex items-baseline gap-3 border-b border-border pb-3">
                <span
                    class="text-2xl font-semibold tabular-nums"
                    :class="driftClass(props.accuracy.overall)"
                >
                    {{ drift(props.accuracy.overall) }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ formatDuration(props.accuracy.overall.estimated) }}
                    →
                    {{ formatDuration(props.accuracy.overall.actual) }}
                </span>
            </div>

            <div class="grid gap-4 pt-3 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <span
                        class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >{{ t('accuracy.byProject') }}</span
                    >
                    <div
                        v-for="row in props.accuracy.projects"
                        :key="row.key"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span class="font-mono text-xs">{{ row.key }}</span>
                        <span
                            class="min-w-0 flex-1 truncate text-muted-foreground"
                            >{{ row.name }}</span
                        >
                        <span class="text-xs text-muted-foreground tabular-nums"
                            >n={{ row.sampleSize }}</span
                        >
                        <span
                            class="w-14 text-right font-medium tabular-nums"
                            :class="driftClass(row)"
                            >{{ drift(row) }}</span
                        >
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span
                        class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >{{ t('accuracy.byBand') }}</span
                    >
                    <div
                        v-for="row in props.accuracy.bands"
                        :key="row.band"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span class="min-w-0 flex-1 truncate">{{
                            t(`accuracy.band.${row.band}`)
                        }}</span>
                        <span class="text-xs text-muted-foreground tabular-nums"
                            >n={{ row.sampleSize }}</span
                        >
                        <span
                            class="w-14 text-right font-medium tabular-nums"
                            :class="driftClass(row)"
                            >{{ drift(row) }}</span
                        >
                    </div>
                </div>
            </div>
        </template>
    </Panel>
</template>
