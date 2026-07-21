<script setup lang="ts">
defineProps<{
    title?: string;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <header
            v-if="title || $slots.action"
            class="mb-3 flex items-center justify-between gap-3"
        >
            <h2 class="text-sm font-medium">{{ title }}</h2>
            <slot name="action" />
        </header>
        <slot />
    </section>
</template>
