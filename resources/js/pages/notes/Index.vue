<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    board as projectBoard,
    index as projectsIndex,
} from '@/routes/projects';
import { index, show, store } from '@/routes/projects/notes';
import type { NoteProject, NoteSummary } from '@/types';

const props = defineProps<{
    project: NoteProject;
    notes: NoteSummary[];
    filters: { search: string };
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const search = ref(props.filters.search);
const debouncedSearch = refDebounced(search, 300);

watch(debouncedSearch, (value) => {
    if (value.trim() === props.filters.search) {
        return;
    }

    router.get(
        index.url(props.project.key),
        value.trim() === '' ? {} : { search: value.trim() },
        { preserveState: true, replace: true },
    );
});

const creating = ref(false);
const form = useForm({ title: '', body: '' });

function create() {
    form.post(store.url(props.project.key), {
        onSuccess: () => form.reset(),
    });
}

function when(iso: string): string {
    return new Date(iso).toLocaleDateString();
}
</script>

<template>
    <Head :title="$t('notes.headTitle', { project: project.name })" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex items-baseline gap-2">
                    <h1 class="text-lg font-medium">{{ project.name }}</h1>
                    <span class="text-sm text-muted-foreground">
                        {{ notes.length }}
                    </span>
                </div>
                <Link
                    :href="projectBoard(project.key)"
                    class="text-sm text-muted-foreground hover:text-foreground"
                >
                    {{ $t('notes.backToBoard') }}
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        :placeholder="$t('notes.search')"
                        class="h-9 w-52 pl-8"
                    />
                </div>
                <Button
                    v-if="can.create"
                    size="sm"
                    @click="creating = !creating"
                >
                    <Plus />
                    {{ $t('notes.new') }}
                </Button>
            </div>
        </div>

        <form
            v-if="creating && can.create"
            class="flex max-w-3xl flex-col gap-3 rounded-lg border p-4"
            @submit.prevent="create"
        >
            <div class="flex flex-col gap-1.5">
                <Label for="note-title">{{ $t('notes.titleLabel') }}</Label>
                <Input
                    id="note-title"
                    v-model="form.title"
                    maxlength="200"
                    autofocus
                />
                <InputError :message="form.errors.title" />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label>{{ $t('notes.bodyLabel') }}</Label>
                <MarkdownEditor v-model="form.body" :rows="8" />
                <InputError :message="form.errors.body" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="form.processing">
                    {{ $t('common.save') }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="creating = false"
                >
                    {{ $t('common.cancel') }}
                </Button>
            </div>
        </form>

        <p v-if="notes.length === 0" class="text-sm text-muted-foreground">
            {{ filters.search ? $t('notes.noResults') : $t('notes.empty') }}
        </p>

        <ul v-else class="flex max-w-3xl flex-col divide-y rounded-lg border">
            <li v-for="note in notes" :key="note.id">
                <Link
                    :href="show({ project: project.key, note: note.id })"
                    class="flex flex-col gap-0.5 px-4 py-3 hover:bg-muted/50"
                >
                    <span class="font-medium">{{ note.title }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ note.author ?? $t('notes.unknownAuthor') }} ·
                        {{ when(note.updatedAt) }}
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>
