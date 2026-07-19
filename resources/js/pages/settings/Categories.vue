<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Settings/CategoryController';
import EditCategoryDialog from '@/components/EditCategoryDialog.vue';
import Heading from '@/components/Heading.vue';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, index } from '@/routes/categories';
import type { ProjectCategory } from '@/types';

const props = defineProps<{
    categories: ProjectCategory[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: index() }],
    },
});

const removing = ref<ProjectCategory | null>(null);

function remove() {
    if (removing.value === null) {
        return;
    }

    router.delete(destroy.url({ category: removing.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            removing.value = null;
        },
    });
}

const hasCategories = () => props.categories.length > 0;
</script>

<template>
    <Head :title="$t('categories.title')" />

    <h1 class="sr-only">{{ $t('categories.title') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('categories.title')"
            :description="$t('categories.description')"
        />

        <Form
            v-if="canManage"
            v-bind="CategoryController.store.form()"
            reset-on-success
            class="flex flex-wrap items-end gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ $t('common.name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    class="w-56"
                    :placeholder="$t('categories.namePlaceholder')"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="parent_id">{{ $t('categories.parent') }}</Label>
                <Select name="parent_id" default-value="">
                    <SelectTrigger id="parent_id" class="w-56">
                        <SelectValue :placeholder="$t('common.noneTopLevel')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">{{
                            $t('common.noneTopLevel')
                        }}</SelectItem>
                        <SelectItem
                            v-for="option in categories"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ '  '.repeat(option.depth) }}{{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.parent_id" />
            </div>

            <Button type="submit" :disabled="processing">{{
                $t('categories.addCategory')
            }}</Button>
        </Form>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <p
                v-if="!hasCategories()"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                {{ $t('categories.empty') }}
            </p>
            <div
                v-for="category in categories"
                :key="category.id"
                class="flex items-center gap-3 border-t border-sidebar-border/70 px-4 py-3 first:border-t-0 dark:border-sidebar-border"
            >
                <span
                    class="truncate text-sm"
                    :style="{ paddingLeft: `${category.depth * 1.25}rem` }"
                >
                    {{ category.name }}
                </span>
                <span class="ml-auto text-xs text-muted-foreground">
                    {{
                        $t(
                            'categories.projectCount',
                            category.projectsCount ?? 0,
                        )
                    }}
                </span>
                <template v-if="canManage">
                    <EditCategoryDialog
                        :category="category"
                        :categories="categories"
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        @click="removing = category"
                    >
                        {{ $t('common.delete') }}
                    </Button>
                </template>
            </div>
        </div>
    </div>

    <Dialog
        :open="removing !== null"
        @update:open="(open) => !open && (removing = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('categories.deleteCategory') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t('categories.deleteConfirm', {
                            name: removing?.name,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">{{
                        $t('common.cancel')
                    }}</Button>
                </DialogClose>
                <Button variant="destructive" @click="remove">{{
                    $t('common.delete')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
