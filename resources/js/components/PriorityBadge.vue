<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { Issue } from '@/types';

const props = defineProps<{
    priority: Issue['priority'];
}>();
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
        {{ $t(`priority.${props.priority}`) }}
    </Badge>
</template>
