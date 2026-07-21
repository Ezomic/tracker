<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        values: number[];
        width?: number;
        height?: number;
    }>(),
    {
        width: 120,
        height: 28,
    },
);

const points = computed(() => {
    const values = props.values;

    if (values.length === 0) {
        return '';
    }

    const pad = 3;
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;
    const innerH = props.height - pad * 2;
    const step =
        values.length > 1 ? props.width / (values.length - 1) : props.width;

    return values
        .map((value, index) => {
            const x = values.length > 1 ? index * step : props.width / 2;
            const y = pad + innerH - ((value - min) / span) * innerH;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
});
</script>

<template>
    <svg
        v-if="points"
        :viewBox="`0 0 ${width} ${height}`"
        preserveAspectRatio="none"
        class="block h-7 w-full text-current"
        aria-hidden="true"
    >
        <polyline
            :points="points"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
