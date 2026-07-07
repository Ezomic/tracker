<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TeamController from '@/actions/App/Http/Controllers/Settings/TeamController';
import EditTeamDialog from '@/components/EditTeamDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/teams';
import type { Team } from '@/types';

defineProps<{
    teams: Team[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Teams',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Teams" />

    <h1 class="sr-only">Teams</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Teams"
            description="Teams give issues their prefix (e.g. THI-274) and independent numbering"
        />

        <Form
            v-bind="TeamController.store.form()"
            reset-on-success
            class="flex items-end gap-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="key">Key</Label>
                <Input
                    id="key"
                    name="key"
                    maxlength="10"
                    pattern="[A-Z]{2,10}"
                    class="w-32 uppercase"
                    placeholder="THI"
                    required
                />
                <InputError :message="errors.key" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    class="w-64"
                    placeholder="Thijssen Software"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <Button type="submit" :disabled="processing">Add team</Button>
        </Form>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Key</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Issues</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty v-if="teams.length === 0" :colspan="4">
                    No teams yet - add one above.
                </TableEmpty>
                <TableRow v-for="team in teams" :key="team.id">
                    <TableCell class="font-mono">{{ team.key }}</TableCell>
                    <TableCell>{{ team.name }}</TableCell>
                    <TableCell>{{ team.issuesCount }}</TableCell>
                    <TableCell class="text-right">
                        <EditTeamDialog :team="team" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
