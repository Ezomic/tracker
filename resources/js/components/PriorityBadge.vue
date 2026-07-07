<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { Issue } from '@/types';

const props = defineProps<{
    priority: Issue['priority'];
}>();

const labels: Record<Issue['priority'], string> = {
    none: 'No priority',
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};
</script>

<template>
    <Badge
        v-if="props.priority !== 'none'"
        :variant="
            props.priority === 'urgent' || props.priority === 'high'
                ? 'destructive'
                : 'outline'
        "
    >
        {{ labels[props.priority] }}
    </Badge>
</template>
