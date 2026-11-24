<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { edit, update } from '@/routes/organization';

defineProps<{
    organization: {
        name: string;
        aiDraftingEnabled: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Organization',
                href: edit(),
            },
        ],
    },
});

function toggleDrafting(value: boolean) {
    router.patch(
        update().url,
        { ai_drafting_enabled: value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('organizationSettings.title')" />

    <h1 class="sr-only">{{ $t('organizationSettings.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('organizationSettings.title')"
            :description="
                $t('organizationSettings.description', {
                    name: organization.name,
                })
            "
        />

        <div class="flex items-start gap-3">
            <Switch
                id="ai-drafting"
                :model-value="organization.aiDraftingEnabled"
                @update:model-value="toggleDrafting"
            />
            <div class="grid gap-1">
                <Label for="ai-drafting">{{
                    $t('organizationSettings.aiDrafting')
                }}</Label>
                <p class="text-sm text-muted-foreground">
                    {{ $t('organizationSettings.aiDraftingHelp') }}
                </p>
            </div>
        </div>
    </div>
</template>
