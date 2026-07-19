<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Settings/CategoryController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ProjectCategory } from '@/types';

const props = defineProps<{
    category: ProjectCategory;
    categories: ProjectCategory[];
}>();

// A category can't be moved under itself or any of its descendants.
const parentOptions = computed(() => {
    const blocked = new Set<number>([props.category.id]);

    for (const candidate of props.categories) {
        if (candidate.parentId !== null && blocked.has(candidate.parentId)) {
            blocked.add(candidate.id);
        }
    }

    return props.categories.filter((candidate) => !blocked.has(candidate.id));
});

const defaultParent = computed(() =>
    props.category.parentId === null ? '' : String(props.category.parentId),
);
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">{{ $t('common.edit') }}</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="
                    CategoryController.update.form({ category: category.id })
                "
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        $t('categories.editCategory')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('categories.editDescription') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`name-${category.id}`">{{
                        $t('common.name')
                    }}</Label>
                    <Input
                        :id="`name-${category.id}`"
                        name="name"
                        :default-value="category.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`parent-${category.id}`">{{
                        $t('categories.parent')
                    }}</Label>
                    <Select name="parent_id" :default-value="defaultParent">
                        <SelectTrigger
                            :id="`parent-${category.id}`"
                            class="w-full"
                        >
                            <SelectValue
                                :placeholder="$t('common.noneTopLevel')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">{{
                                $t('common.noneTopLevel')
                            }}</SelectItem>
                            <SelectItem
                                v-for="option in parentOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ '  '.repeat(option.depth) }}{{ option.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.parent_id" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">{{
                            $t('common.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
