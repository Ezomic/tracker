<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { LabelColor } from '@/types';

defineProps<{
    name: string;
    color: LabelColor;
}>();

const classes: Record<LabelColor, string> = {
    gray: 'border-transparent bg-muted text-muted-foreground',
    red: 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    yellow: 'border-transparent bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    green: 'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    blue: 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    purple: 'border-transparent bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300',
};
</script>

<template>
    <Badge :class="classes[color]">{{ name }}</Badge>
</template>
