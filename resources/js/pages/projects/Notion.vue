<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Database, RefreshCw, Unlink } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as projectsIndex } from '@/routes/projects';
import { destroy, sync, update } from '@/routes/projects/notion';

const props = defineProps<{
    project: { key: string; name: string };
    notion: {
        linked: boolean;
        databaseId: string | null;
        mirrored: number;
        pending: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const { t } = useI18n();

const form = useForm({ database: '' });

function link() {
    form.put(update.url(props.project.key), {
        preserveScroll: true,
        onSuccess: () => form.reset('database'),
    });
}

function syncExisting() {
    router.post(sync.url(props.project.key), {}, { preserveScroll: true });
}

function unlink() {
    if (!window.confirm(t('notion.unlinkConfirm'))) {
        return;
    }

    router.delete(destroy.url(props.project.key), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('notion.title')" />

    <div class="px-4 py-6">
        <Heading
            :title="$t('notion.title')"
            :description="$t('notion.description', { project: project.name })"
        />

        <div class="max-w-2xl space-y-6">
            <Alert v-if="notion.linked">
                <Database />
                <AlertTitle>{{ $t('notion.linkedTitle') }}</AlertTitle>
                <AlertDescription class="flex flex-col gap-3">
                    <code
                        class="min-w-0 truncate rounded-md bg-muted px-3 py-2 font-mono text-xs"
                        >{{ notion.databaseId }}</code
                    >
                    <p class="text-sm">
                        {{
                            $t('notion.counts', {
                                mirrored: notion.mirrored,
                                pending: notion.pending,
                            })
                        }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="notion.pending === 0"
                            @click="syncExisting"
                        >
                            <RefreshCw class="size-4" />
                            {{ $t('notion.syncExisting') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="unlink"
                        >
                            <Unlink class="size-4" />
                            {{ $t('notion.unlink') }}
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>

            <form class="flex items-end gap-3" @submit.prevent="link">
                <div class="flex-1 space-y-1.5">
                    <Label for="notion-database">{{
                        notion.linked
                            ? $t('notion.changeLabel')
                            : $t('notion.databaseLabel')
                    }}</Label>
                    <Input
                        id="notion-database"
                        v-model="form.database"
                        type="text"
                        placeholder="https://www.notion.so/..."
                        autocomplete="off"
                    />
                    <p
                        v-if="form.errors.database"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.database }}
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        {{ $t('notion.help') }}
                    </p>
                </div>
                <Button type="submit" :disabled="form.processing">
                    {{
                        notion.linked ? $t('notion.change') : $t('notion.link')
                    }}
                </Button>
            </form>
        </div>
    </div>
</template>
