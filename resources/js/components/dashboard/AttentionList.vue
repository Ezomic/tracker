<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { staleChipClass, statusChipClass } from '@/lib/issueStatus';
import type { IssueRow } from '@/types';

withDefaults(
    defineProps<{
        rows: IssueRow[];
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const { t } = useI18n();

function ageLabel(row: IssueRow): string {
    if (row.stale) {
        return t('dashboard.idleDays', { days: row.ageDays });
    }

    if (row.ageDays <= 0) {
        return t('dashboard.today');
    }

    return t('dashboard.daysAgo', { days: row.ageDays });
}
</script>

<template>
    <p
        v-if="rows.length === 0"
        class="py-6 text-center text-sm text-muted-foreground"
    >
        {{ $t('dashboard.attentionEmpty') }}
    </p>
    <div v-else class="flex flex-col">
        <div
            v-for="row in rows"
            :key="row.identifier"
            class="flex items-center gap-3 border-t border-sidebar-border/70 py-2.5 first:border-t-0 dark:border-sidebar-border"
        >
            <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[10.5px] font-bold tracking-wide"
                :class="
                    row.stale ? staleChipClass : statusChipClass[row.status]
                "
            >
                {{
                    row.stale
                        ? $t('dashboard.stale')
                        : $t(`status.${row.status}`)
                }}
            </span>
            <span
                class="shrink-0 font-mono text-xs font-semibold text-muted-foreground tabular-nums"
            >
                {{ row.identifier }}
            </span>
            <span class="min-w-0 flex-1 truncate text-sm">{{ row.title }}</span>
            <span
                v-if="!compact"
                class="hidden shrink-0 items-center gap-1.5 text-xs text-muted-foreground sm:inline-flex"
            >
                <span
                    class="size-2 rounded-[3px]"
                    :style="{ backgroundColor: row.projectColor }"
                />
                {{ row.projectName }}
            </span>
            <span
                class="shrink-0 text-right text-xs tabular-nums"
                :class="
                    row.stale
                        ? 'font-semibold text-amber-600 dark:text-amber-400'
                        : 'text-muted-foreground'
                "
            >
                {{ ageLabel(row) }}
            </span>
        </div>
    </div>
</template>
