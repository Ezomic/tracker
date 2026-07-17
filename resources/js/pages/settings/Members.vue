<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Mail, UserPlus } from '@lucide/vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as revokeInvitation,
    resend as resendInvitation,
    store as storeInvitation,
} from '@/routes/invitations';
import { index, update } from '@/routes/members';
import { destroy as destroyMember } from '@/routes/members';
import type {
    OrganizationInvitation,
    OrganizationMember,
    OrganizationRole,
    ProjectLevel,
} from '@/types';

const props = defineProps<{
    organization: { name: string };
    members: OrganizationMember[];
    invitations: OrganizationInvitation[];
    projects: { id: number; key: string; name: string }[];
    currentUserId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: index() }],
    },
});

const roleLabels: Record<OrganizationRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
    guest: 'Guest',
};

const levelLabels: Record<ProjectLevel, string> = {
    admin: 'Admin',
    write: 'Write',
    read: 'Read',
};

const inviteOpen = ref(false);
const inviteEmail = ref('');
const inviteRole = ref<Exclude<OrganizationRole, 'owner' | 'admin'>>('member');
const inviteProjectId = ref<string>('');
const inviteLevel = ref<ProjectLevel>('write');
const inviteErrors = ref<Record<string, string>>({});

const removing = ref<OrganizationMember | null>(null);

const hasProject = computed(() => inviteProjectId.value !== '');

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function formatExpiry(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const canManageMember = (member: OrganizationMember) =>
    member.id !== props.currentUserId && member.role !== 'owner';

function invite() {
    inviteErrors.value = {};

    router.post(
        storeInvitation().url,
        {
            email: inviteEmail.value,
            role: inviteRole.value,
            project_id: hasProject.value ? Number(inviteProjectId.value) : null,
            level: hasProject.value ? inviteLevel.value : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                inviteOpen.value = false;
                inviteEmail.value = '';
                inviteRole.value = 'member';
                inviteProjectId.value = '';
                inviteLevel.value = 'write';
            },
            onError: (errors) => {
                inviteErrors.value = errors as Record<string, string>;
            },
        },
    );
}

function changeRole(member: OrganizationMember, role: string) {
    if (role === member.role) {
        return;
    }

    router.patch(
        update({ user: member.id }).url,
        { role },
        { preserveScroll: true },
    );
}

function resend(invitation: OrganizationInvitation) {
    router.post(
        resendInvitation({ invitation: invitation.id }).url,
        {},
        { preserveScroll: true },
    );
}

function revoke(invitation: OrganizationInvitation) {
    router.delete(revokeInvitation({ invitation: invitation.id }).url, {
        preserveScroll: true,
    });
}

function remove() {
    if (removing.value === null) {
        return;
    }

    router.delete(destroyMember({ user: removing.value.id }).url, {
        preserveScroll: true,
        onFinish: () => {
            removing.value = null;
        },
    });
}
</script>

<template>
    <Head title="Members" />

    <h1 class="sr-only">Members</h1>

    <div class="flex flex-col space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Members"
                :description="`People in ${organization.name} and their role`"
            />

            <Dialog v-model:open="inviteOpen">
                <DialogTrigger as-child>
                    <Button size="sm" class="shrink-0">
                        <UserPlus />
                        Invite
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Invite to {{ organization.name }}</DialogTitle>
                        <DialogDescription>
                            We'll email a link to join. It expires in 7 days.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-6">
                        <div class="grid gap-2">
                            <Label for="invite-email">Email address</Label>
                            <Input
                                id="invite-email"
                                v-model="inviteEmail"
                                type="email"
                                required
                                placeholder="email@example.com"
                            />
                            <InputError :message="inviteErrors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Role</Label>
                            <Select v-model="inviteRole">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="member">
                                        Member &mdash; full access to the
                                        organization
                                    </SelectItem>
                                    <SelectItem value="guest">
                                        Guest &mdash; only the projects you grant
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="inviteErrors.role" />
                        </div>

                        <div class="grid gap-2">
                            <Label>
                                Project access
                                <span class="text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Select v-model="inviteProjectId">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="No project yet" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="project in projects"
                                        :key="project.id"
                                        :value="String(project.id)"
                                    >
                                        {{ project.key }} &middot;
                                        {{ project.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="inviteErrors.project_id" />
                        </div>

                        <div v-if="hasProject" class="grid gap-2">
                            <Label>Level</Label>
                            <Select v-model="inviteLevel">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="admin">Admin</SelectItem>
                                    <SelectItem value="write">Write</SelectItem>
                                    <SelectItem value="read">Read</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="inviteErrors.level" />
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button type="button" @click="invite">
                            Send invitation
                        </Button>
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
                            <SelectItem value="guest">Guest</SelectItem>
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

        <template v-if="invitations.length > 0">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Invited but not yet accepted"
            />
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
                >
                    <Avatar class="size-8 shrink-0">
                        <AvatarFallback class="text-xs text-muted-foreground">
                            <Mail class="size-3.5" />
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ invitation.email }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ roleLabels[invitation.role] }}
                            <template v-if="invitation.projectName">
                                &middot; {{ invitation.projectName }}
                                <span v-if="invitation.level">
                                    ({{ levelLabels[invitation.level] }})
                                </span>
                            </template>
                            &middot; expires
                            {{ formatExpiry(invitation.expiresAt) }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="resend(invitation)"
                    >
                        Resend
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="revoke(invitation)"
                    >
                        Revoke
                    </Button>
                </div>
            </div>
        </template>
    </div>

    <Dialog
        :open="removing !== null"
        @update:open="(open) => !open && (removing = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Remove member</DialogTitle>
                <DialogDescription>
                    Remove {{ removing?.name }} from {{ organization.name }}?
                    They'll lose access to every project in it.
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
