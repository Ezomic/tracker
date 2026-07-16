<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        segments: { color: string; value: number }[];
        thickness?: number;
    }>(),
    {
        thickness: 4,
    },
);

const total = computed(() =>
    props.segments.reduce((sum, segment) => sum + segment.value, 0),
);

const arcs = computed(() => {
    let cumulative = 0;

    return props.segments
        .filter((segment) => segment.value > 0)
        .map((segment) => {
            const percent = total.value
                ? (segment.value / total.value) * 100
                : 0;
            const arc = {
                color: segment.color,
                dash: percent,
                offset: 25 - cumulative,
            };
            cumulative += percent;

            return arc;
        });
});
</script>

<template>
    <svg viewBox="0 0 36 36" class="size-full">
        <circle
            cx="18"
            cy="18"
            r="15.915"
            fill="none"
            class="stroke-muted"
            :stroke-width="thickness"
        />
        <circle
            v-for="(arc, index) in arcs"
            :key="index"
            cx="18"
            cy="18"
            r="15.915"
            fill="none"
            :stroke="arc.color"
            :stroke-width="thickness"
            :stroke-dasharray="`${arc.dash} ${100 - arc.dash}`"
            :stroke-dashoffset="arc.offset"
        />
    </svg>
</template>
