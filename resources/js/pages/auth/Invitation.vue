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
    expired: 'invitation.expired',
    accepted: 'invitation.accepted',
    invalid: 'invitation.invalid',
};
</script>

<template>
    <Head title="Organization invitation" />

    <div class="space-y-6">
        <template v-if="state === 'guest' && invitation">
            <p class="text-center text-sm text-muted-foreground">
                {{
                    $t('invitation.invitedSentence', {
                        inviter:
                            invitation.inviterName ?? $t('invitation.someone'),
                        email: invitation.email,
                        organization: invitation.organizationName,
                        role: invitation.roleLabel,
                    })
                }}
            </p>

            <p
                v-if="invitation.projectName"
                class="text-center text-sm text-muted-foreground"
            >
                {{
                    $t('invitation.projectAccess', {
                        project: invitation.projectName,
                    })
                }}
            </p>

            <div class="grid gap-2">
                <Button v-if="hasAccount" class="w-full" as-child>
                    <a :href="login().url">{{
                        $t('invitation.logInToAccept')
                    }}</a>
                </Button>
                <Button v-else class="w-full" as-child>
                    <a
                        :href="
                            register({ query: { email: invitation.email } }).url
                        "
                    >
                        {{ $t('invitation.createToAccept') }}
                    </a>
                </Button>
            </div>

            <p class="text-center text-xs text-muted-foreground">
                {{ $t('invitation.comeBack') }}
            </p>
        </template>

        <template v-else-if="state === 'mismatch' && invitation">
            <p class="text-center text-sm text-muted-foreground">
                {{
                    $t('invitation.mismatch', {
                        invited: invitation.email,
                        current: currentEmail,
                    })
                }}
            </p>
            <p class="text-center text-sm text-muted-foreground">
                {{
                    $t('invitation.mismatchSignIn', {
                        organization: invitation.organizationName,
                    })
                }}
            </p>
        </template>

        <template v-else>
            <p class="text-center text-sm text-muted-foreground">
                {{ $t(messages[state]) }}
            </p>
            <div class="text-center text-sm">
                <TextLink :href="login()">{{
                    $t('invitation.goToLogIn')
                }}</TextLink>
            </div>
        </template>
    </div>
</template>
