<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    email?: string | null;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Just your name and email — no password needed',
    },
});
</script>

<template>
    <Head title="Create your account" />

    <div class="space-y-6">
        <Form v-bind="store.form()" v-slot="{ errors, processing }">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('common.name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        name="name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Ada Lovelace"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ $t('common.emailAddress') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :default-value="email ?? ''"
                        required
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ $t('auth.sendVerificationCode') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ $t('auth.alreadyHaveAccount') }}</span>
            <TextLink :href="login()">{{ $t('auth.logIn') }}</TextLink>
        </div>
    </div>
</template>
