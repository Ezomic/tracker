<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Paperclip, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/issues/attachments';
import type { IssueAttachment } from '@/types';

const props = defineProps<{
    issueIdentifier: string;
    attachments: IssueAttachment[];
    canUpload: boolean;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const error = ref<string | null>(null);

const KILOBYTE = 1024;
const MEGABYTE = KILOBYTE * 1024;

function formatSize(bytes: number): string {
    if (bytes >= MEGABYTE) {
        return `${(bytes / MEGABYTE).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / KILOBYTE))} KB`;
}

function upload(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    error.value = null;

    router.post(
        store({ issue: props.issueIdentifier }).url,
        { file },
        {
            preserveScroll: true,
            onStart: () => {
                uploading.value = true;
            },
            onError: (received) => {
                error.value = received.file ?? null;
            },
            onFinish: () => {
                uploading.value = false;
                input.value = '';
            },
        },
    );
}

function remove(attachment: IssueAttachment) {
    router.delete(
        destroy({ issue: props.issueIdentifier, attachment: attachment.id })
            .url,
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-2">
            <h2 class="text-sm font-medium">
                {{ $t('issue.attachments') }}
            </h2>
            <Button
                v-if="canUpload"
                variant="outline"
                size="sm"
                class="ml-auto h-7 gap-1.5 text-muted-foreground"
                :disabled="uploading"
                @click="fileInput?.click()"
            >
                <Paperclip class="size-3.5" />
                {{ uploading ? $t('issue.attaching') : $t('issue.attachFile') }}
            </Button>
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                data-test="attachment-input"
                @change="upload"
            />
        </div>
        <InputError :message="error ?? undefined" />

        <p
            v-if="attachments.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('issue.noAttachments') }}
        </p>

        <ul v-else class="grid gap-1.5">
            <li
                v-for="attachment in attachments"
                :key="attachment.id"
                class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 px-2 py-1.5 text-sm dark:border-sidebar-border"
            >
                <a
                    :href="attachment.url"
                    target="_blank"
                    rel="noopener"
                    class="flex min-w-0 flex-1 items-center gap-3 hover:underline"
                >
                    <img
                        v-if="attachment.isImage"
                        :src="attachment.url"
                        :alt="attachment.name"
                        class="size-10 shrink-0 rounded object-cover"
                        loading="lazy"
                    />
                    <span
                        v-else
                        class="flex size-10 shrink-0 items-center justify-center rounded bg-muted"
                    >
                        <Paperclip class="size-4 text-muted-foreground" />
                    </span>
                    <span class="truncate">{{ attachment.name }}</span>
                </a>
                <span class="shrink-0 text-xs text-muted-foreground">
                    {{ formatSize(attachment.size) }}
                    <template v-if="attachment.user">
                        · {{ attachment.user.name }}
                    </template>
                </span>
                <button
                    v-if="attachment.canDelete"
                    type="button"
                    class="shrink-0 text-muted-foreground hover:text-destructive"
                    :aria-label="
                        $t('issue.removeAttachment', { name: attachment.name })
                    "
                    @click="remove(attachment)"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </li>
        </ul>
    </div>
</template>
