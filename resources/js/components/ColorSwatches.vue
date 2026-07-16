<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        palette: string[];
        used?: string[];
        current?: string | null;
    }>(),
    {
        used: () => [],
        current: null,
    },
);

defineEmits<{
    'update:modelValue': [value: string];
}>();

const usedSet = computed(() => new Set(props.used));

function stateOf(color: string): 'current' | 'used' | 'free' {
    if (props.current !== null && color === props.current) {
        return 'current';
    }

    return usedSet.value.has(color) ? 'used' : 'free';
}

function titleOf(color: string): string | undefined {
    const state = stateOf(color);

    if (state === 'current') {
        return 'Current color';
    }

    return state === 'used' ? 'Used by another project' : undefined;
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2.5">
        <button
            v-for="color in palette"
            :key="color"
            type="button"
            class="flex size-6 items-center justify-center rounded-full ring-offset-2 ring-offset-background"
            :class="{
                'ring-2 ring-foreground': stateOf(color) === 'current',
                'ring-1 ring-muted-foreground/50': stateOf(color) === 'used',
            }"
            :style="{ backgroundColor: color }"
            :aria-label="titleOf(color) ?? `Use color ${color}`"
            :title="titleOf(color)"
            @click="$emit('update:modelValue', color)"
        >
            <Check v-if="modelValue === color" class="size-3.5 text-white" />
        </button>
    </div>
</template>
