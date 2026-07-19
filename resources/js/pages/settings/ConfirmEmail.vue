<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import EmailConfirmationController from '@/actions/App/Http/Controllers/Settings/EmailConfirmationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { confirm, edit } from '@/routes/security';

defineProps<{
    email: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Security settings', href: edit() },
            { title: 'Confirm email', href: confirm() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('confirmEmail.headTitle')" />

    <div class="max-w-md space-y-6">
        <Heading
            variant="small"
            :title="$t('confirmEmail.title')"
            :description="$t('confirmEmail.description')"
        />

        <p class="text-sm text-muted-foreground">
            {{ $t('confirmEmail.sentCode') }}
            <span class="font-medium text-foreground">{{ email }}</span>
        </p>

        <Form
            v-bind="EmailConfirmationController.store.form()"
            reset-on-error
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="code">{{ $t('auth.confirmationCode') }}</Label>
                <Input
                    id="code"
                    name="code"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="123456"
                />
                <InputError :message="errors.code" />
            </div>

            <div class="mt-6 flex items-center gap-3">
                <Button :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ $t('confirmEmail.confirm') }}
                </Button>
                <Link
                    :href="confirm()"
                    class="text-sm text-muted-foreground hover:text-foreground"
                >
                    {{ $t('confirmEmail.resend') }}
                </Link>
            </div>
        </Form>
    </div>
</template>
