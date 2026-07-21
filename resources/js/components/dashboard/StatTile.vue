<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/components/dashboard/Sparkline.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        delta?: number | null;
        deltaUnit?: string;
        invert?: boolean;
        accent?: string;
        hero?: boolean;
        spark?: number[];
    }>(),
    {
        delta: null,
        deltaUnit: '%',
        invert: false,
        accent: 'text-muted-foreground',
        hero: false,
        spark: () => [],
    },
);

const kind = computed<'up' | 'down' | 'flat'>(() => {
    if (props.delta === null || props.delta === 0) {
        return 'flat';
    }

    return props.delta > 0 ? 'up' : 'down';
});

const deltaTone = computed(() => {
    if (kind.value === 'flat') {
        return 'bg-muted text-muted-foreground';
    }

    const good = props.invert ? kind.value === 'down' : kind.value === 'up';

    return good
        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
});

const deltaLabel = computed(() => {
    if (props.delta === null) {
        return '';
    }

    const sign = props.delta > 0 ? '+' : '';

    return `${sign}${props.delta}${props.deltaUnit}`;
});
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-2 rounded-xl border p-4"
        :class="
            hero
                ? 'border-primary/30 bg-gradient-to-br from-primary/12 to-card'
                : 'border-sidebar-border/70 bg-card dark:border-sidebar-border'
        "
    >
        <div class="flex items-center justify-between gap-2">
            <span
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ label }}
            </span>
            <span
                v-if="delta !== null"
                class="rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                :class="deltaTone"
            >
                {{ deltaLabel }}
            </span>
        </div>
        <span class="text-2xl leading-none font-semibold tabular-nums">
            {{ value }}
        </span>
        <div :class="accent">
            <Sparkline v-if="spark.length" :values="spark" />
        </div>
    </div>
</template>
