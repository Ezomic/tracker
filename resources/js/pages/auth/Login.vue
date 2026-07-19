<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { create as createLoginCode } from '@/routes/login/code';
import { redirect as ssoRedirect } from '@/routes/sso';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description:
            'Sign in with Thijssensoftware, an email code, or a passkey',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="grid gap-2">
        <Button class="w-full" as-child>
            <a :href="ssoRedirect().url">{{ $t('auth.signInWith') }}</a>
        </Button>

        <Button type="button" variant="outline" class="w-full" as-child>
            <Link :href="createLoginCode()">
                <Mail class="h-4 w-4" />
                {{ $t('auth.logInWithCode') }}
            </Link>
        </Button>
    </div>

    <PasskeyVerify />

    <div class="mt-4 space-x-1 text-center text-sm text-muted-foreground">
        <span>{{ $t('auth.newHere') }}</span>
        <TextLink :href="register()">{{ $t('auth.createAccount') }}</TextLink>
    </div>
</template>
