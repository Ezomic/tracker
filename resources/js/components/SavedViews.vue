<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bookmark, Check, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { destroy, store } from '@/routes/saved-views';
import type { IssueFilters, SavedView } from '@/types';

const props = defineProps<{
    views: SavedView[];
    criteria: Partial<IssueFilters>;
    projectId: number | null;
    canSave: boolean;
}>();

const emit = defineEmits<{
    apply: [criteria: Partial<IssueFilters>];
}>();

const { t } = useI18n();

const saveOpen = ref(false);
const name = ref('');

function apply(view: SavedView) {
    emit('apply', view.criteria);
}

function openSave() {
    name.value = '';
    saveOpen.value = true;
}

function save() {
    if (name.value.trim() === '') {
        return;
    }

    router.post(
        store().url,
        {
            name: name.value.trim(),
            project_id: props.projectId,
            criteria: props.criteria,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['savedViews'],
            onSuccess: () => {
                saveOpen.value = false;
            },
        },
    );
}

function remove(view: SavedView) {
    router.delete(destroy(view.id).url, {
        preserveState: true,
        preserveScroll: true,
        only: ['savedViews'],
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="h-8 gap-1.5">
                <Bookmark class="size-3.5" />
                {{ t('savedViews.label') }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <template v-if="views.length > 0">
                <DropdownMenuItem
                    v-for="view in views"
                    :key="view.id"
                    class="group justify-between gap-2"
                    @select="apply(view)"
                >
                    <span class="flex items-center gap-2 truncate">
                        <Check class="size-3.5 shrink-0 text-muted-foreground" />
                        <span class="truncate">{{ view.name }}</span>
                    </span>
                    <button
                        type="button"
                        class="shrink-0 rounded p-0.5 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive"
                        :title="t('savedViews.delete')"
                        @click.stop="remove(view)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
            </template>
            <p
                v-else
                class="px-2 py-1.5 text-xs text-muted-foreground"
            >
                {{ t('savedViews.empty') }}
            </p>

            <DropdownMenuItem :disabled="!canSave" @select="openSave">
                {{ t('savedViews.save') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="saveOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ t('savedViews.saveTitle') }}</DialogTitle>
            </DialogHeader>
            <Input
                v-model="name"
                :placeholder="t('savedViews.namePlaceholder')"
                autofocus
                @keydown.enter.prevent="save"
            />
            <DialogFooter>
                <Button variant="ghost" size="sm" @click="saveOpen = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button size="sm" :disabled="name.trim() === ''" @click="save">
                    {{ t('savedViews.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
