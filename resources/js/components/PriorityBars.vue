<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Issue } from '@/types';

const props = defineProps<{
    priority: Issue['priority'];
}>();

const { t } = useI18n();

// Colour on the board means status, and only status. Encoding priority as hue
// as well put a low-priority stripe (sky) inside the In review lane (sky) and a
// high-priority one (orange) inside the coral In progress lane, so priority is
// carried by how many bars are filled instead.
const filled: Record<Issue['priority'], number> = {
    none: 0,
    low: 1,
    medium: 2,
    high: 3,
    urgent: 4,
};

const heights = ['h-1', 'h-1.5', 'h-2', 'h-2.5'];

const count = computed(() => filled[props.priority]);
</script>

<template>
    <span
        class="inline-flex items-end gap-px"
        role="img"
        :aria-label="t(`priority.${priority}`)"
        :title="t(`priority.${priority}`)"
    >
        <span
            v-for="(height, index) in heights"
            :key="height"
            class="w-[3px] rounded-[1px]"
            :class="[
                height,
                index < count ? 'bg-foreground/70' : 'bg-muted-foreground/25',
            ]"
        />
    </span>
</template>
