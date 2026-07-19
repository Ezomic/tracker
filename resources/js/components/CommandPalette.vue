<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Kanban, LayoutGrid, Plus, Search, Ticket } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { dashboard } from '@/routes';
import { board as issuesBoard, index as issuesIndex } from '@/routes/issues';
import type { SidebarProject } from '@/types';

type Command = {
    group: string;
    label: string;
    hint?: string;
    href: string;
    icon?: LucideIcon | Component;
    dot?: string;
};

const { open, close, toggle } = useCommandPalette();
const { t } = useI18n();
const page = usePage();
const projects = computed<SidebarProject[]>(
    () => page.props.sidebarProjects ?? [],
);

const query = ref('');
const selected = ref(0);
const inputEl = ref<HTMLInputElement | null>(null);

const commands = computed<Command[]>(() => {
    const base: Command[] = [
        {
            group: t('commandPalette.groupActions'),
            label: t('commandPalette.createIssue'),
            href: issuesIndex().url,
            icon: Plus,
        },
        {
            group: t('commandPalette.groupGoTo'),
            label: t('commandPalette.allIssues'),
            href: issuesIndex().url,
            icon: Ticket,
        },
        {
            group: t('commandPalette.groupGoTo'),
            label: t('commandPalette.board'),
            href: issuesBoard().url,
            icon: Kanban,
        },
        {
            group: t('commandPalette.groupGoTo'),
            label: t('commandPalette.dashboard'),
            href: dashboard().url,
            icon: LayoutGrid,
        },
    ];

    const projectCommands = projects.value.flatMap((project): Command[] => [
        {
            group: t('commandPalette.groupProjects'),
            label: t('commandPalette.projectTickets', { key: project.key }),
            hint: project.name,
            href: `/${project.key}/tickets`,
            dot: project.color,
        },
        {
            group: t('commandPalette.groupProjects'),
            label: t('commandPalette.projectBoard', { key: project.key }),
            hint: project.name,
            href: `/${project.key}/board`,
            dot: project.color,
        },
    ]);

    return [...base, ...projectCommands];
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (q === '') {
        return commands.value;
    }

    return commands.value.filter(
        (command) =>
            command.label.toLowerCase().includes(q) ||
            command.hint?.toLowerCase().includes(q),
    );
});

const groupedFiltered = computed(() => {
    const groups: { group: string; items: Command[] }[] = [];

    filtered.value.forEach((command) => {
        const existing = groups.find((entry) => entry.group === command.group);

        if (existing) {
            existing.items.push(command);
        } else {
            groups.push({ group: command.group, items: [command] });
        }
    });

    return groups;
});

function indexOf(command: Command): number {
    return filtered.value.indexOf(command);
}

watch(query, () => {
    selected.value = 0;
});

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        selected.value = 0;
        nextTick(() => inputEl.value?.focus());
    }
});

function move(delta: number) {
    const count = filtered.value.length;

    if (count === 0) {
        return;
    }

    selected.value = (selected.value + delta + count) % count;
}

function run(command: Command) {
    close();
    router.visit(command.href);
}

function onEnter() {
    const command = filtered.value[selected.value];

    if (command) {
        run(command);
    }
}

function onGlobalKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        toggle();
    }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onUnmounted(() => window.removeEventListener('keydown', onGlobalKeydown));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="gap-0 overflow-hidden p-0 sm:max-w-lg"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="onEnter"
        >
            <div
                class="flex items-center gap-2 border-b border-border px-3.5 py-3"
            >
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="query"
                    :placeholder="$t('commandPalette.placeholder')"
                    class="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                />
            </div>

            <div class="max-h-80 overflow-y-auto p-1.5">
                <p
                    v-if="filtered.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ $t('commandPalette.noResults') }}
                </p>

                <div v-for="section in groupedFiltered" :key="section.group">
                    <p
                        class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                    >
                        {{ section.group }}
                    </p>
                    <button
                        v-for="command in section.items"
                        :key="command.label"
                        type="button"
                        class="relative flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left text-sm"
                        :class="
                            indexOf(command) === selected
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-accent/60'
                        "
                        @click="run(command)"
                        @mousemove="selected = indexOf(command)"
                    >
                        <span
                            v-if="indexOf(command) === selected"
                            class="absolute top-1.5 bottom-1.5 left-0 w-0.5 rounded-full bg-primary"
                        />
                        <component
                            :is="command.icon"
                            v-if="command.icon"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span
                            v-else-if="command.dot"
                            class="size-2 shrink-0 rounded-full"
                            :style="{ backgroundColor: command.dot }"
                        />
                        <span class="truncate">{{ command.label }}</span>
                        <span
                            v-if="command.hint"
                            class="ml-auto truncate text-xs text-muted-foreground"
                        >
                            {{ command.hint }}
                        </span>
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
