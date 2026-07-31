<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatDuration } from '@/lib/duration';
import type { DashboardTime } from '@/types';

const props = defineProps<{
    time: DashboardTime;
}>();

const { t } = useI18n();

const loggedDelta = computed(
    () => props.time.loggedThisWeek - props.time.loggedPreviousWeek,
);

const maxLogged = computed(() =>
    Math.max(1, ...props.time.loggedByProject.map((row) => row.minutes)),
);
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <header
            class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
        >
            <h2 class="text-sm font-medium">{{ t('dashboard.timeTitle') }}</h2>
            <span class="text-xs text-muted-foreground">{{
                t('dashboard.timeHint')
            }}</span>
        </header>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.6fr]">
            <div>
                <p class="text-xs font-medium text-muted-foreground">
                    {{ t('dashboard.loggedThisWeek') }}
                </p>
                <p
                    class="mt-1.5 text-2xl font-semibold text-primary tabular-nums"
                >
                    {{ formatDuration(time.loggedThisWeek) }}
                </p>
                <p
                    v-if="loggedDelta !== 0"
                    class="mt-1.5 text-xs"
                    :class="
                        loggedDelta > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-amber-600 dark:text-amber-400'
                    "
                >
                    {{ loggedDelta > 0 ? '▲' : '▼' }}
                    {{ formatDuration(Math.abs(loggedDelta)) }}
                    <span class="text-muted-foreground">{{
                        t('dashboard.vsLastWeek')
                    }}</span>
                </p>
            </div>

            <div>
                <p class="text-xs font-medium text-muted-foreground">
                    {{ t('dashboard.estimateAccuracy') }}
                </p>
                <p class="mt-1.5 text-2xl font-semibold tabular-nums">
                    {{
                        time.accuracy.pct === null
                            ? '—'
                            : `${time.accuracy.pct}%`
                    }}
                </p>
                <p
                    v-if="time.accuracy.pct !== null"
                    class="mt-1.5 text-xs text-muted-foreground"
                >
                    <span
                        v-if="time.accuracy.direction !== 'none'"
                        :class="
                            time.accuracy.direction === 'over'
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-emerald-600 dark:text-emerald-400'
                        "
                    >
                        {{ (time.accuracy.overPct ?? 0) > 0 ? '+' : ''
                        }}{{ time.accuracy.overPct }}%
                        {{ t(`dashboard.accuracy_${time.accuracy.direction}`) }}
                    </span>
                    {{
                        t('dashboard.onDone', {
                            count: time.accuracy.sampleSize,
                        })
                    }}
                </p>
                <p v-else class="mt-1.5 text-xs text-muted-foreground">
                    {{ t('dashboard.noEstimates') }}
                </p>
            </div>

            <div class="sm:col-span-2 lg:col-span-1">
                <p class="mb-2.5 text-xs font-medium text-muted-foreground">
                    {{ t('dashboard.loggedByProject') }}
                </p>
                <p
                    v-if="time.loggedByProject.length === 0"
                    class="py-2 text-sm text-muted-foreground"
                >
                    {{ t('dashboard.noTimeLogged') }}
                </p>
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="row in time.loggedByProject"
                        :key="row.key"
                        class="flex items-center gap-2.5 text-sm"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-[3px]"
                            :style="{ backgroundColor: row.color }"
                        />
                        <span class="w-14 shrink-0 truncate">{{
                            row.key
                        }}</span>
                        <span
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                class="block h-full rounded-full"
                                :style="{
                                    width: `${(row.minutes / maxLogged) * 100}%`,
                                    backgroundColor: row.color,
                                }"
                            />
                        </span>
                        <span
                            class="w-14 shrink-0 text-right font-semibold tabular-nums"
                            >{{ formatDuration(row.minutes) }}</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
