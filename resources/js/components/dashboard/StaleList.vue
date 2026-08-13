<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { priorityChip } from '@/lib/issuePriority';
import { show } from '@/routes/issues';
import type { StaleRow } from '@/types';

defineProps<{
    rows: StaleRow[];
}>();
</script>

<template>
    <div class="flex flex-col">
        <Link
            v-for="row in rows"
            :key="row.identifier"
            :href="show({ issue: row.identifier })"
            class="flex items-center gap-3 border-t border-sidebar-border/70 py-2.5 first:border-t-0 hover:bg-muted/40 dark:border-sidebar-border"
        >
            <span
                v-if="priorityChip(row.priority)"
                class="shrink-0 rounded-full px-2 py-0.5 text-[10.5px] font-bold tracking-wide uppercase"
                :class="priorityChip(row.priority) ?? ''"
            >
                {{ $t(`priority.${row.priority}`) }}
            </span>
            <span
                class="shrink-0 font-mono text-xs font-semibold text-muted-foreground tabular-nums"
            >
                {{ row.identifier }}
            </span>
            <span class="min-w-0 flex-1 truncate text-sm">{{ row.title }}</span>
            <span
                class="hidden shrink-0 items-center gap-1.5 text-xs text-muted-foreground sm:inline-flex"
            >
                <span
                    class="size-2 rounded-[3px]"
                    :style="{ backgroundColor: row.projectColor }"
                />
                {{ row.projectName }}
            </span>
            <span
                class="shrink-0 text-right text-xs font-semibold text-amber-600 tabular-nums dark:text-amber-400"
                :title="row.quietSince"
            >
                {{ $t('dashboard.quietDays', { days: row.quietDays }) }}
            </span>
        </Link>
    </div>
</template>
