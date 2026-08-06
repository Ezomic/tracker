<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Send, Trash2, Webhook } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { index as projectsIndex } from '@/routes/projects';
import { destroy, store, test, update } from '@/routes/projects/webhooks';

interface ProjectWebhook {
    id: number;
    url: string;
    active: boolean;
    lastDeliveredAtDiff: string | null;
    lastStatus: number | null;
    lastError: string | null;
}

const props = defineProps<{
    project: { key: string; name: string };
    webhooks: ProjectWebhook[];
    signatureHeader: string;
    createdSecret: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const { t } = useI18n();

const form = useForm({ url: '' });
const copied = ref(false);

function add() {
    form.post(store.url(props.project.key), {
        preserveScroll: true,
        onSuccess: () => form.reset('url'),
    });
}

function sendTest(webhook: ProjectWebhook) {
    router.post(
        test.url({ project: props.project.key, webhook: webhook.id }),
        {},
        { preserveScroll: true },
    );
}

function toggle(webhook: ProjectWebhook, active: boolean) {
    router.patch(
        update.url({ project: props.project.key, webhook: webhook.id }),
        { active },
        { preserveScroll: true },
    );
}

function remove(webhook: ProjectWebhook) {
    if (!window.confirm(t('webhooks.removeConfirm', { url: webhook.url }))) {
        return;
    }

    router.delete(
        destroy.url({ project: props.project.key, webhook: webhook.id }),
        {
            preserveScroll: true,
        },
    );
}

async function copySecret(value: string) {
    await navigator.clipboard.writeText(value);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Head :title="$t('webhooks.title')" />

    <div class="px-4 py-6">
        <Heading
            :title="$t('webhooks.title')"
            :description="$t('webhooks.description', { project: project.name })"
        />

        <div class="max-w-2xl space-y-6">
            <Alert v-if="createdSecret">
                <Webhook />
                <AlertTitle>{{ $t('webhooks.secretTitle') }}</AlertTitle>
                <AlertDescription class="flex flex-col gap-2">
                    <span>{{ $t('webhooks.secretWarning') }}</span>
                    <div class="flex items-center gap-2">
                        <code
                            class="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 font-mono text-xs"
                            >{{ createdSecret }}</code
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copySecret(createdSecret)"
                        >
                            <Check v-if="copied" class="size-4" />
                            <Copy v-else class="size-4" />
                            {{
                                copied
                                    ? $t('webhooks.copied')
                                    : $t('webhooks.copy')
                            }}
                        </Button>
                    </div>
                    <p class="text-xs">
                        {{
                            $t('webhooks.secretUsage', {
                                header: signatureHeader,
                            })
                        }}
                    </p>
                </AlertDescription>
            </Alert>

            <form class="flex items-end gap-3" @submit.prevent="add">
                <div class="flex-1 space-y-1.5">
                    <Label for="webhook-url">{{
                        $t('webhooks.urlLabel')
                    }}</Label>
                    <Input
                        id="webhook-url"
                        v-model="form.url"
                        type="url"
                        placeholder="https://snag.thijssensoftware.nl/webhooks/tracker"
                        autocomplete="off"
                    />
                    <p v-if="form.errors.url" class="text-sm text-destructive">
                        {{ form.errors.url }}
                    </p>
                </div>
                <Button type="submit" :disabled="form.processing">
                    {{ $t('webhooks.add') }}
                </Button>
            </form>

            <div class="overflow-hidden rounded-lg border border-border">
                <template v-if="webhooks.length">
                    <div
                        v-for="webhook in webhooks"
                        :key="webhook.id"
                        class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm">
                                {{ webhook.url }}
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <Badge
                                    v-if="webhook.lastStatus"
                                    :variant="
                                        webhook.lastStatus < 300
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                >
                                    {{ webhook.lastStatus }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        webhook.lastDeliveredAtDiff
                                            ? $t('webhooks.lastDelivered', {
                                                  when: webhook.lastDeliveredAtDiff,
                                              })
                                            : $t('webhooks.neverDelivered')
                                    }}
                                </span>
                            </div>
                            <p
                                v-if="webhook.lastError"
                                class="mt-1 truncate text-xs text-destructive"
                            >
                                {{ webhook.lastError }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Switch
                                :model-value="webhook.active"
                                :aria-label="$t('webhooks.active')"
                                @update:model-value="
                                    (value) => toggle(webhook, value === true)
                                "
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="sendTest(webhook)"
                            >
                                <Send class="size-4" />
                                {{ $t('webhooks.test') }}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="remove(webhook)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </template>

                <div v-else class="p-8 text-center">
                    <div
                        class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-muted"
                    >
                        <Webhook class="size-7 text-muted-foreground" />
                    </div>
                    <p class="font-medium">{{ $t('webhooks.empty') }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $t('webhooks.emptyBody') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
