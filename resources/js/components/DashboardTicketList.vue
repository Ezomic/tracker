<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/issues';
import type { DashboardRow } from '@/types';

defineProps<{
    title: string;
    rows: DashboardRow[];
    emptyText: string;
    highlightAge?: boolean;
}>();

const dotClass: Record<DashboardRow['status'], string> = {
    backlog: 'bg-muted-foreground/50',
    in_progress: 'bg-primary',
    in_review: 'bg-sky-500',
    done: 'bg-emerald-500',
};

function ago(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const seconds = Math.max(
        0,
        Math.floor((Date.now() - Date.parse(iso)) / 1000),
    );

    if (seconds < 60) {
        return 'now';
    }

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h`;
    }

    const days = Math.floor(hours / 24);

    if (days < 30) {
        return `${days}d`;
    }

    const months = Math.floor(days / 30);

    if (months < 12) {
        return `${months}mo`;
    }

    return `${Math.floor(months / 12)}y`;
}

function agoClass(iso: string | null, highlight?: boolean): string {
    if (!highlight || iso === null) {
        return 'text-muted-foreground';
    }

    const days = (Date.now() - Date.parse(iso)) / 86_400_000;

    return days >= 30 ? 'text-red-500' : 'text-muted-foreground';
}
</script>

<template>
    <div
        class="flex flex-col rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <h2 class="mb-1 text-sm font-medium">{{ title }}</h2>

        <p v-if="rows.length === 0" class="py-4 text-sm text-muted-foreground">
            {{ emptyText }}
        </p>

        <Link
            v-for="row in rows"
            :key="row.identifier"
            :href="show({ issue: row.identifier })"
            class="-mx-2 flex items-center gap-2.5 rounded-md border-t border-sidebar-border/70 px-2 py-2 first:border-t-0 hover:bg-accent dark:border-sidebar-border"
        >
            <span
                class="size-2 shrink-0 rounded-full"
                :class="dotClass[row.status]"
            />
            <span class="w-16 shrink-0 font-mono text-xs text-muted-foreground">
                {{ row.identifier }}
            </span>
            <span class="min-w-0 flex-1 truncate text-sm">{{ row.title }}</span>
            <span
                class="shrink-0 text-xs tabular-nums"
                :class="agoClass(row.timestamp, highlightAge)"
            >
                {{ ago(row.timestamp) }}
            </span>
        </Link>
    </div>
</template>
