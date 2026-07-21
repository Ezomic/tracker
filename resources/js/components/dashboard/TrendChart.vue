<script setup lang="ts">
import { computed } from 'vue';
import type { TrendPoint } from '@/types';

const props = defineProps<{
    points: TrendPoint[];
}>();

const W = 720;
const H = 180;
const PAD = 12;

const max = computed(() =>
    Math.max(1, ...props.points.flatMap((p) => [p.opened, p.completed])),
);

function coords(pick: (p: TrendPoint) => number): { x: number; y: number }[] {
    const points = props.points;
    const step = points.length > 1 ? W / (points.length - 1) : W;

    return points.map((point, index) => ({
        x: points.length > 1 ? index * step : W / 2,
        y: PAD + (H - PAD * 2) * (1 - pick(point) / max.value),
    }));
}

function line(pick: (p: TrendPoint) => number): string {
    return coords(pick)
        .map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`)
        .join(' ');
}

const completedLine = computed(() => line((p) => p.completed));
const openedLine = computed(() => line((p) => p.opened));

const completedArea = computed(() => {
    const pts = coords((p) => p.completed);

    if (pts.length === 0) {
        return '';
    }

    const first = pts[0];
    const last = pts[pts.length - 1];

    return `M${first.x.toFixed(1)},${first.y.toFixed(1)} ${pts
        .slice(1)
        .map((c) => `L${c.x.toFixed(1)},${c.y.toFixed(1)}`)
        .join(' ')} L${last.x.toFixed(1)},${H} L${first.x.toFixed(1)},${H} Z`;
});

const endpoints = computed(() => {
    const completed = coords((p) => p.completed);
    const opened = coords((p) => p.opened);

    return {
        completed: completed[completed.length - 1],
        opened: opened[opened.length - 1],
    };
});
</script>

<template>
    <svg
        :viewBox="`0 0 ${W} ${H}`"
        preserveAspectRatio="none"
        class="block h-44 w-full"
        role="img"
        :aria-label="$t('dashboard.trendTitle')"
    >
        <line
            v-for="y in [45, 90, 135]"
            :key="y"
            :x1="0"
            :x2="W"
            :y1="y"
            :y2="y"
            class="stroke-border"
            stroke-width="1"
        />
        <path :d="completedArea" class="fill-primary/15" />
        <polyline
            :points="openedLine"
            fill="none"
            class="stroke-sky-500"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <polyline
            :points="completedLine"
            fill="none"
            class="stroke-primary"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle
            v-if="endpoints.opened"
            :cx="endpoints.opened.x"
            :cy="endpoints.opened.y"
            r="4"
            class="fill-sky-500"
        />
        <circle
            v-if="endpoints.completed"
            :cx="endpoints.completed.x"
            :cy="endpoints.completed.y"
            r="4"
            class="fill-primary"
        />
    </svg>
</template>
