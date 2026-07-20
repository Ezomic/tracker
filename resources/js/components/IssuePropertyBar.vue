<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PriorityBars from '@/components/PriorityBars.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { getInitials } from '@/composables/useInitials';
import type { Issue, IssueUser } from '@/types';

const status = defineModel<Issue['status']>('status', { required: true });
const priority = defineModel<Issue['priority']>('priority', { required: true });
const type = defineModel<Issue['type']>('type', { required: true });
const assigneeId = defineModel<string>('assigneeId', { required: true });
const estimate = defineModel<string>('estimate', { required: true });

const props = defineProps<{
    members: IssueUser[];
}>();

const { t } = useI18n();

const statuses: Issue['status'][] = [
    'backlog',
    'in_progress',
    'in_review',
    'done',
];
const priorities: Issue['priority'][] = [
    'none',
    'low',
    'medium',
    'high',
    'urgent',
];
const types: Issue['type'][] = ['feature', 'fix'];

const statusDot: Record<Issue['status'], string> = {
    backlog: 'bg-muted-foreground/50',
    in_progress: 'bg-primary',
    in_review: 'bg-sky-500',
    done: 'bg-emerald-500',
};

const assignee = computed(() =>
    props.members.find((person) => String(person.id) === assigneeId.value),
);

const editingEstimate = ref(false);

// The estimate is the one free-text property here, so it swaps the pill for an
// input rather than opening a menu; blur commits it like the other controls.
function commitEstimate() {
    editingEstimate.value = false;
}

const pill =
    'inline-flex h-7 items-center gap-1.5 rounded-full border border-border bg-card px-2.5 text-xs text-foreground transition-colors hover:bg-accent';
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <DropdownMenu>
            <DropdownMenuTrigger :class="pill">
                <span class="size-2 rounded-full" :class="statusDot[status]" />
                {{ t(`status.${status}`) }}
                <ChevronDown class="size-3 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem
                    v-for="option in statuses"
                    :key="option"
                    @select="status = option"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="statusDot[option]"
                    />
                    {{ t(`status.${option}`) }}
                    <Check v-if="option === status" class="ml-auto size-3.5" />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger :class="pill">
                <PriorityBars :priority="priority" />
                {{ t(`priority.${priority}`) }}
                <ChevronDown class="size-3 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem
                    v-for="option in priorities"
                    :key="option"
                    @select="priority = option"
                >
                    <PriorityBars :priority="option" />
                    {{ t(`priority.${option}`) }}
                    <Check
                        v-if="option === priority"
                        class="ml-auto size-3.5"
                    />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger :class="pill">
                <Avatar v-if="assignee" class="size-4">
                    <AvatarFallback class="text-[9px]">
                        {{ getInitials(assignee.name) }}
                    </AvatarFallback>
                </Avatar>
                {{ assignee?.name ?? t('issue.unassigned') }}
                <ChevronDown class="size-3 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem @select="assigneeId = 'unassigned'">
                    {{ t('issue.unassigned') }}
                    <Check
                        v-if="assigneeId === 'unassigned'"
                        class="ml-auto size-3.5"
                    />
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-for="person in members"
                    :key="person.id"
                    @select="assigneeId = String(person.id)"
                >
                    <Avatar class="size-4">
                        <AvatarFallback class="text-[9px]">
                            {{ getInitials(person.name) }}
                        </AvatarFallback>
                    </Avatar>
                    {{ person.name }}
                    <Check
                        v-if="assigneeId === String(person.id)"
                        class="ml-auto size-3.5"
                    />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger :class="pill">
                {{ t(`issueType.${type}`) }}
                <ChevronDown class="size-3 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem
                    v-for="option in types"
                    :key="option"
                    @select="type = option"
                >
                    {{ t(`issueType.${option}`) }}
                    <Check v-if="option === type" class="ml-auto size-3.5" />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Input
            v-if="editingEstimate"
            v-model="estimate"
            autofocus
            class="h-7 w-28 rounded-full px-2.5 text-xs"
            :placeholder="t('issue.estimatePlaceholder')"
            @blur="commitEstimate"
            @keydown.enter.prevent="commitEstimate"
            @keydown.esc="commitEstimate"
        />
        <button
            v-else
            type="button"
            :class="pill"
            @click="editingEstimate = true"
        >
            <template v-if="estimate">
                {{ estimate }}
                <span class="text-muted-foreground">
                    {{ t('issue.estimate').toLowerCase() }}
                </span>
            </template>
            <span v-else class="text-muted-foreground">
                {{ t('issue.addEstimate') }}
            </span>
        </button>
    </div>
</template>
