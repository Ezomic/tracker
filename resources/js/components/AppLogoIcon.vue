<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="12"
            cy="12"
            r="7"
            fill="none"
            stroke="currentColor"
            stroke-width="2.4"
        />
        <circle cx="12" cy="12" r="2.8" fill="currentColor" />
    </svg>
</template>
