<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { show as showIssue } from '@/routes/issues';
import { read, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

const page = usePage();
const { t } = useI18n();

const notifications = computed<AppNotification[]>(
    () => page.props.notifications ?? [],
);
const unread = computed<number>(() => page.props.unreadNotificationsCount ?? 0);

const messageKey: Record<AppNotification['data']['type'], string> = {
    issue_assigned: 'notifications.assigned',
    comment_mention: 'notifications.mentioned',
    issue_commented: 'notifications.commented',
};

function describe(notification: AppNotification): string {
    return t(messageKey[notification.data.type], {
        actor: notification.data.actorName,
        identifier: notification.data.issueIdentifier,
    });
}

function relativeTime(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const diff = Date.now() - new Date(iso).getTime();
    const minutes = Math.round(diff / 60000);

    if (minutes < 1) {
        return t('notifications.justNow');
    }

    if (minutes < 60) {
        return t('notifications.minutesAgo', { count: minutes });
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return t('notifications.hoursAgo', { count: hours });
    }

    return new Date(iso).toLocaleDateString();
}

function open(notification: AppNotification) {
    const visit = () =>
        router.visit(showIssue(notification.data.issueIdentifier).url);

    if (notification.read) {
        visit();

        return;
    }

    router.patch(
        read(notification.id).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications', 'unreadNotificationsCount'],
            onSuccess: visit,
        },
    );
}

function markAllRead() {
    router.post(
        readAll().url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications', 'unreadNotificationsCount'],
        },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-8"
                :aria-label="t('notifications.label')"
            >
                <Bell class="size-4" />
                <span
                    v-if="unread > 0"
                    class="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground tabular-nums"
                >
                    {{ unread > 9 ? '9+' : unread }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0">
            <div
                class="flex items-center justify-between border-b border-border px-3 py-2"
            >
                <span class="text-sm font-medium">
                    {{ t('notifications.title') }}
                </span>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    {{ t('notifications.markAllRead') }}
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <p
                    v-if="notifications.length === 0"
                    class="px-3 py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('notifications.empty') }}
                </p>

                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full flex-col gap-0.5 border-b border-border/60 px-3 py-2.5 text-left last:border-0 hover:bg-accent/60"
                    @click="open(notification)"
                >
                    <span class="flex items-center gap-2">
                        <span
                            v-if="!notification.read"
                            class="size-1.5 shrink-0 rounded-full bg-primary"
                        />
                        <span
                            class="text-sm"
                            :class="{ 'font-medium': !notification.read }"
                        >
                            {{ describe(notification) }}
                        </span>
                    </span>
                    <span
                        v-if="notification.data.excerpt"
                        class="truncate pl-3.5 text-xs text-muted-foreground"
                    >
                        {{ notification.data.excerpt }}
                    </span>
                    <span class="pl-3.5 text-[11px] text-muted-foreground/70">
                        {{ relativeTime(notification.createdAt) }}
                    </span>
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
