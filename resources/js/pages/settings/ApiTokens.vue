<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store } from '@/routes/api-tokens';

interface ApiToken {
    id: number;
    name: string;
    createdAtDiff: string | null;
    lastUsedAtDiff: string | null;
}

interface CreatedToken {
    name: string;
    plainText: string;
}

defineProps<{
    tokens: ApiToken[];
    createdToken: CreatedToken | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'API tokens', href: index() }],
    },
});

const { t } = useI18n();

const form = useForm({ name: '' });
const copied = ref(false);

function create() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
}

function revoke(token: ApiToken) {
    if (!window.confirm(t('apiTokens.revokeConfirm', { name: token.name }))) {
        return;
    }

    router.delete(destroy.url(token.id), { preserveScroll: true });
}

async function copyToken(value: string) {
    await navigator.clipboard.writeText(value);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Head :title="$t('apiTokens.title')" />

    <h1 class="sr-only">{{ $t('apiTokens.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('apiTokens.title')"
            :description="$t('apiTokens.description')"
        />

        <Alert v-if="createdToken">
            <KeyRound />
            <AlertTitle>{{
                $t('apiTokens.createdTitle', { name: createdToken.name })
            }}</AlertTitle>
            <AlertDescription class="flex flex-col gap-2">
                <span>{{ $t('apiTokens.createdWarning') }}</span>
                <div class="flex items-center gap-2">
                    <code
                        class="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 font-mono text-xs"
                        >{{ createdToken.plainText }}</code
                    >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="copyToken(createdToken.plainText)"
                    >
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                        {{ copied ? $t('apiTokens.copied') : $t('apiTokens.copy') }}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>

        <form class="flex items-end gap-3" @submit.prevent="create">
            <div class="flex-1 space-y-1.5">
                <Label for="token-name">{{ $t('apiTokens.nameLabel') }}</Label>
                <Input
                    id="token-name"
                    v-model="form.name"
                    :placeholder="$t('apiTokens.namePlaceholder')"
                    autocomplete="off"
                />
                <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>
            <Button type="submit" :disabled="form.processing">
                {{ $t('apiTokens.create') }}
            </Button>
        </form>

        <div class="overflow-hidden rounded-lg border border-border">
            <template v-if="tokens.length">
                <div
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ token.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('apiTokens.created') }}
                            {{ token.createdAtDiff }} ·
                            {{
                                token.lastUsedAtDiff
                                    ? $t('apiTokens.lastUsed', {
                                          when: token.lastUsedAtDiff,
                                      })
                                    : $t('apiTokens.neverUsed')
                            }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="revoke(token)"
                    >
                        <Trash2 class="size-4" />
                        {{ $t('apiTokens.revoke') }}
                    </Button>
                </div>
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <KeyRound class="size-7 text-muted-foreground" />
                </div>
                <p class="font-medium">{{ $t('apiTokens.empty') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('apiTokens.emptyBody') }}
                </p>
            </div>
        </div>
    </div>
</template>
