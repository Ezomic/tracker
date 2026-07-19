<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import LanguageTabs from '@/components/LanguageTabs.vue';
import { edit } from '@/routes/language';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Language',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('language.title')" />

    <h1 class="sr-only">{{ $t('language.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('language.title')"
            :description="$t('language.description')"
        />
        <LanguageTabs />
    </div>
</template>
