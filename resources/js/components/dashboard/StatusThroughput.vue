<script setup lang="ts">
import { computed } from 'vue';
import { statusDotClass } from '@/lib/issueStatus';
import type { IssueStatusKey, StatusBreakdown } from '@/types';

const props = defineProps<{
    breakdown: StatusBreakdown;
}>();

const keys: IssueStatusKey[] = ['backlog', 'in_progress', 'in_review', 'done'];

const max = computed(() =>
    Math.max(1, ...keys.map((key) => props.breakdown[key])),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            v-for="key in keys"
            :key="key"
            class="grid grid-cols-[5rem_1fr_1.75rem] items-center gap-2.5 text-sm"
        >
            <span class="truncate text-muted-foreground">{{
                $t(`status.${key}`)
            }}</span>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full"
                    :class="statusDotClass[key]"
                    :style="{ width: `${(breakdown[key] / max) * 100}%` }"
                />
            </div>
            <span class="text-right font-semibold tabular-nums">{{
                breakdown[key]
            }}</span>
        </div>
    </div>
</template>
