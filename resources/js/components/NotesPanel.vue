<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index, show } from '@/routes/projects/notes';
import type { BoardNote } from '@/types';

defineProps<{
    notes: BoardNote[];
    showProject: boolean;
    projectKey?: string;
}>();

function when(iso: string): string {
    return new Date(iso).toLocaleDateString();
}
</script>

<template>
    <div class="flex min-h-0 flex-col">
        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            <p
                v-if="notes.length === 0"
                class="px-2 py-4 text-center text-xs text-muted-foreground/70"
            >
                {{ $t('notes.empty') }}
            </p>

            <Link
                v-for="note in notes"
                :key="note.id"
                :href="show({ project: note.projectKey, note: note.id })"
                class="flex flex-col gap-0.5 rounded-lg px-2 py-2 hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span class="truncate text-sm font-medium">
                    {{ note.title }}
                </span>
                <span class="truncate text-xs text-muted-foreground">
                    <template v-if="showProject">
                        {{ note.projectName }} ·
                    </template>
                    {{ when(note.updatedAt) }}
                </span>
            </Link>
        </div>

        <Link
            v-if="projectKey"
            :href="index(projectKey)"
            class="shrink-0 border-t px-3 py-2 text-xs font-medium text-muted-foreground hover:text-foreground"
        >
            {{ $t('notes.manage') }}
        </Link>
    </div>
</template>
