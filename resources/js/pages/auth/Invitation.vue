<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

defineProps<{
    state: 'guest' | 'mismatch' | 'expired' | 'accepted' | 'invalid';
    invitation: {
        email: string;
        roleLabel: string;
        organizationName: string;
        projectName: string | null;
        inviterName: string | null;
    } | null;
    hasAccount?: boolean;
    currentEmail?: string;
}>();

defineOptions({
    layout: {
        title: 'Organization invitation',
        description: 'Join an organization on Tracker',
    },
});

const messages: Record<string, string> = {
    expired: 'This invitation has expired. Ask for a new one.',
    accepted: 'This invitation has already been used.',
    invalid: "This invitation link isn't valid.",
};
</script>

<template>
    <Head title="Organization invitation" />

    <div class="space-y-6">
        <template v-if="state === 'guest' && invitation">
            <p class="text-center text-sm text-muted-foreground">
                <span v-if="invitation.inviterName" class="font-medium">
                    {{ invitation.inviterName }}
                </span>
                <span v-else class="font-medium">Someone</span>
                invited
                <span class="font-medium text-foreground">
                    {{ invitation.email }}
                </span>
                to join
                <span class="font-medium text-foreground">
                    {{ invitation.organizationName }}
                </span>
                as {{ invitation.roleLabel }}.
            </p>

            <p
                v-if="invitation.projectName"
                class="text-center text-sm text-muted-foreground"
            >
                You'll get access to
                <span class="font-medium text-foreground">
                    {{ invitation.projectName }}
                </span>
                right away.
            </p>

            <div class="grid gap-2">
                <Button v-if="hasAccount" class="w-full" as-child>
                    <a :href="login().url">Log in to accept</a>
                </Button>
                <Button v-else class="w-full" as-child>
                    <a
                        :href="
                            register({ query: { email: invitation.email } }).url
                        "
                    >
                        Create your account to accept
                    </a>
                </Button>
            </div>

            <p class="text-center text-xs text-muted-foreground">
                You'll come straight back here once you're signed in.
            </p>
        </template>

        <template v-else-if="state === 'mismatch' && invitation">
            <p class="text-center text-sm text-muted-foreground">
                This invitation is for
                <span class="font-medium text-foreground">
                    {{ invitation.email }}
                </span>
                , but you're signed in as
                <span class="font-medium text-foreground">
                    {{ currentEmail }}
                </span>
                .
            </p>
            <p class="text-center text-sm text-muted-foreground">
                Sign in with the invited address to join
                {{ invitation.organizationName }}.
            </p>
        </template>

        <template v-else>
            <p class="text-center text-sm text-muted-foreground">
                {{ messages[state] }}
            </p>
            <div class="text-center text-sm">
                <TextLink :href="login()">Go to log in</TextLink>
            </div>
        </template>
    </div>
</template>
