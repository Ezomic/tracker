<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as projectsIndex } from '@/routes/projects';
import { destroy, store, update } from '@/routes/projects/members';
import type { AssignableMember, ProjectLevel, ProjectMember } from '@/types';

const props = defineProps<{
    project: { key: string; name: string };
    members: ProjectMember[];
    assignable: AssignableMember[];
    canManage: boolean;
    currentUserId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const addOpen = ref(false);
const addUserId = ref<string>('');
const addLevel = ref<ProjectLevel>('write');
const addError = ref<string | null>(null);
const removing = ref<ProjectMember | null>(null);

const levelLabels: Record<ProjectLevel, string> = {
    admin: 'Admin',
    write: 'Write',
    read: 'Read',
};

const hasAssignable = computed(() => props.assignable.length > 0);

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function add() {
    if (addUserId.value === '') {
        addError.value = 'Pick someone to add.';

        return;
    }

    router.post(
        store({ project: props.project.key }).url,
        { user_id: Number(addUserId.value), level: addLevel.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                addOpen.value = false;
                addUserId.value = '';
                addLevel.value = 'write';
                addError.value = null;
            },
            onError: (errors) => {
                addError.value = errors.user_id ?? errors.level ?? null;
            },
        },
    );
}

function changeLevel(member: ProjectMember, level: string) {
    if (level === member.level) {
        return;
    }

    router.patch(
        update({ project: props.project.key, user: member.id }).url,
        { level },
        { preserveScroll: true },
    );
}

function remove() {
    if (removing.value === null) {
        return;
    }

    router.delete(
        destroy({ project: props.project.key, user: removing.value.id }).url,
        {
            preserveScroll: true,
            onFinish: () => {
                removing.value = null;
            },
        },
    );
}

const canManageMember = (member: ProjectMember) =>
    props.canManage && member.id !== props.currentUserId;
</script>

<template>
    <Head :title="`${project.name} members`" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`${project.name} members`"
                description="People with access to this project and their level"
            />

            <Dialog v-if="canManage" v-model:open="addOpen">
                <DialogTrigger as-child>
                    <Button
                        size="sm"
                        class="shrink-0"
                        :disabled="!hasAssignable"
                    >
                        <UserPlus />
                        Add member
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add to {{ project.name }}</DialogTitle>
                        <DialogDescription>
                            Grant an organization member access to this project.
                            Invite new people from
                            <span class="font-medium"
                                >Settings &rarr; Members</span
                            >.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-6">
                        <div class="grid gap-2">
                            <Label>Member</Label>
                            <Select v-model="addUserId">
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Select a member"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="candidate in assignable"
                                        :key="candidate.id"
                                        :value="String(candidate.id)"
                                    >
                                        {{ candidate.name }} ({{
                                            candidate.email
                                        }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="addError ?? undefined" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Level</Label>
                            <Select v-model="addLevel">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="admin">Admin</SelectItem>
                                    <SelectItem value="write">Write</SelectItem>
                                    <SelectItem value="read">Read</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button type="button" @click="add">Add member</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <Avatar class="size-8 shrink-0">
                    <AvatarFallback class="text-xs">
                        {{ initials(member.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ member.name }}
                        <span
                            v-if="member.id === currentUserId"
                            class="text-muted-foreground"
                        >
                            (you)
                        </span>
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ member.email }}
                    </p>
                </div>

                <template v-if="canManageMember(member)">
                    <Select
                        :model-value="member.level"
                        @update:model-value="
                            (value) => changeLevel(member, value as string)
                        "
                    >
                        <SelectTrigger class="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="write">Write</SelectItem>
                            <SelectItem value="read">Read</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="removing = member"
                    >
                        Remove
                    </Button>
                </template>
                <Badge v-else variant="secondary" class="shrink-0">
                    {{ levelLabels[member.level] }}
                </Badge>
            </div>
        </div>
    </div>

    <Dialog
        :open="removing !== null"
        @update:open="(open) => !open && (removing = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Remove member</DialogTitle>
                <DialogDescription>
                    Remove {{ removing?.name }} from {{ project.name }}? They'll
                    lose access to the project until added again.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button variant="destructive" @click="remove">Remove</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
