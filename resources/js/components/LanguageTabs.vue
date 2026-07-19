<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { setLocale } from '@/i18n';
import type { Locale } from '@/i18n';
import { update } from '@/routes/locale';

const page = usePage();
const current = computed<Locale>(() => (page.props.locale as Locale) ?? 'en');

const tabs = [
    { value: 'en', label: 'language.english' },
    { value: 'nl', label: 'language.dutch' },
] as const;

function choose(value: Locale) {
    if (value === current.value) {
        return;
    }

    router.patch(
        update().url,
        { locale: value },
        {
            preserveScroll: true,
            onSuccess: () => setLocale(value),
        },
    );
}
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, label } in tabs"
            :key="value"
            @click="choose(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                current === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <span class="text-sm">{{ $t(label) }}</span>
        </button>
    </div>
</template>
