<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Kanban, Ticket } from '@lucide/vue';
import { computed } from 'vue';
import { board as issuesBoard, index as issuesIndex } from '@/routes/issues';
import {
    board as projectBoard,
    tickets as projectTickets,
} from '@/routes/projects';

const props = defineProps<{
    active: 'board' | 'list';
    projectKey?: string;
}>();

const boardHref = computed(() =>
    props.projectKey ? projectBoard(props.projectKey) : issuesBoard(),
);

const listHref = computed(() =>
    props.projectKey ? projectTickets(props.projectKey) : issuesIndex(),
);

const itemClass =
    'flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium transition-colors';
</script>

<template>
    <div class="flex items-center gap-1 rounded-lg border bg-muted/50 p-0.5">
        <Link
            :href="boardHref"
            :class="[
                itemClass,
                active === 'board'
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <Kanban class="size-4" />
            <span>Board</span>
        </Link>
        <Link
            :href="listHref"
            :class="[
                itemClass,
                active === 'list'
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <Ticket class="size-4" />
            <span>List</span>
        </Link>
    </div>
</template>
