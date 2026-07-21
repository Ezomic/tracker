<script setup lang="ts">
import { computed } from 'vue';
import DonutChart from '@/components/DonutChart.vue';
import type { ActiveByProject } from '@/types';

const props = withDefaults(
    defineProps<{
        projects: ActiveByProject[];
        size?: 'md' | 'sm';
    }>(),
    {
        size: 'md',
    },
);

const segments = computed(() =>
    props.projects.map((project) => ({
        color: project.color,
        value: project.count,
    })),
);

const total = computed(() =>
    props.projects.reduce((sum, project) => sum + project.count, 0),
);
</script>

<template>
    <p
        v-if="total === 0"
        class="py-8 text-center text-sm text-muted-foreground"
    >
        {{ $t('dashboard.noActiveTickets') }}
    </p>
    <div v-else class="flex items-center gap-4">
        <div
            class="relative shrink-0"
            :class="size === 'sm' ? 'size-24' : 'size-32'"
        >
            <DonutChart :segments="segments" :thickness="4" />
            <div
                class="absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-xl font-semibold tabular-nums">{{
                    total
                }}</span>
                <span
                    class="text-[10.5px] tracking-wide text-muted-foreground uppercase"
                >
                    {{ $t('dashboard.active') }}
                </span>
            </div>
        </div>
        <div class="grid min-w-0 flex-1 gap-1.5">
            <div
                v-for="project in projects"
                :key="project.key"
                class="flex items-center gap-2 text-sm"
            >
                <span
                    class="size-2.5 shrink-0 rounded-[3px]"
                    :style="{ backgroundColor: project.color }"
                />
                <span class="min-w-0 flex-1 truncate">{{ project.name }}</span>
                <span
                    class="shrink-0 font-semibold text-muted-foreground tabular-nums"
                >
                    {{ project.count }}
                </span>
            </div>
        </div>
    </div>
</template>
