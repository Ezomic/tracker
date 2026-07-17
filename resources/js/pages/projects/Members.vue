<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
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
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as projectsIndex } from '@/routes/projects';
import { destroy, update } from '@/routes/projects/members';
import type { ProjectMember, ProjectRole } from '@/types';

const props = defineProps<{
    project: { key: string; name: string };
    members: ProjectMember[];
    canManage: boolean;
    currentUserId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const removing = ref<ProjectMember | null>(null);

const roleLabels: Record<ProjectRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function changeRole(member: ProjectMember, role: string) {
    if (role === member.role) {
        return;
    }

    router.patch(
        update({ project: props.project.key, user: member.id }).url,
        { role },
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
    props.canManage && member.role !== 'owner';
</script>

<template>
    <Head :title="`${project.name} members`" />

    <div class="flex flex-col gap-4 p-4">
        <Heading
            variant="small"
            :title="`${project.name} members`"
            description="People with access to this project and their roles"
        />

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
                        :model-value="member.role"
                        @update:model-value="
                            (value) => changeRole(member, value as string)
                        "
                    >
                        <SelectTrigger class="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="member">Member</SelectItem>
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
                    {{ roleLabels[member.role] }}
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
                    lose access to the project until invited again.
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
