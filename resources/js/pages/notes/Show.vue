<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import MarkdownContent from '@/components/MarkdownContent.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as projectsIndex } from '@/routes/projects';
import { destroy, index, update } from '@/routes/projects/notes';
import type { NoteDetail, NoteProject } from '@/types';

const props = defineProps<{
    project: NoteProject;
    note: NoteDetail;
    can: { update: boolean; delete: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

const { t } = useI18n();

const editing = ref(false);
const form = useForm({ title: props.note.title, body: props.note.body });

function startEditing() {
    form.title = props.note.title;
    form.body = props.note.body;
    form.clearErrors();
    editing.value = true;
}

function save() {
    form.patch(
        update.url({ project: props.project.key, note: props.note.id }),
        { onSuccess: () => (editing.value = false) },
    );
}

function remove() {
    if (
        !window.confirm(t('notes.deleteConfirm', { title: props.note.title }))
    ) {
        return;
    }

    router.delete(
        destroy.url({ project: props.project.key, note: props.note.id }),
    );
}

function when(iso: string): string {
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head :title="note.title" />

    <div class="flex max-w-3xl flex-col gap-4 p-4">
        <Link
            :href="index(project.key)"
            class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ $t('notes.backToNotes') }}
        </Link>

        <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
            <div class="flex flex-col gap-1.5">
                <Label for="note-title">{{ $t('notes.titleLabel') }}</Label>
                <Input id="note-title" v-model="form.title" maxlength="200" />
                <InputError :message="form.errors.title" />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label>{{ $t('notes.bodyLabel') }}</Label>
                <MarkdownEditor v-model="form.body" :rows="12" />
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
                    @click="editing = false"
                >
                    {{ $t('common.cancel') }}
                </Button>
            </div>
        </form>

        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ note.title }}</h1>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ note.author ?? $t('notes.unknownAuthor') }} ·
                        {{
                            $t('notes.updated', { when: when(note.updatedAt) })
                        }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="can.update"
                        size="sm"
                        variant="outline"
                        @click="startEditing"
                    >
                        <Pencil />
                        {{ $t('common.edit') }}
                    </Button>
                    <Button
                        v-if="can.delete"
                        size="sm"
                        variant="outline"
                        @click="remove"
                    >
                        <Trash2 />
                        {{ $t('common.delete') }}
                    </Button>
                </div>
            </div>

            <MarkdownContent v-if="note.body !== ''" :source="note.body" />
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('notes.noBody') }}
            </p>
        </template>
    </div>
</template>
