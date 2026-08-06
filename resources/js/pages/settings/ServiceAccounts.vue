<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bot, Check, Copy, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store } from '@/routes/service-accounts';

interface ServiceAccount {
    id: number;
    name: string;
    projects: string[];
    createdAtDiff: string | null;
    lastUsedAtDiff: string | null;
}

interface ProjectOption {
    key: string;
    name: string;
}

interface CreatedToken {
    name: string;
    plainText: string;
}

defineProps<{
    accounts: ServiceAccount[];
    projects: ProjectOption[];
    abilities: string[];
    createdToken: CreatedToken | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Service accounts', href: index() }],
    },
});

const { t } = useI18n();

const form = useForm<{ name: string; projects: string[] }>({
    name: '',
    projects: [],
});
const copied = ref(false);

function toggleProject(key: string, checked: boolean) {
    form.projects = checked
        ? [...form.projects, key]
        : form.projects.filter((project) => project !== key);
}

function create() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'projects'),
    });
}

function revoke(account: ServiceAccount) {
    if (
        !window.confirm(
            t('serviceAccounts.revokeConfirm', { name: account.name }),
        )
    ) {
        return;
    }

    router.delete(destroy.url(account.id), { preserveScroll: true });
}

async function copyToken(value: string) {
    await navigator.clipboard.writeText(value);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Head :title="$t('serviceAccounts.title')" />

    <h1 class="sr-only">{{ $t('serviceAccounts.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('serviceAccounts.title')"
            :description="$t('serviceAccounts.description')"
        />

        <Alert v-if="createdToken">
            <Bot />
            <AlertTitle>{{
                $t('serviceAccounts.createdTitle', { name: createdToken.name })
            }}</AlertTitle>
            <AlertDescription class="flex flex-col gap-2">
                <span>{{ $t('serviceAccounts.createdWarning') }}</span>
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
                        {{
                            copied
                                ? $t('serviceAccounts.copied')
                                : $t('serviceAccounts.copy')
                        }}
                    </Button>
                </div>
                <p class="text-xs">
                    {{
                        $t('serviceAccounts.abilities', {
                            abilities: abilities.join(', '),
                        })
                    }}
                </p>
            </AlertDescription>
        </Alert>

        <form class="space-y-4" @submit.prevent="create">
            <div class="space-y-1.5">
                <Label for="account-name">{{
                    $t('serviceAccounts.nameLabel')
                }}</Label>
                <Input
                    id="account-name"
                    v-model="form.name"
                    :placeholder="$t('serviceAccounts.namePlaceholder')"
                    autocomplete="off"
                />
                <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="space-y-1.5">
                <Label>{{ $t('serviceAccounts.projectsLabel') }}</Label>
                <p class="text-sm text-muted-foreground">
                    {{ $t('serviceAccounts.projectsHint') }}
                </p>
                <div class="flex flex-wrap gap-3 pt-1">
                    <label
                        v-for="project in projects"
                        :key="project.key"
                        class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                    >
                        <Checkbox
                            :model-value="form.projects.includes(project.key)"
                            @update:model-value="
                                (checked) =>
                                    toggleProject(project.key, checked === true)
                            "
                        />
                        <span class="font-mono text-xs">{{ project.key }}</span>
                        <span class="text-muted-foreground">{{
                            project.name
                        }}</span>
                    </label>
                </div>
                <p v-if="form.errors.projects" class="text-sm text-destructive">
                    {{ form.errors.projects }}
                </p>
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ $t('serviceAccounts.create') }}
            </Button>
        </form>

        <div class="overflow-hidden rounded-lg border border-border">
            <template v-if="accounts.length">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ account.name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <Badge
                                v-for="key in account.projects"
                                :key="key"
                                variant="secondary"
                                class="font-mono text-xs"
                                >{{ key }}</Badge
                            >
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $t('serviceAccounts.created') }}
                            {{ account.createdAtDiff }} ·
                            {{
                                account.lastUsedAtDiff
                                    ? $t('serviceAccounts.lastUsed', {
                                          when: account.lastUsedAtDiff,
                                      })
                                    : $t('serviceAccounts.neverUsed')
                            }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="revoke(account)"
                    >
                        <Trash2 class="size-4" />
                        {{ $t('serviceAccounts.revoke') }}
                    </Button>
                </div>
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <Bot class="size-7 text-muted-foreground" />
                </div>
                <p class="font-medium">{{ $t('serviceAccounts.empty') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('serviceAccounts.emptyBody') }}
                </p>
            </div>
        </div>
    </div>
</template>
