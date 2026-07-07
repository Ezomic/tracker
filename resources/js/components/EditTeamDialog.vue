<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import TeamController from '@/actions/App/Http/Controllers/Settings/TeamController';
import InputError from '@/components/InputError.vue';
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
import type { Team } from '@/types';

defineProps<{
    team: Team;
}>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Edit</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="TeamController.update.form({ team: team.id })"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Edit team</DialogTitle>
                    <DialogDescription>
                        Update the team's name{{
                            team.keyLocked ? '' : ' or key'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`name-${team.id}`">Name</Label>
                    <Input
                        :id="`name-${team.id}`"
                        name="name"
                        :default-value="team.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`key-${team.id}`">Key</Label>
                    <Input
                        :id="`key-${team.id}`"
                        name="key"
                        :default-value="team.key"
                        :disabled="team.keyLocked"
                        maxlength="10"
                        pattern="[A-Z]{2,10}"
                        class="uppercase"
                    />
                    <InputError :message="errors.key" />
                    <p
                        v-if="team.keyLocked"
                        class="text-sm text-muted-foreground"
                    >
                        The key can't change once a team has issues.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing"> Save </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
